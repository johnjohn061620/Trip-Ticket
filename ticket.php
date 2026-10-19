<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';
require_login();

$s = settings();
$id = (int) ($_GET['id'] ?? 0);
$isNew = $id <= 0;
$wantVehicle = trim((string) ($_GET['vehicle'] ?? ''));
$t = null;

if (!$isNew) {
    $stmt = db()->prepare('SELECT * FROM trip_tickets WHERE id = ?');
    $stmt->execute([$id]);
    $t = $stmt->fetch();
    if (!$t) {
        flash('Trip ticket not found.', 'err');
        redirect('index.php');
    }
    $logRows = decode_log_rows($t['log_rows'] ?? null);
} else {
    $t = [
        'ticket_no'   => $s['ticket_prefix'],
        'ticket_date' => date('m/d/Y'),
        'driver_name' => is_driver() ? driver_session_name() : $s['driver_name'],
        'plate_no'    => $s['plate_no'],
        'passengers'  => '', 'places' => '', 'purpose' => '',
        'dt_needed'   => '', 'dt_return' => '', 'time_dep' => '', 'time_ret' => '',
        'distance_km' => '', 'remarks' => '',
        'gas_a' => '', 'gas_b' => '', 'gas_d' => '',
        'speed_start' => '', 'speed_end' => '',
    ];
    $carriedFromPrev = false;
    $logRows = decode_log_rows(null);
}
$carriedFromPrev = $carriedFromPrev ?? false;

// Driver dropdown options come from the registered driver accounts
$drivers = driver_names();
$currentDriver = trim((string) ($t['driver_name'] ?? ''));
if ($currentDriver !== '' && !in_array($currentDriver, $drivers, true)) {
    $drivers[] = $currentDriver;
    sort($drivers);
}
if (!$drivers) {
    $drivers = [''];
}

// Every new ticket starts by choosing the vehicle (vehicle_pick.php)
if ($isNew && $wantVehicle === '') {
    redirect('vehicle_pick.php');
}

// Vehicle selection: Aerox / Ford Pick up (each with its own plate and km per liter)
$vehicles = vehicle_list();
$defaultVehicleId = (int) ($s['default_vehicle_id'] ?? 0);
$defaultName = '';
foreach ($vehicles as $vid => $v) {
    if ($vid === $defaultVehicleId) {
        $defaultName = $v['name'];
        break;
    }
}
if ($defaultName === '' && $vehicles) {
    $firstVehicle = reset($vehicles);
    $defaultName = $firstVehicle['name'];
}

$selectedName = trim((string) ($t['vehicle_name'] ?? ''));
if ($selectedName === '') {
    $selectedName = $defaultName;
}

// ?vehicle=NAME (from the chooser) preselects that vehicle and its plate on new tickets
if ($isNew && $wantVehicle !== '') {
    $want = vehicle_by_name($wantVehicle);
    if ($want) {
        $selectedName = $want['name'];
        if ($want['plate'] !== '') {
            $t['plate_no'] = $want['plate'];
        }
    } else {
        redirect('vehicle_pick.php');
    }
}
$selectedRate = 30.0;
if ($selectedName !== '' && isset($vehicles[$defaultVehicleId]) && $vehicles[$defaultVehicleId]['name'] === $selectedName) {
    $selectedRate = $vehicles[$defaultVehicleId]['rate'];
}
foreach ($vehicles as $v) {
    if ($v['name'] === $selectedName) {
        $selectedRate = $v['rate'];
        break;
    }
}
if ($selectedRate <= 0) {
    $selectedRate = (float) ($t['fuel_rate'] ?? 30) ?: 30.0;
}

// New tickets begin with this vehicle's own previous ending tank balance
$speedCarried = false;
if ($isNew) {
    $prevBalance = previous_end_balance(null, $selectedName);
    if ($prevBalance !== null) {
        $t['gas_a'] = (string) $prevBalance;
        $carriedFromPrev = true;
    }
    // ...and with its previous ending speedometer reading as the beginning reading
    $prevSpeed = previous_speed_end(null, $selectedName);
    if ($prevSpeed !== null) {
        $t['speed_start'] = rtrim(rtrim(number_format($prevSpeed, 2, '.', ''), '0'), '.');
        $speedCarried = true;
    }
}

$val = fn(string $key): string => e((string) ($t[$key] ?? ''));
$rateNum = fn(float $r): string => rtrim(rtrim(number_format($r, 2, '.', ''), '0'), '.');
$driverSig = trim((string) ($t['driver_name'] ?? '')) !== '' ? $t['driver_name'] : $s['driver_name'];
$certName = trim((string) ($t['driver_name'] ?? '')) !== '' ? $t['driver_name'] : $s['cert_name'];

