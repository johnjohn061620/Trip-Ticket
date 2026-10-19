<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';
require_login();
require_admin();

set_time_limit(0);
ignore_user_abort(true);

function pdf_find_chrome(): string
{
    $envPath = getenv('CHROME_PATH');
    if ($envPath !== false && $envPath !== '' && is_file($envPath)) {
        return $envPath;
    }
    $candidates = [
        // Linux (Docker / Render)
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
        '/usr/bin/google-chrome',
        '/usr/bin/google-chrome-stable',
        // Windows
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return '';
}

function pdf_safe_name(string $value, string $fallback): string
{
    $value = trim($value);
    if ($value === '') {
        $value = $fallback;
    }
    $value = preg_replace('/[^A-Za-z0-9._-]+/', '_', $value);
    $value = trim((string) $value, '._-');
    return $value !== '' ? $value : $fallback;
}

function pdf_rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path) && !is_link($path)) {
            pdf_rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

/** Turn the ticket page HTML into one self-contained file (no web server needed). */
function pdf_inline_assets(string $html): string
{
    $html = preg_replace_callback('#<link rel="stylesheet" href="assets/css/([^"]+)">#', function ($m) {
        $css = @file_get_contents(__DIR__ . '/assets/css/' . basename($m[1]));
        return $css !== false ? '<style>' . $css . '</style>' : '';
    }, $html);
    $html = preg_replace_callback('#<script src="assets/js/[^"]+"></script>#', function () {
        return '';
    }, $html);
    $html = preg_replace_callback('#src="(assets/(?:uploads|img)/[^"]+)"#', function ($m) {
        $path = __DIR__ . '/' . $m[1];
        if (!is_file($path)) {
            return 'src=""';
        }
        $mime = mime_content_type($path) ?: 'application/octet-stream';
        return 'src="data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path)) . '"';
    }, $html);
    return $html;
}

/** Run headless Chrome with a hard timeout; returns true when the PDF was written. */
function pdf_render(string $chrome, string $htmlFile, string $pdfFile, string $profileDir, int $timeoutSec = 30): bool
{
    @mkdir($profileDir, 0777, true);
    $null = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
    $descriptors = [
        0 => ['file', $null, 'r'],
        1 => ['file', $null, 'w'],
        2 => ['file', $null, 'w'],
    ];
    $cmd = [
        $chrome,
        '--headless',
        '--no-first-run',
        '--no-default-browser-check',
        '--disable-gpu',
        '--no-sandbox',
        '--disable-dev-shm-usage',
        '--no-pdf-header-footer',
        '--print-to-pdf-no-header',
        '--user-data-dir=' . $profileDir,
        '--print-to-pdf=' . $pdfFile,
        'file:///' . ltrim(str_replace('\\', '/', $htmlFile), '/'),
    ];
    $proc = @proc_open($cmd, $descriptors, $pipes, __DIR__);
    if (!is_resource($proc)) {
        return false;
    }
    $deadline = microtime(true) + $timeoutSec;
    while (microtime(true) < $deadline) {
        if (is_file($pdfFile) && filesize($pdfFile) > 0) {
            break;
        }
        $status = proc_get_status($proc);
        if (!$status['running'] && is_file($pdfFile)) {
            break;
        }
        usleep(200000);
    }
    $status = proc_get_status($proc);
    if (!empty($status['running'])) {
        proc_terminate($proc, 9);
    }
    proc_close($proc);
    return is_file($pdfFile) && filesize($pdfFile) > 0;
}

$chrome = pdf_find_chrome();
if ($chrome === '') {
    flash('Chrome or Microsoft Edge is required to generate the PDF backup.', 'err');
    redirect('index.php');
}

[$whereSql, $params, $flt] = ticket_filter($_GET);
$stmt = db()->prepare(
    'SELECT id, ticket_no FROM trip_tickets' . $whereSql .
    ' ORDER BY date_iso IS NULL, date_iso DESC, id DESC'
);
$stmt->execute($params);
$pdfRows = $stmt->fetchAll();
if (!$pdfRows) {
    flash('No tickets match the filters.', 'err');
    redirect('index.php');
}

$tmpBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'trip_ticket_pdf_' . bin2hex(random_bytes(4));
@mkdir($tmpBase, 0777, true);
$stamp = date('Y-m-d_His');
$zipPath = $tmpBase . DIRECTORY_SEPARATOR . 'trip-tickets_' . $stamp . '.zip';

$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

$keepGet = $_GET;
$pdfCount = 0;
foreach ($pdfRows as $pdfIdx => $pdfRow) {
    $id = (int) $pdfRow['id'];

    // Render the same print document the ticket page shows
    $_GET = ['id' => $id];
    ob_start();
    include __DIR__ . '/ticket.php';
    $html = (string) ob_get_clean();
    $_GET = $keepGet;

    if (strpos($html, 'a4-container') === false) {
        continue;
    }
    $htmlFile = $tmpBase . DIRECTORY_SEPARATOR . 'ticket_' . $id . '.html';
    $pdfFile = $tmpBase . DIRECTORY_SEPARATOR . 'ticket_' . $id . '.pdf';
    file_put_contents($htmlFile, pdf_inline_assets($html));

    if (pdf_render($chrome, $htmlFile, $pdfFile, $tmpBase . DIRECTORY_SEPARATOR . 'chrome_' . $pdfIdx)) {
        $local = sprintf(
            'trip-ticket_%04d_%s.pdf',
            $id,
            pdf_safe_name((string) $pdfRow['ticket_no'], 'no-number')
        );
        $zip->addFile($pdfFile, $local);
        $pdfCount++;
    }
}
$zip->close();

if ($pdfCount === 0) {
    pdf_rrmdir($tmpBase);
    flash('PDF export failed - no documents were generated.', 'err');
    redirect('index.php');
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="trip-tickets_' . $stamp . '.zip"');
header('Content-Length: ' . (string) filesize($zipPath));
readfile($zipPath);

pdf_rrmdir($tmpBase);
exit;
