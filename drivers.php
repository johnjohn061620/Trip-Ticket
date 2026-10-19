<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = (string) ($_POST['mode'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($mode === 'add' || $mode === 'update') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $fullName = strtoupper(trim((string) ($_POST['full_name'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        if ($username === '' || $fullName === '') {
            flash('Username and full name are required.', 'err');
            redirect('drivers.php');
        }

        if ($mode === 'add' && strlen($password) < 6) {
            flash('Password must be at least 6 characters.', 'err');
            redirect('drivers.php');
        }
        if ($password !== '' && strlen($password) < 6) {
            flash('Password must be at least 6 characters.', 'err');
            redirect('drivers.php');
        }

        try {
            if ($mode === 'add') {
                db()->prepare(
                    'INSERT INTO drivers (username, password_hash, full_name, active) VALUES (?, ?, ?, ?)'
                )->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullName, $active]);
                flash('Driver registered.');
            } elseif ($password !== '') {
                db()->prepare(
                    'UPDATE drivers SET username = ?, password_hash = ?, full_name = ?, active = ? WHERE id = ?'
                )->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullName, $active, $id]);
                flash('Driver updated.');
            } else {
                db()->prepare(
                    'UPDATE drivers SET username = ?, full_name = ?, active = ? WHERE id = ?'
                )->execute([$username, $fullName, $active, $id]);
                flash('Driver updated.');
            }
        } catch (PDOException $ex) {
            flash('That username is already taken.', 'err');
        }
        redirect('drivers.php');
    }

    if ($mode === 'delete') {
        db()->prepare('DELETE FROM drivers WHERE id = ?')->execute([$id]);
        flash('Driver removed.');
        redirect('drivers.php');
    }

    if ($mode === 'toggle') {
        db()->prepare('UPDATE drivers SET active = 1 - active WHERE id = ?')->execute([$id]);
        flash('Driver status changed.');
        redirect('drivers.php');
    }
}

$drivers = db()->query('SELECT * FROM drivers ORDER BY full_name')->fetchAll();

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM drivers WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

$page = 'drivers';
$page_title = 'Drivers';
require __DIR__ . '/includes/header.php';
?>

<h1>Driver Accounts</h1>
<p class="muted">Register drivers here, then pick them from the driver list when creating a trip ticket.</p>

<?php if ($editing): ?>
  <form method="post" action="drivers.php" class="card">
    <h2>Edit Driver</h2>
    <input type="hidden" name="mode" value="update">
    <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
    <div class="grid-2">
      <div class="field">
        <label>Username</label>
        <input type="text" name="username" value="<?= e($editing['username']) ?>" required>
      </div>
      <div class="field">
        <label>Full name</label>
        <input type="text" name="full_name" value="<?= e($editing['full_name']) ?>" required>
      </div>
      <div class="field">
        <label>New password <span class="hint">(leave blank to keep current - min 6 chars)</span></label>
        <input type="password" name="password" autocomplete="new-password" placeholder="Leave blank to keep">
      </div>
      <div class="field">
        <label>Status</label>
        <label style="font-weight:normal; display:flex; gap:6px; align-items:center; margin-top:6px;">
          <input type="checkbox" name="active" value="1" style="width:auto;" <?= (int) $editing['active'] ? 'checked' : '' ?>>
          Active (shows in the ticket driver dropdown)
        </label>
      </div>
    </div>
    <button class="btn" type="submit">Save Driver</button>
    <a class="btn ghost" href="drivers.php">Cancel</a>
  </form>
<?php else: ?>
  <form method="post" action="drivers.php" class="card">
    <h2>Register Driver</h2>
    <input type="hidden" name="mode" value="add">
    <div class="grid-2">
      <div class="field">
        <label>Username</label>
        <input type="text" name="username" required>
      </div>
      <div class="field">
        <label>Full name</label>
        <input type="text" name="full_name" placeholder="e.g. JUAN DELA CRUZ" required>
      </div>
      <div class="field">
        <label>Password <span class="hint">(min 6 chars)</span></label>
        <input type="password" name="password" autocomplete="new-password" required>
      </div>
      <div class="field">
        <label>Status</label>
        <label style="font-weight:normal; display:flex; gap:6px; align-items:center; margin-top:6px;">
          <input type="checkbox" name="active" value="1" style="width:auto;" checked>
          Active (shows in the ticket driver dropdown)
        </label>
      </div>
    </div>
    <button class="btn" type="submit">Register Driver</button>
  </form>
<?php endif; ?>

<div class="card">
  <h2>Registered Drivers</h2>
  <table class="list">
    <thead>
      <tr><th>Full name</th><th>Username</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
      <?php if (!$drivers): ?>
        <tr><td colspan="4" class="muted">No drivers registered yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($drivers as $d): ?>
        <tr>
          <td><?= e($d['full_name']) ?></td>
          <td><?= e($d['username']) ?></td>
          <td>
            <?= (int) $d['active'] ? '<span class="tag">active</span>' : '<span class="tag">inactive</span>' ?>
          </td>
          <td class="actions">
            <a class="btn ghost sm" href="drivers.php?edit=<?= (int) $d['id'] ?>">Edit</a>
            <form method="post" action="drivers.php" style="display:inline;">
              <input type="hidden" name="mode" value="toggle">
              <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
              <button class="btn ghost sm" type="submit"><?= (int) $d['active'] ? 'Deactivate' : 'Activate' ?></button>
            </form>
            <form method="post" action="drivers.php" style="display:inline;">
              <input type="hidden" name="mode" value="delete">
              <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
              <button class="btn danger sm" type="submit"
                      onclick="return confirm('Delete this driver account?');">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
