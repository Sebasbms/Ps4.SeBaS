<?php
/**
 * ARCHIVO: api/scanner.php
 * Verifica que haya una PS4 (Puerto 2121 abierto sin KSWEB) en la IP dada.
 * (Motor Sockets Nativo - Optimizado)
 */
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

if (isset($_GET['ip'])) {
    $ip = $_GET['ip']; $port = 2121;
    
    if (strpos($ip, '127.') === 0) { echo json_encode(['status' => 'error']); exit; }
    
    // Timeout inicial subido a 1.0s para mayor tolerancia en redes móviles/Wi-Fi
    $fp = @fsockopen($ip, $port, $errno, $errstr, 1.0);
    if ($fp) {
        // Stream timeout subido a 1000000 microsegundos (1.0s)
        stream_set_timeout($fp, 1, 0); 
        $banner = fgets($fp, 256); 
        fclose($fp);
        
        if ($banner !== false && stripos($banner, 'KSWEB') === false && stripos($banner, 'bftpd') === false) {
            echo json_encode(['status' => 'success', 'ip' => $ip]); 
        } else { 
            echo json_encode(['status' => 'error']); 
        }
    } else { 
        echo json_encode(['status' => 'error']); 
    }
} else {
    echo json_encode(['status' => 'error']);
}
?>
