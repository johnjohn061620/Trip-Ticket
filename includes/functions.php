<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const UPLOAD_DIR = __DIR__ . '/../assets/uploads';
const UPLOAD_URL = 'assets/uploads';

function get_env(): array
{
    // Production (Render/containers) supplies credentials via environment variables.
    $fromEnv = getenv('DB_HOST');
    if ($fromEnv !== false && $fromEnv !== '') {
        return [
            'db_host' => $fromEnv,
            'db_port' => (int) (getenv('DB_PORT') ?: 3306),
            'db_name' => (getenv('DB_NAME') ?: 'trip_ticket'),
            'db_user' => (getenv('DB_USER') ?: 'root'),
            'db_pass' => (getenv('DB_PASS') !== false ? getenv('DB_PASS') : ''),
            // TiDB Serverless / Aiven / most free MySQL clouds require TLS.
            // Point DB_SSL_CA at a CA bundle file (e.g. isrgrootx1.pem) to enable it.
            'db_ssl_ca' => getenv('DB_SSL_CA') ?: '',
        ];
    }
    $file = __DIR__ . '/../config/env.php';
    if (!is_file($file)) {
        http_response_code(500);
        exit('Missing config/env.php - copy config/env.example.php to config/env.php and set your database credentials.');
    }
    return require $file;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Escapes text but keeps line breaks: newlines and literal <br> tags
 * typed in Settings become real <br> elements. Safe for DB-sourced text.
 */
function line_breaks(?string $value): string
{
    $text = str_ireplace(['<br>', '<br/>', '<br />', '<br>'], "\n", (string) $value);
    return nl2br(e($text));
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function take_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['admin']) || !empty($_SESSION['driver_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

/** Signed-in role: 'admin', 'driver' or empty for guests. */
function current_role(): string
{
    if (!empty($_SESSION['driver_id'])) {
        return 'driver';
    }
    if (!empty($_SESSION['admin'])) {
        return 'admin';
    }
    return '';
}

function is_admin(): bool
{
    return current_role() === 'admin';
}

function is_driver(): bool
{
    return current_role() === 'driver';
}

/** Full name of the signed-in driver (driver login sessions). */
function driver_session_name(): string
{
    return (string) ($_SESSION['driver_name'] ?? '');
}

/** Pages that only administrators may open (Settings, Drivers management). */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash('That page is for administrators only.', 'err');
        redirect('index.php');
    }
}

function settings(): array
{
    static $row = null;
    if ($row !== null) {
        return $row;
    }
    $stmt = db()->query('SELECT * FROM settings WHERE id = 1');
    $row = $stmt->fetch() ?: [];
    return $row;
}

function logo_url(?string $path): string
{
    if (!$path) {
        return 'assets/img/logo-placeholder.svg';
    }
    return UPLOAD_URL . '/' . rawurlencode(basename($path));
}

function logo_html(array $s, int $size = 48): string
{
    $url = !empty($s['logo_path']) ? logo_url($s['logo_path']) : 'assets/img/logo-placeholder.svg';
    return '<img src="' . e($url) . '" alt="Logo" style="max-width:' . $size . 'px;max-height:' . $size . 'px;">';
}

/**
 * Validates and stores an uploaded logo. Returns the stored file name.
 * Throws RuntimeException on invalid files.
 */
function store_logo(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (error code ' . (int) $file['error'] . ').');
    }
    if ($file['size'] > 3 * 1024 * 1024) {
        throw new RuntimeException('Logo must be 3 MB or smaller.');
    }

    $allowedExt = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('Allowed logo formats: PNG, JPG, GIF, WEBP, SVG.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowedMime = [
        'image/png', 'image/jpeg', 'image/gif', 'image/webp',
        'image/svg+xml', 'image/svg', 'text/plain', 'text/xml', 'application/xml',
    ];
    if (!in_array($mime, $allowedMime, true)) {
        throw new RuntimeException('The uploaded file is not a valid image.');
    }

    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
        throw new RuntimeException('Cannot create the uploads directory.');
    }

    $name = bin2hex(random_bytes(10)) . '.' . $ext;
    $dest = UPLOAD_DIR . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }
    @chmod($dest, 0644);
    return $name;
}

