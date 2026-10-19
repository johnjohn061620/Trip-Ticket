<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';
require_login();

[$whereSql, $params, $flt] = ticket_filter($_GET);
$format = (string) ($_GET['format'] ?? 'csv');

$stmt = db()->prepare(
    'SELECT * FROM trip_tickets' . $whereSql . ' ORDER BY date_iso IS NULL, date_iso DESC, id DESC'
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$filterBits = [];
if ($flt['q'] !== '') {
    $filterBits[] = 'search "' . $flt['q'] . '"';
}
if ($flt['vehicle'] !== '') {
    $filterBits[] = 'vehicle ' . $flt['vehicle'];
}
if ($flt['month'] !== '') {
    $filterBits[] = 'month ' . $flt['month'];
}
if ($flt['from'] !== '' || $flt['to'] !== '') {
    $filterBits[] = 'date ' . ($flt['from'] ?: 'any') . ' to ' . ($flt['to'] ?: 'any');
}
$filterLabel = $filterBits ? implode(', ', $filterBits) : 'all tickets';
$stamp = date('Y-m-d_His');

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="trip-tickets_' . $stamp . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8 correctly

    $columns = [
        'id', 'ticket_no', 'ticket_date', 'vehicle_name', 'fuel_rate', 'driver_name', 'plate_no',
        'passengers', 'places', 'purpose', 'dt_needed', 'dt_return', 'time_dep', 'time_ret',
        'distance_km', 'gas_a', 'gas_b', 'gas_c', 'gas_d', 'gas_e',
        'speed_start', 'speed_end', 'speed_dist', 'remarks', 'log_rows', 'created_at', 'updated_at',
    ];
    fputcsv($out, $columns);
    foreach ($rows as $r) {
        $line = [];
        foreach ($columns as $c) {
            $line[] = (string) ($r[$c] ?? '');
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

// Printable backup of the filtered tickets
$page_title = 'Trip Ticket Backup';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= e($page_title) ?></title>
  <style>
    @page { size: A4 landscape; margin: 10mm; }
    * { box-sizing: border-box; }
    body { font-family: Arial, "Helvetica Neue", sans-serif; color: #000; font-size: 11px; margin: 16px; }
    h1 { font-size: 16px; margin: 0 0 4px; }
    .sub { color: #555; margin-bottom: 12px; font-size: 12px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #000; padding: 3px 4px; text-align: left; vertical-align: top; }
    th { background: #eee; font-size: 10.5px; }
    td.num { text-align: right; }
    tr { page-break-inside: avoid; }
    thead { display: table-header-group; }
    .toolbar { margin-bottom: 12px; }
    .toolbar button { padding: 7px 14px; font-size: 13px; cursor: pointer; }
    @media print { .toolbar { display: none; } body { margin: 0; } }
  </style>
</head>
<body>
  <div class="toolbar">
    <button onclick="window.print()">Print / Save as PDF</button>
  </div>
  <h1>Trip Ticket Backup</h1>
  <div class="sub">
    <?= count($rows) ?> ticket(s) &middot; <?= e($filterLabel) ?> &middot; generated <?= e(date('M j, Y g:i A')) ?>
  </div>
  <table>
    <thead>
      <tr>
        <th>Ticket No.</th>
        <th>Date</th>
        <th>Vehicle</th>
        <th>Plate</th>
        <th>Driver</th>
        <th>Place(s)</th>
        <th>Purpose</th>
        <th>Dep / Ret</th>
        <th>Dist.</th>
        <th>a</th>
        <th>b</th>
        <th>c</th>
        <th>d</th>
        <th>e</th>
        <th>Remarks</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="15">No tickets match the filters.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['ticket_no']) ?></td>
          <td><?= e($r['ticket_date']) ?></td>
          <td><?= e($r['vehicle_name']) ?></td>
          <td><?= e($r['plate_no']) ?></td>
          <td><?= e($r['driver_name']) ?></td>
          <td><?= nl2br(e((string) $r['places'])) ?></td>
          <td><?= nl2br(e((string) $r['purpose'])) ?></td>
          <td><?= e(trim($r['time_dep'] . ' / ' . $r['time_ret'], ' /')) ?></td>
          <td class="num"><?= e((string) $r['distance_km']) ?></td>
          <td class="num"><?= e((string) $r['gas_a']) ?></td>
          <td class="num"><?= e((string) $r['gas_b']) ?></td>
          <td class="num"><?= e((string) $r['gas_c']) ?></td>
          <td class="num"><?= e((string) $r['gas_d']) ?></td>
          <td class="num"><?= e((string) $r['gas_e']) ?></td>
          <td><?= e((string) $r['remarks']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
