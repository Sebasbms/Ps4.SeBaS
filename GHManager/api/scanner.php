<?php
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

$ip = $_GET['ip'] ?? '';

if (empty($ip)) {
    echo json_encode(['status' => 'error']);
    exit;
}

// Usamos Sockets de red directos. Rápido y a prueba de balas.
$fp = @fsockopen($ip, 2121, $errno, $errstr, 0.5);
if ($fp) {
    fclose($fp);
    echo json_encode(['status' => 'success', 'ip' => $ip]);
} else {
    echo json_encode(['status' => 'error']);
}
?>
