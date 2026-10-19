<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';
require_login();

$s = settings();
$vehicles = vehicle_list();
$defaultId = (int) ($s['default_vehicle_id'] ?? 0);
$driverName = driver_session_name();

$page = 'pick';
$page_title = 'Choose Vehicle';
$page_css = ['assets/css/pick.css'];
require __DIR__ . '/includes/header.php';
?>

<div class="pick-wrap">
  <?php if (is_driver()): ?>
    <h1>Hello, <?= e($driverName) ?></h1>
    <p class="muted">What will you use for this trip?</p>
  <?php else: ?>
    <h1>New Trip Ticket</h1>
    <p class="muted">Which vehicle will be used for this trip? The plate number is filled in automatically.</p>
  <?php endif; ?>

  <div class="pick-grid">
    <?php foreach ($vehicles as $vid => $v): if ($v['name'] === '') continue; ?>
      <a class="pick-card" href="ticket.php?vehicle=<?= rawurlencode($v['name']) ?>">
        <?php if ($vid === $defaultId): ?><span class="pick-badge">default</span><?php endif; ?>
        <span class="pick-name"><?= e($v['name']) ?></span>
        <span class="pick-plate"><?= e($v['plate'] !== '' ? $v['plate'] : 'no plate set') ?></span>
        <span class="pick-rate"><?= e(rtrim(rtrim(number_format($v['rate'], 2, '.', ''), '0'), '.')) ?> km/L</span>
      </a>
    <?php endforeach; ?>
    <?php if (!$vehicles): ?>
      <p>No vehicles are set up yet.<?= is_admin() ? ' Add vehicles in Settings.' : ' Ask the administrator to add vehicles in Settings.' ?></p>
    <?php endif; ?>
  </div>

  <a class="pick-cancel" href="index.php">&larr; Back to ticket list</a>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
