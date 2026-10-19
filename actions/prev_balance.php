<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';
require_login();

header('Content-Type: application/json');

$vehicle = trim((string) ($_GET['vehicle'] ?? ''));
$balance = previous_end_balance(null, $vehicle !== '' ? $vehicle : null);

echo json_encode(['vehicle' => $vehicle, 'gas_a' => $balance]);
