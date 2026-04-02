<?php
/**
 * ARCHIVO: api/ps4_screenshots.php
 * Buscador de Capturas: Soporta Bóveda Global, Contador y Streaming (Motor cURL Moderno).
 */
error_reporting(0);
$action = $_REQUEST['action'] ?? '';
$host_ip = $_REQUEST['host_ip'] ?? '';
$cusa = $_REQUEST['cusa_id'] ?? '';
$port = 2121;

// 1. MODO STREAMING: Muestra la foto directo en el celular usando cURL
if ($action === 'stream') {
    $path = $_GET['path'] ?? '';
    if (!$host_ip || !$path) exit;
    
    set_time_limit(0); 
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($ext === 'png' ? 'image/png' : 'image/jpeg'));
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$host_ip:$port$path");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // Imprime directo a la pantalla
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_exec($ch);
    curl_close($ch);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
set_time_limit(60); 

if (empty($host_ip)) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos de conexión.']);
    exit;
}

// Función Helper para leer carpetas por FTP usando cURL
function curl_ftp_list($ip, $port, $path) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$ip:$port" . rtrim($path, '/') . '/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "LIST");
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $res = curl_exec($ch);
    curl_close($ch);
    
    $items = [];
    if ($res !== false) {
        $lines = explode("\n", trim($res));
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $parts = preg_split('/\s+/', trim($line), 9);
            if (count($parts) >= 9) {
                $name = $parts[8];
                if ($name === '.' || $name === '..') continue;
                $is_dir = (substr($parts[0], 0, 1) === 'd');
                $items[] = ['name' => $name, 'is_dir' => $is_dir];
            }
        }
    }
    return $items;
}

$capturas = [];
$base_photo = "/user/av_contents/photo";

// Función Taladro (Reescrita con cURL)
function excavar_fotos($ip, $port, $dir, &$capturas, $profundidad = 0) {
    if ($profundidad > 5) return; 
    
    $items = curl_ftp_list($ip, $port, $dir);
    foreach ($items as $item) {
        $ruta_completa = rtrim($dir, '/') . '/' . $item['name'];
        if ($item['is_dir']) {
            excavar_fotos($ip, $port, $ruta_completa, $capturas, $profundidad + 1);
        } else {
            $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $capturas[] = $ruta_completa;
            }
        }
    }
}

// 2. MODO BÓVEDA GLOBAL (Escanea TODO sin importar el CUSA)
if ($action === 'scan_all') {
    $items_nivel1 = curl_ftp_list($host_ip, $port, $base_photo);
    foreach ($items_nivel1 as $item1) {
        if ($item1['is_dir']) {
            excavar_fotos($host_ip, $port, "$base_photo/" . $item1['name'], $capturas);
        }
    }
    
    if (empty($capturas)) {
        echo json_encode(['status' => 'error', 'message' => "No se encontraron fotos en la consola."]);
    } else {
        sort($capturas);
        echo json_encode(['status' => 'success', 'data' => array_reverse($capturas)]);
    }
    exit;
}

// 3. MODO ESPECÍFICO DE JUEGO (O CONTADOR)
if (empty($cusa)) {
    echo json_encode(['status' => 'error', 'message' => 'Falta el ID del juego.']);
    exit;
}

$rutas_cusa = [];
$items_nivel1 = curl_ftp_list($host_ip, $port, $base_photo);

foreach ($items_nivel1 as $item1) {
    if ($item1['is_dir']) {
        $ruta1 = "$base_photo/" . $item1['name'];
        if (stripos($item1['name'], $cusa) !== false) {
            $rutas_cusa[] = $ruta1; 
        } else {
            // Buscar una carpeta más adentro
            $items_nivel2 = curl_ftp_list($host_ip, $port, $ruta1);
            foreach ($items_nivel2 as $item2) {
                if ($item2['is_dir'] && stripos($item2['name'], $cusa) !== false) {
                    $rutas_cusa[] = "$ruta1/" . $item2['name'];
                }
            }
        }
    }
}

foreach ($rutas_cusa as $ruta) {
    excavar_fotos($host_ip, $port, $ruta, $capturas);
}

// MODO SOLO CONTADOR (Para la burbuja (badge) en el menú de opciones)
if ($action === 'count_only') {
    echo json_encode(['status' => 'success', 'count' => count($capturas)]);
    exit;
}

// MODO LISTAR NORMAL
if (empty($capturas)) {
    echo json_encode(['status' => 'error', 'message' => "No se encontraron fotos del juego.<br><br><span class='text-[10px]'>Intenta abrir el juego y sacar una captura nueva.</span>"]);
} else {
    sort($capturas);
    echo json_encode(['status' => 'success', 'data' => array_reverse($capturas)]);
}
?>