$page = 'ticket';
$page_title = $isNew ? 'New Trip Ticket' : 'Trip Ticket ' . $t['ticket_no'];
$page_css = ['assets/css/ticket.css'];
$page_js = ['assets/js/ticket.js'];
require __DIR__ . '/includes/header.php';
?>

<form class="ticket-form" method="post" action="actions/save_ticket.php">
  <input type="hidden" name="id" value="<?= (int) ($t['id'] ?? 0) ?>">
  <input type="hidden" name="fuel_rate" id="fuel_rate" value="<?= e($rateNum($selectedRate)) ?>">

  <div class="print-bar no-print-bar">
    <div class="group">
      <a class="btn ghost" href="index.php">&larr; Back to list</a>
    </div>
    <div class="group">
      <button class="btn ghost" type="button" onclick="window.print()">Print Document</button>
      <button class="btn" type="submit">Save Ticket</button>
    </div>
  </div>

  <div class="a4-container">

    <!-- LEFT COLUMN: DRIVER'S TRIP TICKET -->
    <div class="left-col">
      <div>
        <div class="header-doc-code"><?= line_breaks($s['doc_code']) ?></div>

        <div class="header-main">
          <div class="logo-box"><?= logo_html($s, 50) ?></div>
          <div class="header-text">
            <div><?= e($s['country']) ?></div>
            <div class="title-agency"><?= e($s['agency_name']) ?></div>
            <div class="office-name"><?= e($s['office_name']) ?></div>
            <div><?= e($s['address']) ?></div>
          </div>
        </div>

        <div class="doc-title">DRIVER'S TRIP TICKET</div>

        <div class="ticket-meta">
          <div>Trip Ticket No.: <input type="text" name="ticket_no" value="<?= $val('ticket_no') ?>" style="width: 130px;"></div>
          <div>Date: <input type="text" name="ticket_date" value="<?= $val('ticket_date') ?>" style="width: 110px;"></div>
        </div>

        <div class="section-head">A. TO BE FILLED UP BY THE UNIT HEAD REQUESTING TRANSPORTATION:</div>

        <div class="form-row">
          <label>1. Name of Driver of the vehicle/any available authorized DILG driver:&nbsp;</label>
          <?php if (is_driver()): ?>
            <input type="hidden" name="driver_name" value="<?= e($currentDriver) ?>">
            <select class="bold-val" disabled><option><?= $currentDriver !== '' ? e($currentDriver) : '&mdash;' ?></option></select>
          <?php else: ?>
            <select name="driver_name" id="driver_select" class="bold-val">
              <?php if ($currentDriver === ''): ?>
                <option value="">&mdash; select driver &mdash;</option>
              <?php endif; ?>
              <?php foreach ($drivers as $d): ?>
                <option value="<?= e($d) ?>"<?= ($d !== '' && $d === $currentDriver) ? ' selected' : '' ?>><?= $d !== '' ? e($d) : '&mdash; select driver &mdash;' ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
        <div class="form-row">
          <label>2. Government vehicle/Plate No. to be used:&nbsp;</label>
          <input type="text" name="plate_no" id="plate_no_input" class="bold-val" value="<?= $val('plate_no') ?>" style="text-decoration: underline;">
        </div>
        <div class="form-row no-print">
          <label>Vehicle to use:&nbsp;</label>
          <input type="hidden" name="vehicle_name" value="<?= e($selectedName) ?>">
          <?php $selVeh = $selectedName !== '' ? vehicle_by_name($selectedName) : null; ?>
          <select id="vehicle_select" disabled>
            <?php if ($selectedName === ''): ?>
              <option value="">&mdash; no vehicles yet &mdash;</option>
            <?php else: ?>
              <option value="<?= e($selectedName) ?>"
                      data-rate="<?= e($rateNum($selectedRate)) ?>"
                      data-plate="<?= e($selVeh['plate'] ?? '') ?>"
                      selected><?= e($selectedName) ?></option>
            <?php endif; ?>
          </select>
          <span class="unit-tag" id="rate_tag"><?= e($rateNum($selectedRate)) ?> km/L</span>
        </div>
        <div class="form-row">
          <label>3. Name of Authorized Passenger(s):&nbsp;</label>
          <input type="text" name="passengers" value="<?= $val('passengers') ?>">
        </div>
        <div class="form-row">
          <label>4. Place(s) to be visited:&nbsp;</label>
          <input type="text" name="places" value="<?= $val('places') ?>">
        </div>
        <div class="form-row">
          <label>5. Purpose of trip:&nbsp;</label>
          <input type="text" name="purpose" value="<?= $val('purpose') ?>">
        </div>

        <div class="form-row" style="margin-top: 8px;">
          <label>6. Date and time vehicle is needed:&nbsp;</label>
          <input type="text" name="dt_needed" value="<?= $val('dt_needed') ?>">
        </div>
        <div class="form-row">
          <label>7. Date and time return:&nbsp;</label>
          <input type="text" name="dt_return" value="<?= $val('dt_return') ?>">
        </div>

        <!-- Approval Signatures Section A -->
        <div class="approval-block">
          <div class="sig-box">
            <div style="text-align: left; font-weight: bold; font-size: 9.5px;">APPROVED:</div>
            <div class="sig-line"><?= e($s['approver1_name']) ?></div>
            <div class="sig-sub"><?= line_breaks($s['approver1_sub']) ?></div>
          </div>
          <div class="sig-box" style="margin-top: 10px;">
            <div class="sig-line"><?= e($s['approver2_name']) ?></div>
            <div class="sig-sub"><?= line_breaks($s['approver2_sub']) ?></div>
          </div>
        </div>

        <div class="section-head">B. TO BE FILLED BY THE DRIVER:</div>

        <?php
        // Split a stored "9:30 PM" into its time and AM/PM parts
        $splitAmpm = function (string $raw): array {
            $raw = trim($raw);
            if (preg_match('/^(.*?)\s+(AM|PM)$/i', $raw, $m)) {
                return [trim($m[1]), strtoupper($m[2])];
            }
            return [$raw, ''];
        };
        [$depTime, $depAmpm] = $splitAmpm((string) $t['time_dep']);
        [$retTime, $retAmpm] = $splitAmpm((string) $t['time_ret']);
        $ampmOpts = function (string $sel): string {
            $html = '<option value=""' . ($sel === '' ? ' selected' : '') . '>&mdash;</option>';
            foreach (['AM', 'PM'] as $ap) {
                $html .= '<option value="' . $ap . '"' . ($sel === $ap ? ' selected' : '') . '>' . $ap . '</option>';
            }
            return $html;
        };
        ?>
        <div class="form-row time-row">
          <label>1. Time of Departure from office/garage</label>
          <input type="text" name="time_dep" class="time-text" placeholder="00:00" value="<?= e($depTime) ?>">
          <select name="time_dep_ampm" class="ampm-select print-line"><?= $ampmOpts($depAmpm) ?></select>
        </div>
        <div class="form-row time-row">
          <label>2. Time of return to office/garage</label>
          <input type="text" name="time_ret" class="time-text" placeholder="00:00" value="<?= e($retTime) ?>">
          <select name="time_ret_ampm" class="ampm-select print-line"><?= $ampmOpts($retAmpm) ?></select>
        </div>
        <div class="form-row">
          <label>3. Approximately distance travelled (to/from)</label>
          <input type="text" name="distance_km" value="<?= $val('distance_km') ?>" style="text-align: right;">
          <span class="unit-tag">Kms.</span>
        </div>

        <div style="margin-top: 2px;">4. Gasoline purchased and consumed:</div>
        <div class="form-row sub-item">
          <label>a) Balance in tank before the trip</label>
          <input type="number" step="any" name="gas_a" id="gas_a" class="calc-input" value="<?= $val('gas_a') ?>" style="text-align: right;">
          <span class="unit-tag">liters</span>
        </div>
        <div class="form-row sub-item">
          <label>b) Add: Purchased during trip</label>
          <input type="number" step="any" name="gas_b" id="gas_b" class="calc-input" value="<?= $val('gas_b') ?>" style="text-align: right;">
          <span class="unit-tag">liters</span>
        </div>
        <div class="form-row sub-item">
          <label>c) TOTAL</label>
          <input type="number" step="any" id="gas_c" class="calc-input bold-val" style="text-align: right;" readonly>
          <span class="unit-tag">liters</span>
        </div>
        <div class="form-row sub-item">
          <label>d) Deduct: Used during trip</label>
          <input type="number" step="any" name="gas_d" id="gas_d" class="calc-input" value="<?= $val('gas_d') ?>" style="text-align: right;" readonly>
          <span class="unit-tag">liters</span>
          <span class="screen-only" id="fuel_rate_hint">= distance &divide; <?= e($rateNum($selectedRate)) ?> km/L</span>
          <span class="screen-only" id="carry_hint" style="<?= $carriedFromPrev ? '' : 'display:none;' ?>"
                title="Ending balance of this vehicle's previous trip">carried from previous trip</span>
        </div>
        <div class="form-row sub-item">
          <label>e) Balance in tank at the end of the trip</label>
          <input type="number" step="any" id="gas_e" class="calc-input bold-val" style="text-align: right;" readonly>
          <span class="unit-tag">liters</span>
        </div>

        <div style="margin-top: 2px;">5. Speedometer readings, if any</div>
        <div class="form-row sub-item">
          <label>At the beginning of the trip</label>
          <input type="number" step="any" name="speed_start" id="speed_start" class="calc-input" value="<?= $val('speed_start') ?>" style="text-align: right;">
          <span class="unit-tag">Kms.</span>
          <span class="screen-only" id="speed_carry_hint" style="<?= $speedCarried ? '' : 'display:none;' ?>"
                title="Ending speedometer reading of this vehicle's previous trip">from previous trip</span>
        </div>
        <div class="form-row sub-item">
          <label>At the end of the trip</label>
          <input type="number" step="any" name="speed_end" id="speed_end" class="calc-input" value="<?= $val('speed_end') ?>" style="text-align: right;">
          <span class="unit-tag">Kms.</span>
        </div>
        <div class="form-row sub-item">
          <label>Distance travelled</label>
          <input type="number" step="any" id="speed_dist" class="calc-input bold-val" style="text-align: right;" readonly>
          <span class="unit-tag">Kms.</span>
        </div>

        <div class="form-row" style="margin-top: 2px;">
          <label>6. Remarks&nbsp;</label>
          <input type="text" name="remarks" value="<?= $val('remarks') ?>">
        </div>

        <div class="certify-text">I HEREBY CERTIFY to the correctness of the above statement of record of travel.</div>
      </div>

      <!-- Driver Signature Section B -->
      <div style="width: 200px; margin: 6px auto 0 auto; text-align: center;">
        <div class="sig-line" id="driver_sig_line"><?= e($driverSig) ?></div>
        <div class="sig-sub">Driver</div>
      </div>
    </div>

    <!-- RIGHT COLUMN: VEHICLE TRIP LOG -->
    <div class="right-col">
      <div>
        <div class="right-title">VEHICLE TRIP LOG</div>

        <table class="trip-log-table">
          <thead>
            <tr>
              <th rowspan="2" style="width: 14%;">DATE</th>
              <th colspan="2" style="width: 22%;">TIME OF</th>
              <th colspan="2" style="width: 38%;">PLACES TO BE VISITED</th>
              <th rowspan="2" style="width: 26%;">SIGNATURE OF PASSENGER<br>OVER PRINTED NAME</th>
            </tr>
            <tr>
              <th style="width: 11%;">DEPARTURE</th>
              <th style="width: 11%;">ARRIVAL</th>
              <th style="width: 19%;">FROM</th>
              <th style="width: 19%;">TO</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($logRows as $i => $row): ?>
            <tr>
              <td><input type="date" name="log[<?= $i ?>][date]" value="<?= e(parse_date_iso($row['date']) ?? '') ?>"></td>
              <td><input type="text" name="log[<?= $i ?>][depart]" class="time-input" placeholder="00:00 AM" value="<?= e($row['depart']) ?>"></td>
              <td><input type="text" name="log[<?= $i ?>][arrive]" class="time-input" placeholder="00:00 PM" value="<?= e($row['arrive']) ?>"></td>
              <td><input type="text" name="log[<?= $i ?>][from]" value="<?= e($row['from']) ?>"></td>
              <td><input type="text" name="log[<?= $i ?>][to]" value="<?= e($row['to']) ?>"></td>
              <td><input type="text" name="log[<?= $i ?>][passenger]" value="<?= e($row['passenger']) ?>"></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>

        <div class="attest-section">
          <div style="width: 48%;">ATTESTED BY:</div>
          <div style="width: 48%;">CERTIFIED CORRECT:</div>
        </div>

        <div class="approval-block" style="margin-top: 5px;">
          <div class="sig-box">
            <div class="sig-line"><?= e($s['attest_name']) ?></div>
            <div class="sig-sub"><?= line_breaks($s['attest_sub']) ?></div>
          </div>
          <div class="sig-box">
            <div class="sig-line" id="cert_sig_line"><?= e($certName) ?></div>
            <div class="sig-sub"><?= line_breaks($s['cert_sub']) ?></div>
          </div>
        </div>

        <div class="right-footer-cert">
          <b>I HEREBY CERTIFY</b> that I used the vehicle mentioned at the back hereof for official business as
        </div>
      </div>

      <div class="bottom-line-sig">
        Passanger/Driver
      </div>
    </div>

  </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
