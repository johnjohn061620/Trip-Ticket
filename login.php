<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';

$error = '';
$as = 'admin';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $as = ($_POST['login_as'] ?? 'admin') === 'driver' ? 'driver' : 'admin';

    if ($as === 'driver') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $stmt = db()->prepare('SELECT * FROM drivers WHERE username = ? AND active = 1');
        $stmt->execute([$username]);
        $d = $stmt->fetch();
        if ($d && $password !== '' && password_verify($password, $d['password_hash'])) {
            session_regenerate_id(true);
            unset($_SESSION['admin']);
            $_SESSION['driver_id'] = (int) $d['id'];
            $_SESSION['driver_name'] = (string) $d['full_name'];
            redirect('vehicle_pick.php');
        }
        $error = 'Incorrect driver username or password.';
        usleep(300000);
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $hash = (string) (settings()['admin_hash'] ?? '');
        if ($hash !== '' && password_verify($password, $hash)) {
            session_regenerate_id(true);
            unset($_SESSION['driver_id'], $_SESSION['driver_name']);
            $_SESSION['admin'] = true;
            redirect('index.php');
        }
        $error = 'Incorrect password.';
        usleep(300000);
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - Trip Ticket System</title>
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="login-page">
  <form class="login-card" method="post" action="login.php">
    <div class="login-logo"><?= logo_html(settings(), 56) ?></div>
    <h1>Trip Ticket System</h1>
    <p class="muted">Sign in to continue</p>
    <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>

    <div class="role-switch">
      <label class="<?= $as === 'admin' ? 'on' : '' ?>">
        <input type="radio" name="login_as" value="admin" <?= $as === 'admin' ? 'checked' : '' ?>> Administrator
      </label>
      <label class="<?= $as === 'driver' ? 'on' : '' ?>">
        <input type="radio" name="login_as" value="driver" <?= $as === 'driver' ? 'checked' : '' ?>> Driver
      </label>
    </div>

    <div id="driver_user_field" style="<?= $as === 'driver' ? '' : 'display:none;' ?>">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" autocomplete="username" <?= $as === 'driver' ? 'autofocus' : '' ?>>
    </div>

    <label for="password">Password</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required
           <?= $as === 'admin' ? 'autofocus' : '' ?>>
    <button type="submit" class="btn">Sign in</button>
  </form>

  <script>
  (function () {
    var field = document.getElementById('driver_user_field');
    var labels = document.querySelectorAll('.role-switch label');
    for (var i = 0; i < labels.length; i++) {
      labels[i].addEventListener('click', function () {
        for (var j = 0; j < labels.length; j++) labels[j].classList.remove('on');
        this.classList.add('on');
        var as = this.querySelector('input').value;
        field.style.display = as === 'driver' ? '' : 'none';
      });
    }
  })();
  </script>
</body>
</html>
