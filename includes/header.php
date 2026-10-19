<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/db.php';
$page = $page ?? '';
$settings = settings();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title ?? 'Trip Ticket System') ?></title>
  <link rel="stylesheet" href="assets/css/app.css">
<?php foreach (($page_css ?? []) as $css): ?>
  <link rel="stylesheet" href="<?= e($css) ?>">
<?php endforeach; ?>
</head>
<body>
<div class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="index.php">
      <?= logo_html($settings, 26) ?>
      <span>Trip Ticket System</span>
    </a>
    <nav>
      <a href="index.php" class="<?= $page === 'home' ? 'active' : '' ?>">Tickets</a>
      <a href="vehicle_pick.php" class="<?= $page === 'pick' || $page === 'ticket' ? 'active' : '' ?>">New Ticket</a>
      <?php if (is_driver()): ?>
        <span class="nav-user">Driver: <?= e(driver_session_name()) ?></span>
      <?php else: ?>
        <a href="drivers.php" class="<?= $page === 'drivers' ? 'active' : '' ?>">Drivers</a>
        <a href="settings.php" class="<?= $page === 'settings' ? 'active' : '' ?>">Settings</a>
      <?php endif; ?>
      <a href="actions/logout.php">Log out</a>
    </nav>
  </div>
</div>
<main class="wrap">
<?php if ($f = take_flash()): ?>
  <div class="alert <?= e($f['type']) ?>"><?= $f['msg'] ?></div>
<?php endif; ?>
