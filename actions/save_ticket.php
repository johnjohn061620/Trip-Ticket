<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../index.php');
}

$id = (int) ($_POST['id'] ?? 0);

$spd = calc_speed(num($_POST['speed_start'] ?? null), num($_POST['speed_end'] ?? null));

// Time fields are entered as a time box plus an AM/PM choice; store as "9:30 PM"
$joinAmpm = function (string $key): string {
    $time = trim((string) ($_POST[$key] ?? ''));
    $ampm = strtoupper(trim((string) ($_POST[$key . '_ampm'] ?? '')));
    if (in_array($ampm, ['AM', 'PM'], true)) {
        return $time === '' ? '' : $time . ' ' . $ampm;
    }
    return $time;
};

// Vehicle choice (Aerox / Ford Pick up ...) picks the plate and consumption
$vehicleName = trim((string) ($_POST['vehicle_name'] ?? ''));
$fuelRate = (float) ($_POST['fuel_rate'] ?? 0);
$plateNo = trim((string) ($_POST['plate_no'] ?? ''));
$veh = $vehicleName !== '' ? vehicle_by_name($vehicleName) : null;
if ($veh) {
    $fuelRate = $veh['rate']; // authoritative average consumption from the vehicle list
    if ($plateNo === '' && $veh['plate'] !== '') {
        $plateNo = $veh['plate'];
    }
}
if ($vehicleName === '' || $fuelRate <= 0) {
    $first = reset(vehicle_list());
    if ($first) {
        if ($vehicleName === '') {
            $vehicleName = $first['name'];
        }
        if ($fuelRate <= 0) {
            $fuelRate = $first['rate'];
        }
    }
}
if ($fuelRate <= 0) {
    $fuelRate = 30.0;
}

// d) Deduct: used during trip = distance travelled / vehicle consumption
$gasD = fuel_used($spd['speed_dist'], $fuelRate);
if ($gasD === null) {
    $gasD = num($_POST['gas_d'] ?? null); // no speedometer reading - keep the posted value
}

$gasA = num($_POST['gas_a'] ?? null);
if ($id <= 0 && $gasA === null) {
    // A brand-new ticket starts from that vehicle's previous ending balance
    $gasA = previous_end_balance(null, $vehicleName);
}
$gasB = num($_POST['gas_b'] ?? null);

$gas = calc_gas($gasA, $gasB, $gasD);

$values = [
    'ticket_no'    => trim((string) ($_POST['ticket_no'] ?? '')),
    'ticket_date'  => trim((string) ($_POST['ticket_date'] ?? '')),
    'date_iso'     => parse_date_iso($_POST['ticket_date'] ?? ''),
    'driver_name'  => trim((string) ($_POST['driver_name'] ?? '')),
    'plate_no'     => $plateNo,
    'vehicle_name' => $vehicleName,
    'fuel_rate'    => $fuelRate,
    'passengers'   => trim((string) ($_POST['passengers'] ?? '')),
    'places'       => trim((string) ($_POST['places'] ?? '')),
    'purpose'      => trim((string) ($_POST['purpose'] ?? '')),
    'dt_needed'    => trim((string) ($_POST['dt_needed'] ?? '')),
    'dt_return'    => trim((string) ($_POST['dt_return'] ?? '')),
    'time_dep'     => $joinAmpm('time_dep'),
    'time_ret'     => $joinAmpm('time_ret'),
    'distance_km'  => trim((string) ($_POST['distance_km'] ?? '')),
    'remarks'      => trim((string) ($_POST['remarks'] ?? '')),
    'log_rows'     => json_encode(collect_log_rows($_POST), JSON_UNESCAPED_UNICODE),
] + $gas + $spd;

if ($id > 0) {
    $sets = [];
    $vals = [];
    foreach ($values as $col => $val) {
        $sets[] = "`$col` = ?";
        $vals[] = $val;
    }
    $vals[] = $id;
    $stmt = db()->prepare('UPDATE trip_tickets SET ' . implode(', ', $sets) . ' WHERE id = ?');
    $stmt->execute($vals);
    if ($stmt->rowCount() === 0) {
        flash('That trip ticket no longer exists.', 'err');
        redirect('../index.php');
    }
    flash('Trip ticket saved.');
    redirect('../ticket.php?id=' . $id);
}

$cols = array_keys($values);
$placeholders = implode(',', array_fill(0, count($cols), '?'));
$stmt = db()->prepare('INSERT INTO trip_tickets (' . implode(',', $cols) . ') VALUES (' . $placeholders . ')');
$stmt->execute(array_values($values));

flash('Trip ticket created.');
redirect('../ticket.php?id=' . (int) db()->lastInsertId());
