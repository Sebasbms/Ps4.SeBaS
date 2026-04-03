<?php
/**
 * ARCHIVO: api/scanner.php
 * Radar de red - Basado en la lógica original de SeBaS optimizada para Termux
 */
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

if (isset($_GET['ip'])) {
    $ip = $_GET['ip']; 
    $port = 2121;
    
    // Evitar que el radar se confunda escaneando el propio celular (localhost)
    if (strpos($ip, '127.') === 0 || $ip === '0.0.0.0') { 
        echo json_encode(['status' => 'error']); 
        exit; 
    }
    
    // REDUCCIÓN CLAVE: 0.5s en lugar de 1.0s para no embotellar los 5 carriles de Termux
    $timeout_segundos = 0.5; 
    
    $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout_segundos);
    if ($fp) {
        // Stream timeout ajustado a 500,000 microsegundos (0.5s)
        stream_set_timeout($fp, 0, 500000); 
        $banner = fgets($fp, 256); 
        fclose($fp);
        
        // Tu excelente lógica para descartar falsos positivos
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