function delete_logo_file(?string $path): void
{
    if (!$path) {
        return;
    }
    $file = UPLOAD_DIR . '/' . basename($path);
    if (is_file($file)) {
        @unlink($file);
    }
}

function num(?string $v): ?float
{
    if ($v === null || trim($v) === '') {
        return null;
    }
    return (float) str_replace(',', '', $v);
}

/** Server-side recomputation of the gasoline totals. */
function calc_gas(?float $a, ?float $b, ?float $d): array
{
    $a = $a ?? 0.0;
    $b = $b ?? 0.0;
    $d = $d ?? 0.0;
    $c = $a + $b;
    $e = $c - $d;
    return [
        'gas_a' => $a ?: null,
        'gas_b' => $b ?: null,
        'gas_c' => $c ?: null,
        'gas_d' => $d ?: null,
        'gas_e' => ($c || $d) ? $e : null,
    ];
}

/** Server-side recomputation of distance travelled. */
function calc_speed(?float $start, ?float $end): array
{
    $dist = ($start !== null && $end !== null && $end >= $start && $end > 0) ? $end - $start : null;
    return ['speed_start' => $start, 'speed_end' => $end, 'speed_dist' => $dist];
}

const LOG_COLUMNS = ['date', 'depart', 'arrive', 'from', 'to', 'passenger'];
const LOG_ROWS = 23;

/**
 * Builds the ticket-list WHERE clause from GET filters.
 * Returns [whereSql, params, filters] where filters holds the sanitized values.
 */
function ticket_filter(array $get): array
{
    $q     = trim((string) ($get['q'] ?? ''));
    $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) ($get['month'] ?? '')) ? (string) $get['month'] : '';
    $from  = valid_date_param((string) ($get['from'] ?? ''));
    $to    = valid_date_param((string) ($get['to'] ?? ''));
    $veh   = trim((string) ($get['vehicle'] ?? ''));

    $names = [];
    foreach (vehicle_list() as $v) {
        $names[] = $v['name'];
    }
    if ($veh !== '' && !in_array($veh, $names, true)) {
        $veh = '';
    }

    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = '(ticket_no LIKE ? OR driver_name LIKE ? OR purpose LIKE ? OR places LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like);
    }
    if ($veh !== '') {
        $where[] = 'vehicle_name = ?';
        $params[] = $veh;
    }
    if ($month !== '') {
        $where[] = "DATE_FORMAT(date_iso, '%Y-%m') = ?";
        $params[] = $month;
    }
    if ($from !== '') {
        $where[] = 'date_iso >= ?';
        $params[] = $from;
    }
    if ($to !== '') {
        $where[] = 'date_iso <= ?';
        $params[] = $to;
    }

    return [
        $where ? ' WHERE ' . implode(' AND ', $where) : '',
        $params,
        ['q' => $q, 'month' => $month, 'from' => $from, 'to' => $to, 'vehicle' => $veh],
    ];
}

/** yyyy-mm-dd date or empty string (shared by the list filters and exports). */
function valid_date_param(string $v): string
{
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return $v;
    }
    return '';
}

/**
 * Parses a ticket date (m/d/Y, n/j/Y, Y-m-d or d/m/Y) into Y-m-d for filtering.
 * Returns null when the text is empty or not a real date.
 */
function parse_date_iso(?string $raw): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }
    $formats = [
        ['!m/d/Y', 'm/d/Y'],
        ['!n/j/Y', 'n/j/Y'],
        ['!Y-m-d', 'Y-m-d'],
        ['!d/m/Y', 'd/m/Y'],
        ['!m-d-Y', 'm-d-Y'],
    ];
    foreach ($formats as [$parse, $print]) {
        $d = DateTime::createFromFormat($parse, $raw);
        if ($d && $d->format($print) === $raw) {
            return $d->format('Y-m-d');
        }
    }
    $ts = strtotime($raw);
    return $ts ? date('Y-m-d', $ts) : null;
}

