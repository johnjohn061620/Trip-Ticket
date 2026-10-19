<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';
require_admin();

$s = settings();
$logoUrl = !empty($s['logo_path']) ? logo_url($s['logo_path']) : 'assets/img/logo-placeholder.svg';

$fields = [
    'country', 'agency_name', 'office_name', 'address', 'doc_code', 'ticket_prefix',
    'driver_name', 'plate_no',
    'approver1_name', 'approver1_sub', 'approver2_name', 'approver2_sub',
    'attest_name', 'attest_sub', 'cert_name', 'cert_sub',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = (string) ($_POST['mode'] ?? 'save');

    if ($mode === 'remove_logo') {
        delete_logo_file($s['logo_path'] ?? null);
        db()->prepare('UPDATE settings SET logo_path = NULL WHERE id = 1')->execute();
        flash('Logo removed - placeholder restored.');
        redirect('settings.php');
    }

    if ($mode === 'add_vehicle') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $plate = trim((string) ($_POST['plate_no'] ?? ''));
        $rate = (float) ($_POST['km_per_liter'] ?? 0);
        if ($name === '' || $rate <= 0) {
            flash('Vehicle name and average km per liter are required.', 'err');
            redirect('settings.php');
        }
        try {
            db()->prepare('INSERT INTO vehicles (name, plate_no, km_per_liter) VALUES (?, ?, ?)')
                ->execute([$name, $plate, $rate]);
            flash('Vehicle added.');
        } catch (PDOException $ex) {
            flash('That vehicle already exists.', 'err');
        }
        redirect('settings.php');
    }

    if ($mode === 'delete_vehicle') {
        $vid = (int) ($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM vehicles WHERE id = ?')->execute([$vid]);
        db()->prepare('UPDATE settings SET default_vehicle_id = NULL WHERE default_vehicle_id = ?')->execute([$vid]);
        flash('Vehicle removed.');
        redirect('settings.php');
    }

    $data = [];
    foreach ($fields as $f) {
        if (!array_key_exists($f, $_POST)) {
            continue;
        }
        $data[$f] = trim((string) $_POST[$f]);
    }

    if (array_key_exists('default_vehicle_id', $_POST)) {
        $data['default_vehicle_id'] = ((int) $_POST['default_vehicle_id']) ?: null;
    }

    foreach (['agency_name', 'office_name', 'address', 'doc_code', 'ticket_prefix'] as $required) {
        if (($data[$required] ?? '') === '') {
            flash('Agency, office, address, document code and ticket prefix cannot be empty.', 'err');
            redirect('settings.php');
        }
    }

    $newPassword = trim((string) ($_POST['new_password'] ?? ''));
    if ($newPassword !== '') {
        if (strlen($newPassword) < 6) {
            flash('New password must be at least 6 characters.', 'err');
            redirect('settings.php');
        }
        $data['admin_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    }

    $logoError = null;
    if (!empty($_FILES['logo']['name'])) {
        try {
            $data['logo_path'] = store_logo($_FILES['logo']);
            delete_logo_file($s['logo_path'] ?? null);
        } catch (RuntimeException $ex) {
            $logoError = $ex->getMessage();
        }
    }

    if ($logoError) {
        flash('Settings saved, but logo upload failed: ' . e($logoError), 'err');
        redirect('settings.php');
    }

    if ($data) {
        $sets = [];
        $vals = [];
        foreach ($data as $col => $val) {
            $sets[] = "`$col` = ?";
            $vals[] = $val;
        }
        $vals[] = 1;
        db()->prepare('UPDATE settings SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    }

    flash('Settings saved.');
    redirect('settings.php');
}

$page = 'settings';
$page_title = 'Settings';
$vehicles = vehicle_list();
$rateNum = fn(float $r): string => rtrim(rtrim(number_format($r, 2, '.', ''), '0'), '.');
require __DIR__ . '/includes/header.php';
?>

<h1>Settings</h1>
<p class="muted">Logo, names and signatories used on every trip ticket.</p>

<form method="post" action="settings.php" enctype="multipart/form-data" class="card">
  <input type="hidden" name="mode" value="save">

  <h2>Logo</h2>
  <div class="logo-preview">
    <img src="<?= e($logoUrl) ?>" alt="Current logo" id="logoPreview">
    <div>
      <div class="field">
        <label for="logo">Upload new logo <span class="hint">(PNG, JPG, GIF, WEBP, SVG - max 3 MB)</span></label>
        <input type="file" id="logo" name="logo" accept=".png,.jpg,.jpeg,.gif,.webp,.svg,image/*">
      </div>
      <div class="muted small">Uploading a new logo replaces the current one. The placeholder is shown until a logo is uploaded.</div>
      <?php if (!empty($s['logo_path'])): ?>
        <button class="btn danger sm" type="submit" name="mode" value="remove_logo" style="margin-top:8px;"
                onclick="return confirm('Remove the uploaded logo and restore the placeholder?');">Remove uploaded logo</button>
      <?php endif; ?>
    </div>
  </div>

  <h2>Document &amp; Office</h2>
  <div class="grid-2">
    <div class="field">
      <label>Country</label>
      <input type="text" name="country" value="<?= e($s['country']) ?>">
    </div>
    <div class="field">
      <label>Document code <span class="hint">(top-right of the ticket)</span></label>
      <input type="text" name="doc_code" value="<?= e($s['doc_code']) ?>">
    </div>
    <div class="field">
      <label>Agency name</label>
      <input type="text" name="agency_name" value="<?= e($s['agency_name']) ?>">
    </div>
    <div class="field">
      <label>Office name</label>
      <input type="text" name="office_name" value="<?= e($s['office_name']) ?>">
    </div>
    <div class="field">
      <label>Office address</label>
      <input type="text" name="address" value="<?= e($s['address']) ?>">
    </div>
    <div class="field">
      <label>Ticket number prefix</label>
      <input type="text" name="ticket_prefix" value="<?= e($s['ticket_prefix']) ?>">
    </div>
  </div>

  <h2>Driver &amp; Vehicle Defaults</h2>
  <div class="grid-2">
    <div class="field">
      <label>Default driver name</label>
      <input type="text" name="driver_name" value="<?= e($s['driver_name']) ?>">
    </div>
    <div class="field">
      <label>Default plate / vehicle no.</label>
      <input type="text" name="plate_no" value="<?= e($s['plate_no']) ?>">
    </div>
    <div class="field">
      <label>Default vehicle <span class="hint">(highlighted in the vehicle chooser)</span></label>
      <select name="default_vehicle_id">
        <option value="">— no default —</option>
        <?php foreach ($vehicles as $vid => $v): ?>
          <option value="<?= $vid ?>"<?= $vid === (int) ($s['default_vehicle_id'] ?? 0) ? ' selected' : '' ?>>
            <?= e($v['name']) ?><?= $v['plate'] !== '' ? ' (' . e($v['plate']) . ')' : '' ?> - <?= e($rateNum($v['rate'])) ?> km/L
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <h2>Signatories</h2>
  <div class="grid-2">
    <div class="field">
      <label>Approved by - name</label>
      <input type="text" name="approver1_name" value="<?= e($s['approver1_name']) ?>">
    </div>
    <div class="field">
      <label>Approved by - designation <span class="hint">(use &lt;br&gt; for line break)</span></label>
      <input type="text" name="approver1_sub" value="<?= e($s['approver1_sub']) ?>">
    </div>
    <div class="field">
      <label>Second approver - name</label>
      <input type="text" name="approver2_name" value="<?= e($s['approver2_name']) ?>">
    </div>
    <div class="field">
      <label>Second approver - designation</label>
      <input type="text" name="approver2_sub" value="<?= e($s['approver2_sub']) ?>">
    </div>
    <div class="field">
      <label>Attested by - name</label>
      <input type="text" name="attest_name" value="<?= e($s['attest_name']) ?>">
    </div>
    <div class="field">
      <label>Attested by - designation</label>
      <input type="text" name="attest_sub" value="<?= e($s['attest_sub']) ?>">
    </div>
    <div class="field">
      <label>Certified correct - name</label>
      <input type="text" name="cert_name" value="<?= e($s['cert_name']) ?>">
    </div>
    <div class="field">
      <label>Certified correct - designation</label>
      <input type="text" name="cert_sub" value="<?= e($s['cert_sub']) ?>">
    </div>
  </div>

  <h2>Change Password</h2>
  <div class="grid-2">
    <div class="field">
      <label>New password <span class="hint">(leave blank to keep current - min 6 chars)</span></label>
      <input type="password" name="new_password" autocomplete="new-password" placeholder="Leave blank to keep">
    </div>
  </div>

  <button class="btn" type="submit">Save Settings</button>
</form>

<div class="card">
  <h2>Vehicles</h2>
  <p class="muted small">Each vehicle has its own average consumption - fuel deduct (d) on the ticket = distance &divide; km per liter.</p>
  <table class="list">
    <thead>
      <tr><th>Vehicle</th><th>Plate no.</th><th>km per liter</th><th></th></tr>
    </thead>
    <tbody>
      <?php if (!$vehicles): ?>
        <tr><td colspan="4" class="muted">No vehicles yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($vehicles as $vid => $v): ?>
        <tr>
          <td>
            <?= e($v['name']) ?>
            <?php if ($vid === (int) ($s['default_vehicle_id'] ?? 0)): ?>
              <span class="tag">default</span>
            <?php endif; ?>
          </td>
          <td><?= e($v['plate'] !== '' ? $v['plate'] : '—') ?></td>
          <td><?= e($rateNum($v['rate'])) ?></td>
          <td class="actions">
            <form method="post" action="settings.php" style="display:inline;">
              <input type="hidden" name="mode" value="delete_vehicle">
              <input type="hidden" name="id" value="<?= $vid ?>">
              <button class="btn danger sm" type="submit"
                      onclick="return confirm('Remove <?= e($v['name']) ?> from the vehicle list?');">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <form method="post" action="settings.php" class="grid-2" style="margin-top:12px; align-items:end;">
    <input type="hidden" name="mode" value="add_vehicle">
    <div class="field">
      <label>Vehicle name</label>
      <input type="text" name="name" placeholder="e.g. Montero" required>
    </div>
    <div class="field">
      <label>Plate number</label>
      <input type="text" name="plate_no" placeholder="e.g. 140106">
    </div>
    <div class="field">
      <label>Average km per liter</label>
      <input type="number" step="any" min="0.1" name="km_per_liter" placeholder="e.g. 12" required>
      <div class="hint">Pick up style vehicles use 8 km/L.</div>
    </div>
    <div>
      <button class="btn" type="submit">Add Vehicle</button>
    </div>
  </form>
</div>

<script>
document.getElementById('logo').addEventListener('change', function () {
  if (this.files && this.files[0]) {
    document.getElementById('logoPreview').src = URL.createObjectURL(this.files[0]);
  }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
