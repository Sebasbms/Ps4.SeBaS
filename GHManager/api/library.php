<?php
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

$action = $_REQUEST['action'] ?? '';
$host = $_REQUEST['host_ip'] ?? '';
$port = 2121;
$cusa = $_REQUEST['cusa_id'] ?? '';

// Función auxiliar cURL para listar carpetas
function curl_ftp_list($ip, $port, $path) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$ip:$port" . rtrim($path, '/') . '/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "NLST");
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $res = curl_exec($ch);
    curl_close($ch);
    
    $items = [];
    if ($res !== false) {
        $lines = explode("\n", trim($res));
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && $line !== '.' && $line !== '..') {
                $items[] = basename($line);
            }
        }
    }
    return $items;
}

// 1. ESCANEAR LISTA DE JUEGOS EN PS4
if ($action === 'scan_list') {
    if (empty($host)) { echo json_encode(['status'=>'error', 'message'=>'Falta IP']); exit; }
    
    $juegos_ps4 = [];
    $rutas = ['/user/app/', '/mnt/ext0/user/app/', '/mnt/usb0/user/app/'];
    
    foreach ($rutas as $ruta) {
        $carpetas = curl_ftp_list($host, $port, $ruta);
        foreach ($carpetas as $carpeta) {
            // Si la carpeta tiene formato CUSA/PPSA/CUSAXXXXX
            if (preg_match('/^[A-Z]{4}\d{5}$/i', $carpeta)) {
                $juegos_ps4[] = strtoupper($carpeta);
            }
        }
    }
    
    $juegos_ps4 = array_values(array_unique($juegos_ps4));
    
    // Comparamos con la caché local
    $cache_dir = '../cache_biblioteca/';
    if (!file_exists($cache_dir)) @mkdir($cache_dir, 0777, true);
    
    $faltantes = [];
    foreach ($juegos_ps4 as $id) {
        if (!file_exists($cache_dir . $id . '.json')) {
            $faltantes[] = ['id' => $id];
        }
    }
    
    echo json_encode(['status' => 'success', 'missing' => $faltantes, 'total_ps4' => count($juegos_ps4)]);
    exit;
}

// 2. DESCARGAR DATOS Y PORTADA DE UN JUEGO (PARAM.SFO y ICON0.PNG)
if ($action === 'get_game_data') {
    if (empty($host) || empty($cusa)) { echo json_encode(['status'=>'error']); exit; }
    
    $cache_dir = '../cache_biblioteca/';
    if (!file_exists($cache_dir)) @mkdir($cache_dir, 0777, true);

    $sfo_path = "/user/app/meta/$cusa/param.sfo";
    $icon_path = "/user/app/meta/$cusa/icon0.png";
    
    // Extraer SFO con cURL en memoria
    $ch_sfo = curl_init();
    curl_setopt($ch_sfo, CURLOPT_URL, "ftp://$host:$port$sfo_path");
    curl_setopt($ch_sfo, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_sfo, CURLOPT_TIMEOUT, 5);
    $sfo_data = curl_exec($ch_sfo);
    curl_close($ch_sfo);
    
    $title = $cusa;
    $version = "1.00";
    $type = "game";
    
    if ($sfo_data) {
        // Parseo básico de SFO para sacar el TITLE
        $pos = strpos($sfo_data, 'TITLE');
        if ($pos !== false) {
            $title_section = substr($sfo_data, $pos + 5, 100);
            $clean_title = preg_replace('/[\x00-\x1F\x7F]/u', '', $title_section);
            if (!empty(trim($clean_title))) $title = trim($clean_title);
        }
    }
    
    // Descargar icono localmente con cURL
    $icon_local = $cache_dir . $cusa . '.png';
    $ch_icon = curl_init();
    curl_setopt($ch_icon, CURLOPT_URL, "ftp://$host:$port$icon_path");
    curl_setopt($ch_icon, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_icon, CURLOPT_TIMEOUT, 8);
    $img_data = curl_exec($ch_icon);
    curl_close($ch_icon);
    
    if ($img_data) {
        file_put_contents($icon_local, $img_data);
    }
    
    $meta = [
        'id' => $cusa,
        'title' => $title,
        'version' => $version,
        'type' => $type,
        'icon' => 'cache_biblioteca/' . $cusa . '.png'
    ];
    
    file_put_contents($cache_dir . $cusa . '.json', json_encode($meta));
    echo json_encode(['status' => 'success', 'data' => $meta]);
    exit;
}

// 3. OBTENER JUEGOS CACHEADOS (Para mostrar la cuadrícula rápido)
if ($action === 'get_cached_games') {
    $cache_dir = '../cache_biblioteca/';
    $games = [];
    if (is_dir($cache_dir)) {
        $files = scandir($cache_dir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $content = json_decode(file_get_contents($cache_dir . $file), true);
                if ($content) $games[] = $content;
            }
        }
    }
    echo json_encode(['status' => 'success', 'data' => $games]);
    exit;
}

// 4. ELIMINAR JUEGO (Ocultar)
if ($action === 'delete_game') {
    if (empty($cusa)) exit;
    $cache_dir = '../cache_biblioteca/';
    @unlink($cache_dir . $cusa . '.json');
    @unlink($cache_dir . $cusa . '.png');
    echo json_encode(['status' => 'success']);
    exit;
}

// 5. LIMPIAR CACHÉ TEMP
if ($action === 'clear_temp') {
    $temp_dirs = ['../rpi_cache/', '../cache_biblioteca/'];
    foreach ($temp_dirs as $dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) @unlink($file);
            }
        }
    }
    echo json_encode(['status' => 'success']);
    exit;
}
?>