/**
 * Fuel tank balance left by the most recent ticket - the next trip starts from it.
 * Pass the vehicle name to get that vehicle's own previous ending balance.
 */
function previous_end_balance(?int $exceptId = null, ?string $vehicleName = null): ?float
{
    $sql = 'SELECT gas_e FROM trip_tickets WHERE gas_e IS NOT NULL';
    $params = [];
    if ($exceptId) {
        $sql .= ' AND id <> ?';
        $params[] = $exceptId;
    }
    if ($vehicleName !== null && $vehicleName !== '') {
        $sql .= ' AND vehicle_name = ?';
        $params[] = $vehicleName;
    }
    $sql .= ' ORDER BY id DESC LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ? (float) $row['gas_e'] : null;
}

/**
 * Ending speedometer reading of the most recent ticket - the next trip's
 * beginning reading starts from it (odometer carry-over, like the tank balance).
 */
function previous_speed_end(?int $exceptId = null, ?string $vehicleName = null): ?float
{
    $sql = "SELECT speed_end FROM trip_tickets
            WHERE speed_end IS NOT NULL AND TRIM(speed_end) <> ''";
    $params = [];
    if ($exceptId) {
        $sql .= ' AND id <> ?';
        $params[] = $exceptId;
    }
    if ($vehicleName !== null && $vehicleName !== '') {
        $sql .= ' AND vehicle_name = ?';
        $params[] = $vehicleName;
    }
    $sql .= ' ORDER BY id DESC LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $val = $stmt->fetchColumn();
    return ($val === false || $val === null || trim((string) $val) === '') ? null : (float) $val;
}

/** Vehicles available on tickets: id => [name, plate, km per liter]. */
function vehicle_list(): array
{
    $rows = db()->query('SELECT id, name, plate_no, km_per_liter FROM vehicles ORDER BY name')->fetchAll();
    $list = [];
    foreach ($rows as $r) {
        $list[(int) $r['id']] = [
            'name' => $r['name'],
            'plate' => (string) $r['plate_no'],
            'rate' => (float) $r['km_per_liter'],
        ];
    }
    return $list;
}

/** One vehicle by name (null when it no longer exists). */
function vehicle_by_name(string $name): ?array
{
    $stmt = db()->prepare('SELECT id, name, plate_no, km_per_liter FROM vehicles WHERE name = ?');
    $stmt->execute([$name]);
    $r = $stmt->fetch();
    if (!$r) {
        return null;
    }
    return [
        'name' => $r['name'],
        'plate' => (string) $r['plate_no'],
        'rate' => (float) $r['km_per_liter'],
    ];
}

/** Active drivers shown in the ticket dropdown. */
function driver_names(): array
{
    return db()->query(
        "SELECT full_name FROM drivers WHERE active = 1 ORDER BY full_name"
    )->fetchAll(PDO::FETCH_COLUMN);
}

/** Fuel consumed on the trip: distance travelled / consumption (km per liter). */
function fuel_used(?float $distance, ?float $rate = null): ?float
{
    if ($distance === null || $distance <= 0) {
        return null;
    }
    $rate = ($rate && $rate > 0) ? $rate : 30.0;
    return round($distance / $rate, 2);
}

/** Normalizes posted trip-log rows into a plain array. */
function collect_log_rows(array $post): array
{
    $rows = [];
    $src = $post['log'] ?? [];
    for ($i = 0; $i < LOG_ROWS; $i++) {
        $row = [];
        foreach (LOG_COLUMNS as $col) {
            $row[$col] = trim((string) ($src[$i][$col] ?? ''));
        }
        $rows[] = $row;
    }
    return $rows;
}

function decode_log_rows(?string $json): array
{
    $data = $json ? json_decode($json, true) : null;
    if (!is_array($data)) {
        $data = [];
    }
    $rows = [];
    for ($i = 0; $i < LOG_ROWS; $i++) {
        $raw = $data[$i] ?? [];
        $row = [];
        foreach (LOG_COLUMNS as $col) {
            $row[$col] = trim((string) ($raw[$col] ?? ''));
        }
        $rows[] = $row;
    }
    return $rows;
}
