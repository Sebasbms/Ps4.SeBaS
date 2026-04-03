<?php
/**
 * ====================================================================
 * GOLDHEN MANAGER V2.1 馃殌 (PS5/PS4)
 * DEVELOPED *By SeBaS* * ====================================================================
 */
error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('memory_limit', '512M'); 

header('Content-Type: text/html; charset=utf-8');
$firma = chr(83).chr(101).chr(66).chr(97).chr(83); 
header('X-Author: ' . $firma);

// 1. AUTO-CREACION PWA E ICONO
$manifest_content = '{
  "name": "GoldHen Manager", "short_name": "GoldHen Manager",
  "start_url": "./index.php", "display": "standalone", "background_color": "#0b0c10",
  "theme_color": "#0b0c10", "orientation": "portrait-primary",
  "icons": [{"src": "icon-512.png?v=7", "sizes": "512x512", "type": "image/png", "purpose": "any"}]
}';
if (!file_exists('manifest.json')) { @file_put_contents('manifest.json', $manifest_content); }

$sw_content = "self.addEventListener('install', (e) => self.skipWaiting());\nself.addEventListener('activate', (e) => self.clients.claim());\nself.addEventListener('fetch', (e) => e.respondWith(fetch(e.request)));";
if (!file_exists('sw.js')) { @file_put_contents('sw.js', $sw_content); }

if (!file_exists('icon-512.png')) {
    $u = chr(69).chr(108).chr(78).chr(111).chr(78).chr(111).chr(50).chr(54);
    $r = chr(80).chr(115).chr(52).chr(45).chr(83).chr(101).chr(66).chr(97).chr(83);
    $icon_url = 'https://raw.githubusercontent.com/'.$u.'/'.$r.'/main/icon-512.png';
    if (ini_get('allow_url_fopen')) { @file_put_contents('icon-512.png', file_get_contents($icon_url)); }
}

// 2. AUTO-DESCUBRIMIENTO DE CARPETAS
$directorios = ['iconos', 'backup_icons', 'payloads', 'cache_biblioteca', 'servidor_rpi', 'rpi_cache'];
foreach ($directorios as $dir) {
    if (!file_exists($dir)) { @mkdir($dir, 0777, true); }
    if (!file_exists($dir . '/.nomedia')) { @file_put_contents($dir . '/.nomedia', ''); }
}

// 2.5 AUTO-DETECTAR MICROSD Y CREAR T脷NEL
$storage_dir = '/storage/';
$microsd_link = __DIR__ . '/microsd';
if (is_dir($storage_dir)) {
    $carpetas = scandir($storage_dir);
    $sd_encontrada = false;
    foreach ($carpetas as $carpeta) {
        if (preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $carpeta)) {
            $ruta_real = $storage_dir . $carpeta;
            if (!is_link($microsd_link) || readlink($microsd_link) !== $ruta_real) {
                @unlink($microsd_link);
                @symlink($ruta_real, $microsd_link);
            }
            $sd_encontrada = true;
            break;
        }
    }
    if (!$sd_encontrada) { @unlink($microsd_link); }
}

// 3. DETECCI脫N DE IP LOCAL
function getLocalIP() {
    if (isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] !== '127.0.0.1' && $_SERVER['SERVER_ADDR'] !== '::1' && $_SERVER['SERVER_ADDR'] !== '0.0.0.0') {
        return $_SERVER['SERVER_ADDR'];
    }
    $ip = '127.0.0.1';
    exec("ip -4 addr show wlan0 2>/dev/null", $out_ip);
    if (!empty($out_ip)) {
        foreach ($out_ip as $line) {
            if (preg_match('/inet\s+([0-9\.]+)\//i', $line, $matches)) return $matches[1]; 
        }
    }
    return $ip;
}

$ip_servidor = getLocalIP();
$subred_partes = explode('.', $ip_servidor);
$subred_actual = isset($subred_partes[0]) ? $subred_partes[0] . '.' . $subred_partes[1] . '.' . $subred_partes[2] . '.' : '192.168.0.';

// 4. PROXY RPI SENDER
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (isset($_GET['rpi_proxy']) || (isset($data['ip']) && isset($data['url_pkg']))) {
    header('Content-Type: application/json');
    $ps4_ip = isset($data['ip']) ? $data['ip'] : '';
    $url_pkg = isset($data['url_pkg']) ? $data['url_pkg'] : '';
    
    if (empty($ps4_ip) || empty($url_pkg)) { echo json_encode(['status' => 'fail', 'message' => 'Faltan datos IP o URL']); exit; }
    
    $payload = json_encode(["type" => "direct", "packages" => [$url_pkg]]);
    $ch = curl_init('http://' . $ps4_ip . ':12800/api/install');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($payload)]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $logs = ["Link: $url_pkg", "P12800 -> HTTP:$httpcode | Err:$error"];
    if ($httpcode >= 200 && $httpcode < 300 && strpos($response, 'success') !== false) {
        echo json_encode(['status' => 'success', 'message' => 'Orden enviada a PS4', 'logs' => $logs]);
    } else {
        echo json_encode(['status' => 'fail', 'message' => 'El RPI rechaz贸 la conexi贸n', 'logs' => $logs]);
    }
    exit;
}

// 5. OBTENER LISTA DE JUEGOS RPI
if (isset($_GET['get_rpi_list'])) {
    header('Content-Type: application/json');
    $rutas_buscar = [
        ['dir' => __DIR__ . '/servidor_rpi', 'url' => 'servidor_rpi', 'tipo' => 'Interna'],
        ['dir' => __DIR__ . '/microsd/pkgs_rpi', 'url' => 'microsd/pkgs_rpi', 'tipo' => 'MicroSD']
    ];
    
    $pkgs = [];
    foreach ($rutas_buscar as $ruta) {
        if (is_dir($ruta['dir'])) {
            $files = scandir($ruta['dir']);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'pkg') {
                    $path = $ruta['dir'] . '/' . $file;
                    $size = filesize($path);
                    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
                    $bytes = max($size, 0); 
                    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
                    $pow = min($pow, count($units) - 1); 
                    $bytes /= pow(1024, $pow);
                    $size_fmt = round($bytes, 2) . ' ' . $units[$pow];
                    $pkgs[] = [
                        'nombre' => $file, 
                        'path' => $ruta['url'] . '/' . rawurlencode($file), 
                        'size' => $size, 
                        'size_fmt' => $size_fmt,
                        'origen' => $ruta['tipo']
                    ];
                }
            }
        }
    }
    echo json_encode(['status' => 'success', 'data' => $pkgs]);
    exit;
}

// 6. EXTRACTOR BINARIO SECUENCIAL PKG
if (isset($_GET['extract_pkg'])) {
    header('Content-Type: application/json');
    $file = basename(rawurldecode($_GET['extract_pkg']));
    
    $path_interna = __DIR__ . '/servidor_rpi/' . $file;
    $path_sd = __DIR__ . '/microsd/pkgs_rpi/' . $file;
    $path = file_exists($path_sd) ? $path_sd : $path_interna;

    $cache_img = __DIR__ . '/rpi_cache/' . $file . '.png';
    $cache_json = __DIR__ . '/rpi_cache/' . $file . '.json';

    if (file_exists($cache_json)) { echo file_get_contents($cache_json); exit; }

    $title_fallback = trim(preg_replace('/(_|-|\.pkg)/i', ' ', $file));
    $meta = ['title' => $title_fallback, 'cusa' => 'UNKNOWN', 'icon' => ''];
    
    if (file_exists($path)) {
        $fp = fopen($path, 'rb');
        if ($fp) {
            $buffer = fread($fp, 8 * 1024 * 1024);
            fclose($fp);
            
            $sfo_pos = strpos($buffer, "\0PSF");
            if ($sfo_pos !== false) {
                $sfo_data = substr($buffer, $sfo_pos, 65536);
                $magic = substr($sfo_data, 0, 4);
                if ($magic === "\0PSF") {
                    $key_table_offset = unpack('V', substr($sfo_data, 8, 4))[1];
                    $data_table_offset = unpack('V', substr($sfo_data, 12, 4))[1];
                    $entries = unpack('V', substr($sfo_data, 16, 4))[1];
                    
                    for ($i = 0; $i < $entries; $i++) {
                        $entry_offset = 20 + ($i * 16);
                        if ($entry_offset + 16 > strlen($sfo_data)) break;
                        $key_offset = unpack('v', substr($sfo_data, $entry_offset, 2))[1];
                        $data_len = unpack('V', substr($sfo_data, $entry_offset + 4, 4))[1];
                        $data_offset = unpack('V', substr($sfo_data, $entry_offset + 12, 4))[1];
                        
                        $key = '';
                        $pos = $key_table_offset + $key_offset;
                        while ($pos < strlen($sfo_data) && $sfo_data[$pos] !== "\0") {
                            $key .= $sfo_data[$pos];
                            $pos++;
                        }
                        
                        if ($key === 'TITLE_ID') {
                            $val = substr($sfo_data, $data_table_offset + $data_offset, $data_len);
                            $meta['cusa'] = trim(str_replace("\0", '', $val));
                        }
                        if ($key === 'TITLE') {
                            $val = substr($sfo_data, $data_table_offset + $data_offset, $data_len);
                            $val_clean = trim(str_replace("\0", '', $val));
                            if (!empty($val_clean)) {
                                $meta['title'] = preg_replace('/[\x00-\x1F\x7F]/u', '', $val_clean);
                            }
                        }
                    }
                }
            }
            
            $png_start = strpos($buffer, "\x89PNG\r\n\x1a\n");
            if ($png_start !== false) {
                $png_end = strpos($buffer, "IEND\xae\x42\x60\x82", $png_start);
                if ($png_end !== false) {
                    $png_end += 8; 
                    $png_data = substr($buffer, $png_start, $png_end - $png_start);
                    file_put_contents($cache_img, $png_data);
                    $meta['icon'] = 'rpi_cache/' . $file . '.png';
                }
            }
        }
    }
    
    $json_output = json_encode(['status' => 'success', 'data' => $meta]);
    file_put_contents($cache_json, $json_output); 
    echo $json_output;
    exit;
}

// 7. ACTUALIZACIÓN OTA (VÍA GIT) - VERSIÓN SEGURA Y EN ESPAÑOL
if (isset($_GET['ota_update'])) {
    header('Content-Type: application/json; charset=utf-8');
    $dir = __DIR__;
    
    $output = shell_exec("cd $dir && git reset --hard 2>&1 && git pull 2>&1");
    
    if (strpos($output, 'Already up to date') !== false || strpos($output, 'actualizado') !== false || strpos($output, 'uptodate') !== false) {
        echo json_encode(['status' => 'uptodate', 'message' => 'Ya tienes la última versión instalada.', 'log' => $output], JSON_UNESCAPED_UNICODE);
    } 
    else if (strpos($output, 'Fast-forward') !== false || strpos($output, 'Updating') !== false || strpos($output, 'changed') !== false || strpos($output, 'cambiados') !== false) {
        echo json_encode(['status' => 'updated', 'message' => '¡Actualización aplicada con éxito! Recargando...', 'log' => $output], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar. Usa Termux.', 'log' => $output], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
?>























<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>GoldHen Manager V2.1</title>
    
    <link rel="manifest" href="manifest.json?v=7">
    <meta name="theme-color" content="#05050a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="icon-512.png?v=7">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800;900&display=swap" rel="stylesheet">
    
    <style>
        :root { 
            --ps5-bg: #05050a; 
            --glass-bg: rgba(10, 15, 30, 0.4); 
            --glass-border: rgba(34, 211, 238, 0.15); 
            --neon-cyan: #22d3ee; 
            --neon-purple: #a855f7; 
            --bg-blur: 0px;
            --bg-opacity: 0.8;
            --theme-prim: #22d3ee;
            --theme-sec: #a855f7;
            --theme-bg: #05050a;
            --text-main: #ffffff;
            --text-muted: rgba(255,255,255,0.5);
        }
        
        body[data-theme="ps5"] { --theme-prim: #3b82f6; --theme-sec: #ffffff; --theme-bg: #0a0b12; }
        body[data-theme="spiderman"] { --theme-prim: #ef4444; --theme-sec: #3b82f6; --theme-bg: #140505; }
        body[data-theme="batman"] { --theme-prim: #eab308; --theme-sec: #6b7280; --theme-bg: #080808; }
        body[data-theme="gow"] { --theme-prim: #dc2626; --theme-sec: #ca8a04; --theme-bg: #120505; }
        body[data-theme="matrix"] { --theme-prim: #22c55e; --theme-sec: #14532d; --theme-bg: #050a05; }
        body[data-theme="vicecity"] { --theme-prim: #ec4899; --theme-sec: #06b6d4; --theme-bg: #140a17; }
        body[data-theme="bloodborne"] { --theme-prim: #991b1b; --theme-sec: #9ca3af; --theme-bg: #0f1115; }
        body[data-theme="hollowknight"] { --theme-prim: #e2e8f0; --theme-sec: #4c1d95; --theme-bg: #0f172a; }
        body[data-theme="got"] { --theme-prim: #f87171; --theme-sec: #f3f4f6; --theme-bg: #111111; }
        body[data-theme="evangelion"] { --theme-prim: #a855f7; --theme-sec: #4ade80; --theme-bg: #120518; }
        
        body[data-theme="neumorphism-light"] { 
            --theme-prim: #3182ce; --theme-sec: #2b6cb0; --theme-bg: #e0e5ec; 
            --text-main: #2d3748; --text-muted: #718096; 
            --neu-surface: #e0e5ec; --neu-border: #e0e5ec;
            --neu-shadow: 7px 7px 14px #bec2c8, -7px -7px 14px #ffffff; 
            --neu-shadow-inset: inset 5px 5px 10px #bec2c8, inset -5px -5px 10px #ffffff; 
        }
        body[data-theme="neumorphism-dark"] { 
            --theme-prim: #3b82f6; --theme-sec: #2563eb; --theme-bg: #222428; 
            --text-main: #e2e8f0; --text-muted: #a0aec0; 
            --neu-surface: #222428; --neu-border: #222428;
            --neu-shadow: 6px 6px 12px #18191c, -6px -6px 12px #2c2f34; 
            --neu-shadow-inset: inset 5px 5px 10px #18191c, inset -5px -5px 10px #2c2f34; 
        }
        body[data-theme="glass"] {
            --theme-prim: #38bdf8; --theme-sec: #818cf8; --theme-bg: #0f172a;
            --text-main: #ffffff; --text-muted: rgba(255,255,255,0.7);
        }

        body[data-theme^="neumorphism"] { background-color: var(--theme-bg); color: var(--text-main); }
        body[data-theme^="neumorphism"] .glass-panel, 
        body[data-theme^="neumorphism"] .dock-nav, 
        body[data-theme^="neumorphism"] .bottom-sheet,
        body[data-theme^="neumorphism"] .app-bg-overlay {
            background: var(--neu-surface) !important; backdrop-filter: none !important; -webkit-backdrop-filter: none !important; border: 1px solid var(--neu-border) !important; box-shadow: var(--neu-shadow) !important; opacity: 1 !important;
        }
        body[data-theme^="neumorphism"] .bg-black\/60, body[data-theme^="neumorphism"] .bg-black\/50, body[data-theme^="neumorphism"] .bg-black\/40, body[data-theme^="neumorphism"] .bg-white\/5, body[data-theme^="neumorphism"] .bg-white\/10 { background: var(--neu-surface) !important; box-shadow: var(--neu-shadow) !important; border: 1px solid var(--neu-border) !important; backdrop-filter: none !important; color: var(--text-main); }
        body[data-theme^="neumorphism"] input, body[data-theme^="neumorphism"] .shadow-inner, body[data-theme^="neumorphism"] .game-card { background: var(--neu-surface) !important; box-shadow: var(--neu-shadow-inset) !important; border: none !important; }
        body[data-theme^="neumorphism"] .text-white { color: var(--text-main) !important; }
        body[data-theme^="neumorphism"] .text-white\/80, body[data-theme^="neumorphism"] .text-white\/70, body[data-theme^="neumorphism"] .text-white\/60, body[data-theme^="neumorphism"] .text-white\/50, body[data-theme^="neumorphism"] .text-white\/40, body[data-theme^="neumorphism"] .text-white\/30 { color: var(--text-muted) !important; }
        body[data-theme^="neumorphism"] .dock-item { color: var(--text-muted); }
        body[data-theme^="neumorphism"] .dock-item.active { box-shadow: var(--neu-shadow-inset) !important; color: var(--theme-prim) !important; background: transparent !important; }
        body[data-theme^="neumorphism"] .btn-ps5-primary { box-shadow: var(--neu-shadow) !important; color: #fff !important; text-shadow: none !important;}
        body[data-theme^="neumorphism"] #custom-bg-layer { display: none !important; } 
        body[data-theme^="neumorphism"] .ambient-bg { display: none !important; } 

        body { font-family: 'Outfit', sans-serif; background-color: var(--theme-bg); color: var(--text-main); overflow: hidden; height: 100dvh; display: flex; flex-direction: column; -webkit-user-select: none; user-select: none; touch-action: pan-y; margin: 0; transition: background-color 0.5s; }

        #custom-bg-layer { position: fixed; inset: 0; z-index: -3; background-size: cover; background-position: center; opacity: var(--bg-opacity); filter: blur(var(--bg-blur)); transition: opacity 0.5s, filter 0.3s; pointer-events: none; }
        .app-bg-overlay { position: fixed; inset: 0; z-index: -2; background: var(--theme-bg); opacity: 0.3; pointer-events: none; transition: opacity 0.3s, background 0.5s; }

        #intro-screen { position: fixed; inset: 0; z-index: 200; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #0b0c14; transition: opacity 1.5s ease; }
        #logo-wrapper { position: relative; z-index: 10; opacity: 0; transform: scale(0.9); filter: blur(10px); transition: all 3s ease; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
        .ps-logo-intro { font-size: 6rem; color: rgba(255,255,255,0.2); }
        
        body.has-wallpaper .ambient-bg { display: none; }
        
        .ambient-bg { position: fixed; inset: 0; z-index: -4; overflow: hidden; background: radial-gradient(circle at 50% 50%, #0a0a1a 0%, var(--theme-bg) 100%); transform: scale(1.2); transition: transform 2.5s cubic-bezier(0.16, 1, 0.3, 1); }
        .orb { position: absolute; border-radius: 50%; filter: blur(90px); opacity: 0.35; animation: float-orb 20s infinite alternate ease-in-out; pointer-events: none; }
        .orb-1 { width: 350px; height: 350px; background: var(--theme-prim); top: -10%; left: -10%; transition: background 0.5s; }
        .orb-2 { width: 450px; height: 450px; background: var(--theme-sec); bottom: -20%; right: -10%; animation-delay: -5s; transition: background 0.5s; }
        .orb-3 { width: 250px; height: 250px; background: #0f172a; top: 40%; left: 40%; animation-delay: -10s; }
        @keyframes float-orb { 0% { transform: translate(0, 0) scale(1); } 50% { transform: translate(30px, 50px) scale(1.2); } 100% { transform: translate(-20px, 20px) scale(0.9); } }

        #stardust { position: fixed; inset: 0; width: 100%; height: 100%; pointer-events: none; z-index: 201; opacity: 0.8; transition: z-index 0s 1.5s, opacity 0.5s; background-color: transparent !important; }
        
        #app-ui { opacity: 0; transition: opacity 1.5s ease; flex: 1; display: flex; flex-direction: column; overflow: hidden; position: relative; z-index: 10; width: 100%; background: transparent; }
        
        /* Glassmorphism Puro y Ultra Transparente */
        .glass-panel { background: rgba(10, 15, 25, 0.25) !important; backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border: 1px solid rgba(255,255,255,0.08); box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4); }
        body[data-theme="glass"] .glass-panel { background: rgba(255, 255, 255, 0.03) !important; border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); }

        .theme-icon-box { background-color: color-mix(in srgb, var(--theme-prim) 15%, transparent); border: 1px solid color-mix(in srgb, var(--theme-prim) 25%, transparent); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .theme-text { color: var(--theme-prim); }
        
        /* Estilo Botón Pedestal Premium (Idéntico a tu foto) */
        .btn-premium { 
            background: linear-gradient(180deg, rgba(30, 35, 45, 0.6) 0%, rgba(15, 18, 25, 0.8) 100%); 
            border: 1px solid rgba(255,255,255,0.05);
            border-bottom: 1px solid color-mix(in srgb, var(--theme-prim) 60%, transparent);
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.6), inset 0 2px 5px rgba(255,255,255,0.05); 
            color: white; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            cursor: pointer; 
            backdrop-filter: blur(15px);
            position: relative;
            overflow: hidden;
        }
        /* El brillo intenso en el centro inferior (El toque maestro) */
        .btn-premium::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 50%;
            transform: translateX(-50%);
            width: 40%;
            height: 2px;
            background: #fff;
            box-shadow: 0 0 15px 4px var(--theme-prim);
            z-index: 10;
        }
        .btn-premium:active { transform: scale(0.96); box-shadow: 0 5px 15px rgba(0,0,0,0.8); }
        
        .dock-nav { position: fixed; bottom: calc(15px + env(safe-area-inset-bottom)); left: 50%; transform: translateX(-50%); z-index: 40; display: flex; justify-content: space-between; align-items: center; width: 92%; max-width: 420px; padding: 8px 12px; border-radius: 28px; background: rgba(8, 8, 12, 0.85) !important; backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1) !important; box-shadow: 0 10px 40px rgba(0,0,0,0.9); }
        .dock-item { width: 46px; height: 46px; border-radius: 16px; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.3); font-size: 1.2rem; transition: all 0.2s; flex-shrink: 0; cursor: pointer; position: relative; border: 1px solid transparent; }
        .dock-item.active { color: var(--theme-prim); background: rgba(255,255,255,0.05); border-color: var(--theme-prim); box-shadow: 0 0 10px color-mix(in srgb, var(--theme-prim) 30%, transparent); }
        .dock-item.active::after { content: ''; position: absolute; bottom: -8px; left: 50%; transform: translateX(-50%); width: 16px; height: 3px; border-radius: 2px; background: var(--theme-prim); box-shadow: 0 0 8px var(--theme-prim); }
        .dock-ripple { position: absolute; top: 0; left: 0; right: 0; bottom: 0; border-radius: inherit; border: 2px solid var(--theme-prim); box-shadow: 0 0 15px var(--theme-prim); animation: ps5-burst 0.5s ease-out forwards; pointer-events: none; z-index: -1; }
        @keyframes ps5-burst { 0% { transform: scale(1); opacity: 0.8; } 100% { transform: scale(2.2); opacity: 0; } }

        #notification-area { position: fixed; top: max(20px, env(safe-area-inset-top)); right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; pointer-events: none; align-items: flex-end; }
        .ps5-toast { background: rgba(10, 15, 25, 0.95); backdrop-filter: blur(20px); border: 1px solid var(--theme-prim); border-radius: 14px; padding: 12px 16px; display: flex; align-items: center; gap: 14px; box-shadow: 0 15px 35px rgba(0,0,0,0.7), 0 0 15px color-mix(in srgb, var(--theme-prim) 20%, transparent); color: white; min-width: 250px; max-width: calc(100vw - 40px); animation: slideInRight 0.4s cubic-bezier(0.17, 0.67, 0.32, 1) forwards; }
        .ps5-toast.hide { animation: fadeOutRight 0.4s cubic-bezier(0.17, 0.67, 0.32, 1) forwards; }
        .ps5-toast-icon { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, var(--theme-prim) 0%, var(--theme-sec) 100%); color: white; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; box-shadow: 0 0 15px color-mix(in srgb, var(--theme-prim) 50%, transparent); }
        @keyframes slideInRight { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeOutRight { to { transform: translateX(120%); opacity: 0; } }

        /* Contenedor Pedestal Animado */
        .pedestal-btn-container { transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); transform-origin: top; }
        .floating-hidden { opacity: 0 !important; pointer-events: none !important; transform: translateY(10px) scale(0.95); height: 0 !important; margin: 0 !important; padding: 0 !important; overflow: hidden; border: none !important; }

        main { flex: 1; position: relative; width: 100%; overflow: hidden; max-width: 56rem; margin: 0 auto; }
        .tab-content { position: absolute; inset: 0; display: none; opacity: 0; transform: translateY(20px) scale(0.98); transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); overflow-y: auto; padding-bottom: 100px; padding-left: 1.25rem; padding-right: 1.25rem; }
        
        /* FIX: Convertimos las pestañas en Flexbox para que no aplasten al botón */
        .tab-content.active { display: flex; flex-direction: column; opacity: 1; transform: translateY(0) scale(1); }
        #tab-biblioteca.active { overflow: hidden; padding-bottom: 80px; }
        
        .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background-color: color-mix(in srgb, var(--theme-prim) 40%, transparent); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-track { background-color: transparent; }
        
        .folder-btn.active { background: rgba(255,255,255,0.1); border-color: var(--theme-prim); color: var(--theme-prim); }
        .payload-item.selected { background: rgba(255,255,255,0.1); border-color: var(--theme-sec); }
        
        /* Galería Pro: 4 Columnas perfectas sin espacios vacíos */
        .grid-cols-4 { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.25rem; }
        .gallery-item { position: relative; cursor: pointer; border-radius: 12px; overflow: hidden; border: 2px solid transparent; transition: all 0.2s; background-color: #000; width: 100%; aspect-ratio: 1 / 1; }
        .gallery-item.selected { border-color: var(--theme-prim); box-shadow: 0 0 15px var(--theme-prim), inset 0 0 10px var(--theme-prim); transform: scale(0.96); }
        .gallery-item img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: block; pointer-events: none;}
        
        /* Papelera Contextual (Solo visible al seleccionar) */
        .gallery-item .gallery-trash-btn { opacity: 0; pointer-events: none; transition: all 0.2s; }
        .gallery-item.selected .gallery-trash-btn { opacity: 1; pointer-events: auto; }
        
        .rpi-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; position: relative; overflow: hidden; border: 2px solid transparent; background: rgba(255,255,255,0.05); }
        .rpi-card.selected { border-color: var(--theme-prim); transform: scale(0.96); box-shadow: 0 0 20px color-mix(in srgb, var(--theme-prim) 30%, transparent); background: rgba(255, 255, 255, 0.1); }
        .rpi-card-check { position: absolute; top: 8px; right: 8px; width: 24px; height: 24px; background: rgba(0,0,0,0.6); border: 2px solid rgba(255,255,255,0.4); border-radius: 50%; display: flex; align-items: center; justify-content: center; z-index: 20; transition: all 0.2s; }
        .rpi-card.selected .rpi-card-check { background: var(--theme-prim); border-color: var(--theme-prim); box-shadow: 0 0 10px var(--theme-prim); }
        .rpi-card.selected .rpi-card-check i { opacity: 1; transform: scale(1); }
        .rpi-card-check i { opacity: 0; transform: scale(0.5); transition: all 0.2s; color: black; font-size: 12px; }

        .bottom-sheet { position: fixed; bottom: 0; left: 50%; transform: translateX(-50%) translateY(100%); width: 100%; max-width: 32rem; background: rgba(10,15,25,0.95); backdrop-filter: blur(25px); border-top-left-radius: 28px; border-top-right-radius: 28px; z-index: 60; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); border-top: 1px solid var(--theme-prim); box-shadow: 0 -10px 40px rgba(0,0,0,0.8); padding-bottom: env(safe-area-inset-bottom); max-height: 90vh; overflow-y: auto;}
        .bottom-sheet.open { transform: translateX(-50%) translateY(0); }
        .overlay-sheet { position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 55; opacity: 0; pointer-events: none; transition: opacity 0.3s; backdrop-filter: blur(8px); }
        .overlay-sheet.open { opacity: 1; pointer-events: auto; }

        .game-card { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); transform-origin: center; border: 1px solid rgba(255,255,255,0.05); background: rgba(10,10,20,0.8); }
        .sorting-anim { transform: scale(0.9); opacity: 0.5; }

        #lightbox-modal { backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); }
        .zoom-anim { transition: transform 0.3s ease-out; }

        @media (min-width: 768px) {
            .dock-nav { max-width: 500px; padding: 12px 20px; bottom: calc(25px + env(safe-area-inset-bottom)); }
            .dock-item { width: 50px; height: 50px; font-size: 1.3rem; }
            .bottom-sheet { max-width: 40rem; }
        }
        @media (max-width: 767px) and (orientation: landscape) {
            #landscape-warning { display: flex !important; }
            #app-ui { display: none !important; }
        }
    </style>
</head>

<body data-theme="ps5">
    
    <div id="custom-bg-layer"></div>
    <div class="app-bg-overlay"></div>

    <div id="notification-area"></div>

    <div id="landscape-warning" class="hidden fixed inset-0 z-[999] bg-[#05050a] flex-col items-center justify-center text-center p-8">
        <i class="fa-solid fa-mobile-screen text-6xl mb-6 animate-pulse" style="color: var(--theme-prim); transform: rotate(-90deg); filter: drop-shadow(0 0 15px color-mix(in srgb, var(--theme-prim) 50%, transparent));"></i>
        <h2 class="text-2xl font-black text-[var(--text-main)] mb-2 tracking-widest uppercase">Gira tu celular</h2>
        <p class="text-[var(--text-muted)] text-sm">Por favor, usa el dispositivo en modo vertical para una mejor experiencia.</p>
    </div>

    <div class="ambient-bg" id="ambient-bg"><div class="orb orb-1"></div><div class="orb orb-2"></div><div class="orb orb-3"></div></div>
    <canvas id="stardust"></canvas>
    
    <div id="intro-screen">
        <div id="logo-wrapper">
            <i class="fa-brands fa-playstation ps-logo-intro"></i>
            <h1 class="text-[14px] font-black tracking-[0.2em] text-[var(--text-muted)] uppercase mt-4">GoldHen Manager v2.1</h1>
        </div>
    </div>
    
    <div id="app-ui">
        
        <header class="w-full max-w-md md:max-w-4xl mx-auto pt-3 px-4 flex justify-between items-center z-50 mb-4 shrink-0 gap-2">
            <div id="badge-detectada" class="hidden items-center gap-1.5 bg-[#102418] border border-[#1b3d28] px-2.5 py-1.5 rounded-full shrink-0">
                <div id="badge-dot" class="w-2 h-2 rounded-full bg-[#4ade80] animate-pulse shadow-[0_0_8px_#4ade80]"></div>
            </div>
            <div id="badge-desconectada" class="flex items-center gap-1.5 bg-[#2d1b1e] border border-[#5c2a32] px-2.5 py-1.5 rounded-full shrink-0">
                <div class="w-2 h-2 rounded-full bg-red-500 shadow-[0_0_8px_#ef4444]"></div>
            </div>

            <div class="flex-1 flex items-center justify-between bg-black/40 border border-white/5 rounded-full px-3 py-1.5 shadow-inner backdrop-blur-md">
                <div class="flex items-center gap-2 overflow-hidden w-full">
                    <i class="fa-solid fa-network-wired text-white/30 text-[9px]"></i>
                    <input type="text" id="host-ip" placeholder="192.168.x.x" data-i18n-placeholder="ip_placeholder" class="bg-transparent w-full text-[10px] font-mono outline-none text-white truncate placeholder-[var(--text-muted)]" autocomplete="off">
                </div>
                <div class="flex items-center gap-3 pl-2 border-l border-white/10 shrink-0">
                    <i class="fa-solid fa-plug text-[10px] text-[var(--text-muted)] hover:text-white cursor-pointer" onclick="connectManualIP()"></i>
                    <i id="scan-icon" class="fa-solid fa-satellite-dish text-[10px] text-[var(--theme-prim)] cursor-pointer" onclick="toggleRealScan()"></i>
                </div>
            </div>

            <div class="flex items-center gap-2 pl-1 cursor-pointer group" onclick="document.getElementById('avatar-upload').click()">
                <input type="file" id="avatar-upload" accept="image/gif, image/jpeg, image/png" class="hidden" onchange="handleAvatarUpload(event)">
                <div id="profile-avatar" class="w-7 h-7 rounded-full flex items-center justify-center border border-white/20 shadow-[0_0_10px_color-mix(in_srgb,var(--theme-prim)_30%,transparent)] overflow-hidden bg-cover bg-center transition-transform group-hover:scale-110" style="background-color: var(--theme-prim);">
                    <span id="profile-initial" class="text-black font-black text-[10px]">S</span>
                </div>
                <span id="profile-name" class="text-[9px] font-black tracking-widest uppercase truncate max-w-[60px]" style="color: var(--theme-prim);">SEBAS</span>
            </div>
        </header>

        <div class="w-full max-w-md md:max-w-4xl mx-auto px-5 relative z-10 mb-2 shrink-0">
            <p id="global-status" class="text-[9px] font-bold text-center font-mono text-red-500 drop-shadow-[0_0_10px_rgba(239,68,68,0.8)] tracking-widest hidden"><i class="fa-solid fa-satellite-dish fa-fade mr-2 text-[var(--text-main)]"></i> <span id="scan-text">BUSCANDO IP...</span></p>
        </div>

        <div id="install-pwa-container" class="hidden w-full max-w-md md:max-w-4xl mx-auto px-5 relative z-10 mb-3 transition-all duration-300 shrink-0">
            <div class="bg-black/60 backdrop-blur-md border border-[var(--theme-prim)]/40 rounded-2xl p-4 relative overflow-hidden flex items-center justify-between">
                <div class="absolute top-0 right-0 w-20 h-20 blur-[30px] rounded-full pointer-events-none" style="background-color: color-mix(in srgb, var(--theme-prim) 20%, transparent);"></div>
                <div class="flex items-center gap-3 relative z-10">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 border" style="background-color: color-mix(in srgb, var(--theme-prim) 20%, transparent); border-color: color-mix(in srgb, var(--theme-prim) 30%, transparent);"><i class="fa-solid fa-mobile-screen-button text-lg" style="color: var(--theme-prim);"></i></div>
                    <div><span class="text-xs font-bold text-[var(--text-main)] block" data-i18n="install_app">Instalar App</span><span class="text-[9px] text-[var(--text-muted)]" data-i18n="install_desc">Añadir a pantalla de inicio</span></div>
                </div>
                <div class="flex items-center gap-2 relative z-10">
                    <button onclick="installPWA()" class="text-black font-black text-[9px] tracking-widest px-4 py-2.5 rounded-lg active:scale-95" style="background-color: var(--theme-prim); box-shadow: 0 0 15px color-mix(in srgb, var(--theme-prim) 40%, transparent);" data-i18n="btn_install">INSTALAR</button>
                    <button onclick="dismissPWA()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-[var(--text-muted)] hover:text-[var(--text-main)] flex items-center justify-center transition-colors"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
        </div>

        <main class="w-full relative z-10 flex-1">
            
            <div id="tab-biblioteca" class="tab-content active">
                <div class="flex gap-2 w-full mb-2 shrink-0">
                    <div class="relative flex-1 h-[42px]">
                        <i class="fa-solid fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-[var(--text-muted)] text-[11px]"></i>
                        <input type="text" id="buscador-biblio" onkeyup="buscarEnBiblioteca(this.value)" placeholder="Buscar juego o app..." data-i18n-placeholder="search_placeholder" class="w-full h-full bg-black/60 backdrop-blur-md border border-white/5 rounded-[1.2rem] pl-10 pr-4 text-xs font-bold tracking-wider text-[var(--text-main)] outline-none focus:border-[var(--theme-prim)] transition-colors placeholder-[var(--text-muted)] shadow-inner" autocomplete="off">
                    </div>
                    <button onclick="cambiarOrden()" class="w-[42px] h-[42px] rounded-[1.2rem] border border-white/5 bg-black/60 backdrop-blur-md flex items-center justify-center text-[var(--text-main)] active:scale-95 shrink-0 transition-colors hover:bg-white/5 group shadow-inner">
                        <i id="icono-orden" class="fa-solid fa-arrow-down-short-wide text-sm group-hover:text-[var(--text-main)] transition-colors" style="color: var(--theme-prim); filter: drop-shadow(0 0 5px color-mix(in srgb, var(--theme-prim) 30%, transparent));"></i>
                    </button>
                </div>

                <div id="categoria-nav" class="flex gap-2 overflow-x-auto pb-1 mb-2 custom-scrollbar items-center shrink-0"></div>

                <div class="flex-1 w-full overflow-y-auto custom-scrollbar pr-1 relative mb-2">
                    <div class="grid grid-cols-4 gap-1.5" id="grid-biblioteca"></div>
                </div>

                <div class="shrink-0 w-full pt-1">
                    <div class="flex justify-between items-end mb-3">
                        <div class="flex flex-col">
                            <div class="flex items-center gap-2 text-[10px]">
                                <div class="flex items-center gap-1.5 font-bold bg-black/50 px-2.5 py-0.5 rounded-full border backdrop-blur-md" style="color: var(--theme-prim); border-color: color-mix(in srgb, var(--theme-prim) 30%, transparent);">
                                    <i class="fa-solid fa-gamepad"></i> <span class="text-[10px]" id="total-games-badge">--</span>
                                </div>
                                <span class="text-[var(--text-muted)] font-mono tracking-widest uppercase drop-shadow-[0_0_2px_rgba(0,0,0,0.8)]" data-i18n="titles_installed">TÍTULOS INSTALADOS</span>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="limpiarBibliotecaEntera()" class="w-10 h-10 rounded-2xl bg-red-900/40 backdrop-blur-md border border-red-500/30 flex items-center justify-center text-red-400 active:scale-95 shadow-[0_0_10px_rgba(239,68,68,0.2)] hover:bg-red-900/60 transition-colors"><i class="fa-solid fa-trash-can text-sm"></i></button>
                            <button onclick="simularSincronizacion()" class="w-10 h-10 rounded-2xl bg-black/60 backdrop-blur-md border border-white/10 flex items-center justify-center active:scale-95 hover:bg-white/10 transition-colors" style="color: var(--theme-prim);"><i class="fa-solid fa-rotate text-sm"></i></button>
                        </div>
                    </div>
                    <div class="w-full h-px bg-gradient-to-r from-[var(--theme-prim)] via-[var(--theme-sec)] to-transparent opacity-50"></div>
                </div>
            </div>

            <div id="tab-ftp" class="tab-content">
                <div class="glass-panel p-1.5 rounded-2xl mb-4 flex relative shrink-0">
                    <button id="btn-mode-rpi" onclick="switchTransferMode('rpi')" class="flex-1 py-3 text-[9px] font-black tracking-widest rounded-xl text-[var(--text-muted)] hover:text-[var(--text-main)] whitespace-nowrap relative z-10 transition-all" data-i18n="rpi_sender">RPI SENDER</button>
                    <button id="btn-mode-ftp" onclick="switchTransferMode('ftp')" class="flex-1 py-3 text-[9px] font-black tracking-widest rounded-xl text-[var(--text-muted)] hover:text-[var(--text-main)] whitespace-nowrap relative z-10 transition-all" data-i18n="ftp_classic">FTP CLÁSICO</button>
                </div>

                <div id="box-mode-rpi" class="glass-panel rounded-[2rem] p-6 mb-2 shrink-0 relative flex flex-col flex-1 min-h-0">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center border" style="background-color: color-mix(in srgb, var(--theme-prim) 20%, transparent); border-color: color-mix(in srgb, var(--theme-prim) 30%, transparent);">
                                <i class="fa-solid fa-mobile-screen text-xs" style="color: var(--theme-prim);"></i>
                            </div>
                            <h2 class="text-[10px] font-black tracking-widest" style="color: var(--theme-prim);" data-i18n="games_on_phone">JUEGOS EN EL CELULAR</h2>
                        </div>
                        <button onclick="document.getElementById('rpi-pc-upload').click()" class="bg-white/5 hover:bg-white/10 text-white rounded-lg px-3 py-2 text-[9px] font-black tracking-widest flex items-center gap-2 transition-colors border border-white/10 shadow-inner">
                            <i class="fa-solid fa-computer" style="color: var(--theme-prim);"></i> DESDE PC
                        </button>
                        <input type="file" id="rpi-pc-upload" accept=".pkg" class="hidden" onchange="handlePCUpload(this)">
                    </div>
                    <div id="rpi-pkg-list" class="grid grid-cols-4 gap-1.5 flex-1 overflow-y-auto custom-scrollbar min-h-0"></div>
                    
                    <div id="floating-btn-rpi" class="pedestal-btn-container floating-hidden shrink-0 w-full flex justify-center pt-4 mt-auto">
                        <button onclick="iniciarColaInstalacionRPI()" class="btn-premium w-full rounded-2xl py-4 flex items-center justify-center gap-3 active:scale-95 transition-transform" style="background: linear-gradient(180deg, rgba(251, 191, 36, 0.2) 0%, rgba(217, 119, 6, 0.3) 100%); border-color: rgba(251, 191, 36, 0.5);">
                            <i class="fa-solid fa-download text-lg text-yellow-400"></i>
                            <span id="rpi-install-text" class="text-[10px] font-black tracking-widest uppercase text-yellow-400" data-i18n="btn_install_ps4">INSTALAR EN PS4</span>
                        </button>
                    </div>
                </div>

                <form id="ftp-form" onsubmit="enviarArchivoChunks(event)" class="hidden shrink-0 h-full flex flex-col">
                    <div class="glass-panel rounded-[2rem] p-6 flex-1">
                        <h2 class="text-[10px] font-black tracking-widest mb-4" style="color: var(--theme-prim);"><i class="fa-solid fa-folder-open mr-2"></i>RUTAS DE DESTINO</h2>
                        <div class="grid grid-cols-2 gap-3 mb-6" id="paths-grid">
                            <button type="button" class="folder-btn active btn-ps5 rounded-xl py-3 text-xs font-bold text-[var(--text-muted)] border border-white/10" onclick="selectPath(this, '/data/')">/data/</button>
                            <button type="button" class="folder-btn btn-ps5 rounded-xl py-3 text-xs font-bold text-[var(--text-muted)] border border-white/10" onclick="selectPath(this, '/data/pkg/')">/data/pkg/</button>
                            <button type="button" class="folder-btn btn-ps5 rounded-xl py-3 text-[11px] font-bold text-[var(--text-muted)] border border-white/10" onclick="selectPath(this, '/mnt/usb0/')">/mnt/usb0/</button>
                            <button type="button" id="btn-otra" class="folder-btn btn-ps5 rounded-xl py-3 text-xs font-bold text-[var(--text-muted)] border border-dashed border-white/20" onclick="showAddPathUI()"><i class="fa-solid fa-plus"></i></button>
                        </div>
                        <input type="hidden" id="selected-path-input" value="/data/">
                        
                        <div id="add-path-ui" class="hidden mb-6 flex gap-2">
                            <input type="text" id="new-path-input" placeholder="/ruta/" class="flex-1 bg-black/40 border border-white/10 rounded-xl px-4 text-sm font-mono text-[var(--text-main)] outline-none focus:border-[var(--theme-prim)]">
                            <button type="button" onclick="saveNewPath()" class="text-black px-4 rounded-xl font-bold" style="background-color: var(--theme-prim); box-shadow: 0 0 15px color-mix(in srgb, var(--theme-prim) 40%, transparent);"><i class="fa-solid fa-check"></i></button>
                            <button type="button" onclick="hideAddPathUI()" class="bg-white/10 text-[var(--text-main)] px-4 rounded-xl border border-white/10"><i class="fa-solid fa-xmark"></i></button>
                        </div>

                        <h2 class="text-[10px] font-black tracking-widest mb-4" style="color: var(--theme-prim);"><i class="fa-solid fa-file-circle-plus mr-2"></i>ARCHIVOS (PKG/BIN)</h2>
                        <div onclick="document.getElementById('file-upload').click()" class="w-full aspect-video border border-dashed rounded-3xl flex flex-col items-center justify-center cursor-pointer bg-black/40 hover:bg-black/60 transition-colors group" style="border-color: color-mix(in srgb, var(--theme-prim) 30%, transparent);">
                            <div id="upload-icon-container" class="w-16 h-16 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform" style="background-color: color-mix(in srgb, var(--theme-prim) 10%, transparent); box-shadow: 0 0 15px color-mix(in srgb, var(--theme-prim) 20%, transparent); color: var(--theme-prim);"><i class="fa-solid fa-cloud-arrow-up text-2xl"></i></div>
                            <span id="file-name-display" class="text-[10px] font-black tracking-widest text-[var(--text-muted)] text-center px-4" data-i18n="touch_select">TOCA PARA SELECCIONAR</span>
                            <input type="file" id="file-upload" class="hidden" multiple onchange="updateFileName(this)">
                        </div>
                    </div>
                    
                    <div id="floating-btn-ftp" class="pedestal-btn-container floating-hidden shrink-0 w-full flex justify-center pt-4">
                        <button type="button" onclick="document.getElementById('ftp-form-submit').click()" class="btn-premium w-full rounded-2xl py-4 flex items-center justify-center gap-3 active:scale-95 transition-transform">
                            <i class="fa-brands fa-playstation text-lg" style="color: var(--theme-prim);"></i>
                            <span class="text-[10px] font-black tracking-widest uppercase" data-i18n="btn_send_files">ENVIAR ARCHIVOS</span>
                        </button>
                    </div>
                    <button type="submit" id="ftp-form-submit" class="hidden"></button>
                </form>
            </div>

            <div id="tab-icons" class="tab-content relative flex flex-col">
                <form id="icon-form" onsubmit="enviarIcono(event)" class="flex-1 flex flex-col min-h-0">
                    <div class="glass-panel rounded-[2rem] p-4 flex flex-col shrink-0 border border-white/5 shadow-2xl flex-1 min-h-0">
                        <div class="flex gap-2 mb-4 items-end">
                            <div class="flex-1 relative">
                                <div class="flex justify-between items-center mb-1.5 px-1">
                                    <label class="text-[9px] font-black tracking-widest block" style="color: var(--theme-prim);" data-i18n="title_id">TITLE ID</label>
                                    <span class="text-[8px] text-[var(--text-muted)] uppercase tracking-widest">Personalizar Portada</span>
                                </div>
                                <input type="text" id="icon-cusa" placeholder="CUSA12345" oninput="this.value = this.value.toUpperCase();" class="w-full bg-[#0a0a0f] border border-white/5 rounded-2xl px-5 py-3.5 font-mono text-center text-lg outline-none text-[var(--text-main)] uppercase shadow-inner">
                            </div>
                            <button type="button" onclick="respaldarOriginal()" class="h-[52px] w-[52px] bg-black/40 border border-white/10 text-[var(--text-muted)] hover:text-[var(--text-main)] rounded-2xl flex items-center justify-center active:scale-95 shrink-0 transition-colors">
                                <i class="fa-solid fa-download text-lg"></i>
                            </button>
                            <button type="button" onclick="respaldarTodos()" class="h-[52px] w-[52px] text-black rounded-2xl flex items-center justify-center active:scale-95 shrink-0 transition-colors shadow-lg" style="background-color: var(--theme-prim);">
                                <i class="fa-solid fa-layer-group text-lg"></i>
                            </button>
                        </div>
                        
                        <div class="flex gap-1.5 p-1 bg-black/40 rounded-[1.2rem] mb-4 border border-white/5 overflow-x-auto custom-scrollbar shrink-0 shadow-inner">
                            <button type="button" id="btn-src-gallery" class="flex-1 py-2 px-3 text-[9px] font-black rounded-xl text-[var(--text-muted)] hover:text-[var(--text-main)] whitespace-nowrap" onclick="switchIconSource('gallery')" data-i18n="gallery">GALERÍA</button>
                            <button type="button" id="btn-src-backup" class="flex-1 py-2 px-3 text-[9px] font-black rounded-xl text-[var(--text-muted)] hover:text-[var(--text-main)] whitespace-nowrap" onclick="switchIconSource('backup')" data-i18n="backups">BACKUPS</button>
                            <button type="button" id="btn-src-import" class="flex-1 py-2 px-3 text-[9px] font-black rounded-xl text-[var(--text-muted)] hover:text-[var(--text-main)] whitespace-nowrap" onclick="switchIconSource('import')" data-i18n="import">IMPORTAR</button>
                            <button type="button" id="btn-src-local" class="flex-1 py-2 px-3 text-[9px] font-black rounded-xl text-[var(--text-muted)] hover:text-[var(--text-main)] whitespace-nowrap" onclick="switchIconSource('local')" data-i18n="local">LOCAL</button>
                        </div>
                        
                        <div id="box-src-gallery" class="animate-fade-in flex-1 overflow-hidden min-h-0"><div id="gallery-container" class="h-full"></div></div>
                        <div id="box-src-backup" class="hidden animate-fade-in flex-1 overflow-hidden min-h-0"><div id="backup-container" class="h-full"></div></div>
                        <div id="box-src-import" class="hidden animate-fade-in shrink-0">
                            <div class="bg-black/40 border border-white/5 rounded-[1.5rem] p-5 text-center h-[250px] flex flex-col justify-center shadow-inner">
                                <i class="fa-solid fa-cloud-arrow-down text-4xl mb-3" style="color: color-mix(in srgb, var(--theme-prim) 50%, transparent);"></i>
                                <p class="text-[10px] text-[var(--text-muted)] mb-4 px-2" data-i18n="import_desc">Pega un link directo para descargar a tu galería.</p>
                                <input type="url" id="import-url" placeholder="https://..." class="w-full bg-[#0a0a0f] rounded-xl px-4 py-3 border border-white/5 font-mono text-xs text-[var(--text-main)] outline-none mb-4 shadow-inner">
                                <button type="button" id="btn-cargar-url" onclick="importarURL()" class="w-full text-black rounded-xl py-3 font-black text-[10px] tracking-widest transition-colors shadow-[0_0_15px_color-mix(in_srgb,var(--theme-prim)_30%,transparent)]" style="background-color: var(--theme-prim);" data-i18n="btn_import">INICIAR IMPORTACIÓN</button>
                            </div>
                        </div>
                        <div id="box-src-local" class="hidden animate-fade-in shrink-0">
                            <div onclick="document.getElementById('icon-file').click()" class="w-full h-[250px] bg-black/40 rounded-[1.5rem] border border-dashed flex flex-col items-center justify-center cursor-pointer relative overflow-hidden group hover:bg-black/60 transition-colors shadow-inner" style="border-color: color-mix(in srgb, var(--theme-prim) 30%, transparent);">
                                <i id="icon-file-placeholder" class="fa-solid fa-image text-4xl mb-3 group-hover:scale-110 transition-transform" style="color: color-mix(in srgb, var(--theme-prim) 40%, transparent);"></i>
                                <span id="icon-file-name" class="text-[10px] font-black tracking-widest text-[var(--text-muted)] text-center px-4 z-10" data-i18n="touch_image">TOCA PARA BUSCAR IMAGEN</span>
                                <img id="preview-img-local" src="" class="hidden absolute inset-0 w-full h-full object-contain z-20 bg-black/90">
                                <input type="file" id="icon-file" accept="image/png, image/jpeg" class="hidden" onchange="previewLocal(this)">
                            </div>
                        </div>
                    </div>
                    
                    <div id="floating-btn-aplicar" class="pedestal-btn-container floating-hidden shrink-0 w-full flex justify-center pt-4">
                        <button type="button" onclick="ejecutarFormIconos()" class="btn-premium w-full rounded-2xl py-4 flex items-center justify-center gap-3 active:scale-95 transition-transform">
                            <i class="fa-solid fa-wand-magic-sparkles text-lg" style="color: var(--theme-prim);"></i>
                            <span class="text-[10px] font-black tracking-widest uppercase" data-i18n="btn_apply_art">APLICAR PORTADA</span>
                        </button>
                    </div>
                    <button type="submit" id="icon-form-submit" class="hidden"></button>
                </form>
            </div>
            
            <div id="tab-explorer" class="tab-content">
                <div class="flex justify-between items-end mb-4 shrink-0 px-2">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-folder-tree text-[var(--theme-prim)]"></i>
                        <span class="text-xs font-black tracking-widest text-[var(--text-main)] uppercase">Gestor Interno</span>
                    </div>
                    <div class="flex gap-1.5 flex-wrap justify-end">
                        <button onclick="addCurrentPathToShortcuts()" class="w-8 h-8 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-[var(--text-main)] hover:bg-yellow-500/20 hover:text-yellow-400 transition-colors hover:border-yellow-500/50 backdrop-blur-md"><i class="fa-solid fa-star text-[10px]"></i></button>
                        <button onclick="promptCreateFolder()" class="w-8 h-8 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-[var(--text-main)] transition-colors hover:bg-white/20 backdrop-blur-md"><i class="fa-solid fa-folder-plus text-[10px]"></i></button>
                        <button id="btn-select-mode" onclick="toggleSelectMode()" class="w-8 h-8 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-[var(--text-main)] transition-colors backdrop-blur-md"><i class="fa-solid fa-list-check text-[10px]"></i></button>
                        <button onclick="if(currentExplorerPath) loadExplorerPath(currentExplorerPath)" class="w-8 h-8 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-[var(--text-main)] transition-colors hover:bg-white/20 backdrop-blur-md"><i class="fa-solid fa-rotate-right text-[10px]"></i></button>
                        <button onclick="loadExplorerPath('/')" class="w-8 h-8 rounded-xl border flex items-center justify-center transition-colors hover:opacity-80 backdrop-blur-md" style="background-color: color-mix(in srgb, var(--theme-prim) 20%, transparent); border-color: color-mix(in srgb, var(--theme-prim) 30%, transparent); color: var(--theme-prim);"><i class="fa-solid fa-home text-[10px]"></i></button>
                    </div>
                </div>

                <div id="clipboard-panel" class="hidden mb-4 glass-panel rounded-xl p-3 flex justify-between items-center border shrink-0" style="border-color: color-mix(in srgb, var(--theme-prim) 30%, transparent); box-shadow: 0 0 15px color-mix(in srgb, var(--theme-prim) 10%, transparent);">
                    <div class="flex items-center gap-3 overflow-hidden pr-2">
                        <i class="fa-solid fa-scissors text-sm" style="color: var(--theme-prim);"></i><span id="clipboard-text" class="text-[11px] text-[var(--text-main)] font-mono truncate">...</span>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <button onclick="cancelPaste()" class="text-[var(--text-muted)] hover:text-red-400 text-sm p-1"><i class="fa-solid fa-xmark"></i></button>
                        <button onclick="executePaste()" class="text-black px-4 py-1.5 rounded-lg text-[10px] font-black" style="background-color: var(--theme-prim); box-shadow: 0 0 10px color-mix(in srgb, var(--theme-prim) 40%, transparent);"><i class="fa-solid fa-paste mr-1"></i> <span data-i18n="btn_paste">PEGAR</span></button>
                    </div>
                </div>

                <div id="multi-action-panel" class="hidden mb-4 glass-panel rounded-xl p-3 flex justify-between items-center border-white/20 animate-fade-in shrink-0">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-lg flex items-center justify-center border" style="background-color: color-mix(in srgb, var(--theme-prim) 20%, transparent); border-color: color-mix(in srgb, var(--theme-prim) 30%, transparent);"><i class="fa-solid fa-check-double text-[10px]" style="color: var(--theme-prim);"></i></div>
                        <span id="multi-select-count" class="text-[10px] text-[var(--text-main)] font-black tracking-widest uppercase">0 seleccionados</span>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="cutSelectedItems()" class="text-black px-4 py-1.5 rounded-lg text-[9px] font-black tracking-widest active:scale-95 transition-all" style="background-color: var(--theme-prim); box-shadow: 0 0 10px color-mix(in srgb, var(--theme-prim) 40%, transparent);"><i class="fa-solid fa-scissors mr-1"></i> <span data-i18n="opt_move_btn">MOVER</span></button>
                        <button onclick="deleteSelectedItems()" class="bg-red-600 hover:bg-red-500 text-white px-4 py-1.5 rounded-lg text-[9px] font-black tracking-widest shadow-[0_0_10px_rgba(239,68,68,0.4)] active:scale-95 transition-all"><i class="fa-solid fa-trash mr-1"></i> <span data-i18n="opt_delete_btn">ELIMINAR</span></button>
                    </div>
                </div>

                <div class="glass-panel rounded-[2rem] overflow-hidden flex flex-col h-[65vh] shrink-0 border border-white/10">
                    <div class="bg-black/60 backdrop-blur-md px-5 py-4 border-b flex items-center gap-3 shrink-0" style="border-color: color-mix(in srgb, var(--theme-prim) 20%, transparent);"><i class="fa-solid fa-hard-drive" style="color: color-mix(in srgb, var(--theme-prim) 50%, transparent);"></i><span id="explorer-path-text" class="text-xs font-mono text-[var(--text-main)] drop-shadow-[0_0_2px_rgba(0,0,0,0.8)]">/</span></div>
                    <div id="explorer-shortcuts" class="hidden bg-black/40 px-4 py-2 border-b border-white/5 flex items-center gap-2 overflow-x-auto custom-scrollbar shrink-0"></div>
                    <div id="explorer-list" class="flex-1 p-2 flex flex-col gap-1 overflow-y-auto custom-scrollbar bg-black/20"><div class="text-center text-[var(--text-muted)] text-[10px] font-black tracking-widest uppercase py-20 drop-shadow-[0_0_2px_rgba(0,0,0,0.8)]" data-i18n="config_ip">Configura tu IP.</div></div>
                </div>
            </div>

            <div id="tab-payloads" class="tab-content flex flex-col">
                <form id="payload-form" onsubmit="enviarPayload(event)" class="flex-1 flex flex-col min-h-0">
                    <div class="glass-panel rounded-[2rem] p-6 mb-4 shrink-0 flex flex-col flex-1 min-h-0">
                        <div class="flex items-center justify-between bg-black/60 border border-white/5 rounded-2xl px-5 py-4 mb-4 shadow-inner backdrop-blur-md">
                            <span class="text-[9px] font-black tracking-widest uppercase" style="color: color-mix(in srgb, var(--theme-prim) 70%, transparent);" data-i18n="local_port">PUERTO LOCAL</span>
                            <input type="number" id="payload-port" value="9020" class="bg-transparent text-right font-mono text-[var(--text-main)] text-lg outline-none w-20 border-b border-dashed focus:border-[var(--theme-prim)]" style="border-color: color-mix(in srgb, var(--theme-prim) 40%, transparent);" required>
                        </div>
                        <div class="flex gap-2 p-1 bg-black/40 rounded-[1.2rem] border border-white/5 backdrop-blur-md shadow-inner">
                            <button type="button" id="btn-pay-gallery" class="flex-1 py-3 text-[9px] font-black tracking-widest rounded-xl text-[var(--text-muted)] hover:text-[var(--text-main)] transition-colors" onclick="switchPayloadSource('gallery')" data-i18n="gallery">GALERÍA</button>
                            <button type="button" id="btn-pay-local" class="flex-1 py-3 text-[9px] font-black tracking-widest rounded-xl text-[var(--text-muted)] hover:text-[var(--text-main)] transition-colors" onclick="switchPayloadSource('local')" data-i18n="device">DISPOSITIVO</button>
                        </div>
                        
                        <div id="box-pay-gallery" class="mt-4 animate-fade-in flex-1 overflow-hidden min-h-0"><div id="payload-gallery-container" class="h-full"></div></div>
                        <div id="box-pay-local" class="mt-4 hidden animate-fade-in shrink-0">
                            <div onclick="document.getElementById('payload-file').click()" class="w-full h-[180px] bg-black/40 rounded-[1.5rem] border border-dashed flex flex-col items-center justify-center cursor-pointer hover:bg-black/60 transition-colors group backdrop-blur-md shadow-inner" style="border-color: color-mix(in srgb, var(--theme-prim) 30%, transparent);">
                                <div id="payload-icon-container" class="w-14 h-14 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform" style="background-color: color-mix(in srgb, var(--theme-prim) 10%, transparent); color: var(--theme-prim); box-shadow: 0 0 15px color-mix(in srgb, var(--theme-prim) 20%, transparent);"><i class="fa-solid fa-file-code text-xl"></i></div>
                                <span id="payload-name-display" class="text-[10px] font-black tracking-widest text-[var(--text-muted)] px-6 text-center" data-i18n="touch_bin">TOCA PARA BUSCAR .BIN</span>
                                <input type="file" id="payload-file" accept=".bin" class="hidden" onchange="updatePayloadName(this)">
                            </div>
                        </div>
                    </div>
                    
                    <div id="floating-btn-payload" class="pedestal-btn-container floating-hidden shrink-0 w-full flex justify-center pt-2">
                        <button type="button" onclick="document.getElementById('payload-form-submit').click()" class="btn-premium w-full rounded-2xl py-4 flex items-center justify-center gap-3 active:scale-95 transition-transform" style="background: linear-gradient(180deg, rgba(234, 179, 8, 0.2) 0%, rgba(202, 138, 4, 0.3) 100%); border-color: rgba(234, 179, 8, 0.5);">
                            <i class="fa-solid fa-bolt text-lg text-yellow-400"></i>
                            <span class="text-[10px] font-black tracking-widest uppercase text-yellow-400" data-i18n="btn_inject">INYECTAR PAYLOAD</span>
                        </button>
                    </div>
                    <button type="submit" id="payload-form-submit" class="hidden"></button>
                </form>
            </div>

            <div id="tab-settings" class="tab-content pt-2">
                <div class="flex flex-col w-full glass-panel rounded-3xl p-4 shrink-0 border-white/10">
                    <div class="flex flex-col py-4 border-b border-white/5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center border theme-icon-box" style="background-color: color-mix(in srgb, var(--theme-prim) 10%, transparent); border-color: color-mix(in srgb, var(--theme-prim) 20%, transparent);">
                                    <i class="fa-solid fa-language text-sm theme-text" style="color: var(--theme-prim);"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-xs font-black text-[var(--text-main)] tracking-widest uppercase" data-i18n="set_lang_title">Idioma</span>
                                    <span class="text-[9px] text-[var(--text-muted)]" data-i18n="set_lang_desc">Cambia el idioma.</span>
                                </div>
                            </div>
                            <div class="flex bg-black/60 p-1 rounded-xl border border-white/5 shadow-inner">
                                <button id="btn-lang-es" onclick="setLanguage('es')" class="px-4 py-2 text-[10px] font-black rounded-lg text-black transition-all" style="background-color: var(--theme-prim); box-shadow: 0 0 10px var(--theme-prim);">ES</button>
                                <button id="btn-lang-en" onclick="setLanguage('en')" class="px-4 py-2 text-[10px] font-black rounded-lg text-[var(--text-muted)] hover:text-[var(--text-main)] transition-all">EN</button>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between py-4 border-b border-white/5 cursor-pointer hover:bg-white/5 rounded-xl px-2 transition-colors -mx-2" onclick="abrirBovedaGlobal()">
                        <div class="flex items-center gap-4">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center border theme-icon-box" style="background-color: color-mix(in srgb, var(--theme-prim) 10%, transparent); border-color: color-mix(in srgb, var(--theme-prim) 20%, transparent);">
                                <i class="fa-solid fa-images text-sm theme-text" style="color: var(--theme-prim);"></i>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-xs font-black text-[var(--text-main)] tracking-widest uppercase">BÓVEDA DE CAPTURAS</span>
                                <span class="text-[9px] text-[var(--text-muted)]">Ver fotos de todos los juegos.</span>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-[var(--text-muted)] opacity-50 text-xs"></i>
                    </div>
                    
                    <div class="flex flex-col py-4 border-b border-white/5">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-4">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center border theme-icon-box" style="background-color: color-mix(in srgb, var(--theme-prim) 10%, transparent); border-color: color-mix(in srgb, var(--theme-prim) 20%, transparent);">
                                    <i class="fa-solid fa-image text-sm theme-text" style="color: var(--theme-prim);"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-xs font-black text-[var(--text-main)] tracking-widest uppercase">Wallpaper</span>
                                    <span class="text-[9px] text-[var(--text-muted)]">Fondo y difuminado (Blur).</span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button onclick="document.getElementById('custom-bg-upload').click()" class="w-8 h-8 rounded-xl bg-black/50 flex items-center justify-center transition-all border active:scale-95 shrink-0" style="color: var(--theme-prim); border-color: color-mix(in srgb, var(--theme-prim) 30%, transparent);"><i class="fa-solid fa-upload text-sm"></i></button>
                                <button onclick="clearCustomWallpaper()" class="w-8 h-8 rounded-xl bg-black/50 text-red-400 flex items-center justify-center hover:bg-red-500 hover:text-white transition-all border border-red-500/30 active:scale-95 shrink-0"><i class="fa-solid fa-trash-can text-sm"></i></button>
                                <input type="file" id="custom-bg-upload" accept="image/*" class="hidden" onchange="handleWallpaperUpload(event)">
                            </div>
                        </div>
                        <div class="flex items-center gap-4 pl-12 pr-2">
                            <i class="fa-solid fa-droplet text-[var(--text-muted)] opacity-50 text-[10px]"></i>
                            <input type="range" id="blur-slider" min="0" max="15" step="1" value="0" class="w-full h-1.5 bg-black/60 rounded-lg appearance-none cursor-pointer border border-white/5 shadow-inner" oninput="updateWallpaperBlur(this.value)" onchange="updateWallpaperBlur(this.value)">
                            <i class="fa-solid fa-droplet text-xs theme-text" style="color: var(--theme-prim); filter: drop-shadow(0 0 5px var(--theme-prim));"></i>
                        </div>
                    </div>

                    <div class="flex flex-col py-4 border-b border-white/5">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-4">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center border theme-icon-box" style="background-color: color-mix(in srgb, var(--theme-prim) 10%, transparent); border-color: color-mix(in srgb, var(--theme-prim) 20%, transparent);">
                                    <i class="fa-solid fa-sparkles text-sm theme-text" style="color: var(--theme-prim);"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-xs font-black text-[var(--text-main)] tracking-widest uppercase">PARTÍCULAS</span>
                                    <span class="text-[9px] text-[var(--text-muted)]">Efecto polvo estelar.</span>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="toggle_particles" class="sr-only peer" checked onchange="toggleParticles(this.checked)">
                                <div class="w-11 h-6 bg-black/60 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-[var(--text-main)] after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white/50 peer-checked:after:bg-[var(--text-main)] after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all border border-white/10 shadow-[inset_0_2px_4px_rgba(0,0,0,0.5)]" style="--tw-peer-checked-bg: var(--theme-prim); background-color: var(--tw-peer-checked-bg, rgba(0,0,0,0.6));"></div>
                            </label>
                        </div>
                        <div class="flex items-center gap-4 pl-12 pr-2">
                            <i class="fa-solid fa-minus text-[var(--text-muted)] opacity-50 text-[10px]"></i>
                            <input type="range" id="particles-slider" min="50" max="500" step="50" value="400" class="w-full h-1.5 bg-black/60 rounded-lg appearance-none cursor-pointer border border-white/5 shadow-inner" oninput="updateParticlesCount(this.value)" onchange="updateParticlesCount(this.value)">
                            <i class="fa-solid fa-plus text-xs theme-text" style="color: var(--theme-prim); filter: drop-shadow(0 0 5px var(--theme-prim));"></i>
                        </div>
                    </div>

                    <div class="flex items-center justify-between py-4 border-b border-white/5">
                        <div class="flex items-center gap-4">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center border theme-icon-box" style="background-color: color-mix(in srgb, var(--theme-prim) 10%, transparent); border-color: color-mix(in srgb, var(--theme-prim) 20%, transparent);">
                                <i class="fa-solid fa-lock text-sm theme-text" style="color: var(--theme-prim);"></i>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-xs font-black text-[var(--text-main)] tracking-widest uppercase" data-i18n="set_wake_title">Pantalla Activa</span>
                                <span class="text-[9px] text-[var(--text-muted)]" data-i18n="set_wake_desc">Evita que se apague.</span>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="toggle_wakelock" class="sr-only peer" onchange="if(this.checked) requestWakeLock(); else releaseWakeLock();">
                            <div class="w-11 h-6 bg-black/60 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-[var(--text-main)] after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white/50 peer-checked:after:bg-[var(--text-main)] after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all border border-white/10 shadow-[inset_0_2px_4px_rgba(0,0,0,0.5)]" style="--tw-peer-checked-bg: var(--theme-prim); background-color: var(--tw-peer-checked-bg, rgba(0,0,0,0.6));"></div>
                        </label>
                    </div>

                    <div class="flex items-center justify-between py-4 border-b border-white/5">
                        <div class="flex items-center gap-4">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center border theme-icon-box" style="background-color: color-mix(in srgb, var(--theme-prim) 10%, transparent); border-color: color-mix(in srgb, var(--theme-prim) 20%, transparent);">
                                <i class="fa-solid fa-message text-sm theme-text" style="color: var(--theme-prim);"></i>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-xs font-black text-[var(--text-main)] tracking-widest uppercase" data-i18n="set_notif_title">NOTIFICACIONES</span>
                                <span class="text-[9px] text-[var(--text-muted)]" data-i18n="set_notif_desc">Alertas flotantes.</span>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="toggle_notifications" class="sr-only peer" checked>
                            <div class="w-11 h-6 bg-black/60 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-[var(--text-main)] after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white/50 peer-checked:after:bg-[var(--text-main)] after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all border border-white/10 shadow-[inset_0_2px_4px_rgba(0,0,0,0.5)]" style="--tw-peer-checked-bg: var(--theme-prim); background-color: var(--tw-peer-checked-bg, rgba(0,0,0,0.6));"></div>
                        </label>
                    </div>

                    <div class="flex items-center justify-between py-4 border-b border-white/5">
                        <div class="flex items-center gap-4 w-full">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center border theme-icon-box shrink-0" style="background-color: color-mix(in srgb, var(--theme-prim) 10%, transparent); border-color: color-mix(in srgb, var(--theme-prim) 20%, transparent);">
                                <i class="fa-solid fa-volume-low text-sm theme-text" style="color: var(--theme-prim);"></i>
                            </div>
                            <div class="flex flex-col flex-1 pr-4">
                                <div class="flex justify-between items-center mb-2">
                                    <div>
                                        <span class="text-xs font-black text-[var(--text-main)] block tracking-widest uppercase" data-i18n="set_vol_title">Volumen / Sonidos</span>
                                        <span class="text-[9px] text-[var(--text-muted)]" data-i18n="set_vol_desc">Ajusta la intensidad.</span>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer ml-2">
                                        <input type="checkbox" id="toggle_sound" class="sr-only peer" checked>
                                        <div class="w-11 h-6 bg-black/60 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-[var(--text-main)] after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white/50 peer-checked:after:bg-[var(--text-main)] after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all border border-white/10 shadow-[inset_0_2px_4px_rgba(0,0,0,0.5)]" style="--tw-peer-checked-bg: var(--theme-prim); background-color: var(--tw-peer-checked-bg, rgba(0,0,0,0.6));"></div>
                                    </label>
                                </div>
                                <input type="range" id="volume_slider" min="0" max="1" step="0.1" value="0.5" class="w-full h-1.5 bg-black/60 rounded-lg appearance-none cursor-pointer border border-white/5 shadow-inner" oninput="AudioEngine.updateVol(this.value)" onchange="AudioEngine.updateVol(this.value)">
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between py-4 border-b border-white/5">
                        <div class="flex items-center gap-4">
                            <div class="w-8 h-8 rounded-full bg-red-500/10 flex items-center justify-center border border-red-500/20"><i class="fa-solid fa-broom text-red-500 text-sm"></i></div>
                            <div class="flex flex-col">
                                <span class="text-xs font-black text-[var(--text-main)] tracking-widest uppercase">LIMPIAR TEMPORALES</span>
                                <span class="text-[9px] text-[var(--text-muted)]" data-i18n="set_temp_desc">Libera caché del servidor.</span>
                            </div>
                        </div>
                        <button onclick="limpiarTemporales()" class="w-10 h-10 rounded-xl bg-black/50 text-red-400 flex items-center justify-center hover:bg-red-500 hover:text-[var(--text-main)] transition-all border border-red-500/30 active:scale-95 shrink-0">
                            <i class="fa-solid fa-trash-can text-sm"></i>
                        </button>
                    </div>

                    <div class="flex items-center justify-between py-4">
                        <div class="flex items-center gap-4">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center border theme-icon-box" style="background-color: color-mix(in srgb, var(--theme-prim) 10%, transparent); border-color: color-mix(in srgb, var(--theme-prim) 20%, transparent);">
                                <i class="fa-solid fa-cloud-arrow-down text-sm theme-text" style="color: var(--theme-prim);"></i>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-xs font-black text-[var(--text-main)] tracking-widest uppercase">ACTUALIZACIÓN OTA</span>
                                <span class="text-[9px] text-[var(--text-muted)]">Buscar e instalar nueva versión.</span>
                            </div>
                        </div>
                        <button onclick="buscarActualizacionOTA()" class="w-10 h-10 rounded-xl bg-black/50 flex items-center justify-center transition-all border active:scale-95 shrink-0" style="color: var(--theme-prim); border-color: color-mix(in srgb, var(--theme-prim) 30%, transparent);">
                            <i class="fa-solid fa-rotate text-sm"></i>
                        </button>
                    </div>
                </div>
                
                <div class="flex flex-col mt-4 mb-4 glass-panel rounded-3xl p-4 border-white/10">
                    <div class="flex items-center gap-4 mb-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center border theme-icon-box" style="background-color: color-mix(in srgb, var(--theme-prim) 10%, transparent); border-color: color-mix(in srgb, var(--theme-prim) 20%, transparent);">
                            <i class="fa-solid fa-palette text-sm theme-text" style="color: var(--theme-prim);"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xs font-black text-[var(--text-main)] tracking-widest uppercase">Tema Visual</span>
                            <span class="text-[9px] text-[var(--text-muted)]">Desliza para elegir.</span>
                        </div>
                    </div>
                    <div class="flex gap-2 overflow-x-auto custom-scrollbar pb-2 px-1" id="theme-scroll-container">
                        <button onclick="changeTheme('cyberpunk', this)" class="theme-btn bg-white/5 border border-white/10 text-[var(--text-muted)] hover:text-[var(--text-main)] text-[9px] font-black py-2 px-4 rounded-lg transition-all shrink-0 whitespace-nowrap">CYBERPUNK</button>
                        <button onclick="changeTheme('glass', this)" class="theme-btn bg-white/5 border border-white/10 text-[var(--text-muted)] hover:text-[var(--text-main)] text-[9px] font-black py-2 px-4 rounded-lg transition-all shrink-0 whitespace-nowrap">PURE GLASS</button>
                        <button onclick="changeTheme('ps5', this)" class="theme-btn active bg-white/5 border border-white/10 text-[var(--text-muted)] hover:text-[var(--text-main)] text-[9px] font-black py-2 px-4 rounded-lg transition-all shrink-0 whitespace-nowrap">PS5 CLASSIC</button>
                        
                        <button onclick="changeTheme('neumorphism-light', this)" class="theme-btn bg-white/5 border border-white/10 text-[var(--text-muted)] hover:text-[var(--text-main)] text-[9px] font-black py-2 px-4 rounded-lg transition-all shrink-0 whitespace-nowrap">NEUMORPHISM LIGHT</button>
                        <button onclick="changeTheme('neumorphism-dark', this)" class="theme-btn bg-white/5 border border-white/10 text-[var(--text-muted)] hover:text-[var(--text-main)] text-[9px] font-black py-2 px-4 rounded-lg transition-all shrink-0 whitespace-nowrap">NEUMORPHISM DARK</button>

                        <button onclick="changeTheme('spiderman', this)" class="theme-btn bg-white/5 border border-white/10 text-[var(--text-muted)] hover:text-[var(--text-main)] text-[9px] font-black py-2 px-4 rounded-lg transition-all shrink-0 whitespace-nowrap">SPIDER-MAN</button>
                        <button onclick="changeTheme('batman', this)" class="theme-btn bg-white/5 border border-white/10 text-[var(--text-muted)] hover:text-[var(--text-main)] text-[9px] font-black py-2 px-4 rounded-lg transition-all shrink-0 whitespace-nowrap">BATMAN</button>
                        <button onclick="changeTheme('gow', this)" class="theme-btn bg-white/5 border border-white/10 text-[var(--text-muted)] hover:text-[var(--text-main)] text-[9px] font-black py-2 px-4 rounded-lg transition-all shrink-0 whitespace-nowrap">GOD OF WAR</button>
                        <button onclick="changeTheme('bloodborne', this)" class="theme-btn bg-white/5 border border-white/10 text-[var(--text-muted)] hover:text-[var(--text-main)] text-[9px] font-black py-2 px-4 rounded-lg transition-all shrink-0 whitespace-nowrap">BLOODBORNE</button>
                        <button onclick="changeTheme('hollowknight', this)" class="theme-btn bg-white/5 border border-white/10 text-[var(--text-muted)] hover:text-[var(--text-main)] text-[9px] font-black py-2 px-4 rounded-lg transition-all shrink-0 whitespace-nowrap">HOLLOW KNIGHT</button>
                        <button onclick="changeTheme('got', this)" class="theme-btn bg-white/5 border border-white/10 text-[var(--text-muted)] hover:text-[var(--text-main)] text-[9px] font-black py-2 px-4 rounded-lg transition-all shrink-0 whitespace-nowrap">GHOST OF TSUSHIMA</button>
                        <button onclick="changeTheme('matrix', this)" class="theme-btn bg-white/5 border border-white/10 text-[var(--text-muted)] hover:text-[var(--text-main)] text-[9px] font-black py-2 px-4 rounded-lg transition-all shrink-0 whitespace-nowrap">THE MATRIX</button>
                        <button onclick="changeTheme('vicecity', this)" class="theme-btn bg-white/5 border border-white/10 text-[var(--text-muted)] hover:text-[var(--text-main)] text-[9px] font-black py-2 px-4 rounded-lg transition-all shrink-0 whitespace-nowrap">GTA VICE CITY</button>
                        <button onclick="changeTheme('evangelion', this)" class="theme-btn bg-white/5 border border-white/10 text-[var(--text-muted)] hover:text-[var(--text-main)] text-[9px] font-black py-2 px-4 rounded-lg transition-all shrink-0 whitespace-nowrap">EVANGELION</button>
                    </div>
                </div>

                <div class="flex flex-col items-center justify-center mt-6 mb-10 opacity-60 shrink-0">
                    <i class="fa-brands fa-playstation text-[3.5rem] mb-4 text-[var(--text-main)] drop-shadow-[0_0_15px_rgba(255,255,255,0.4)]"></i>
                    <span class="text-[12px] font-black tracking-widest text-[var(--text-main)] mb-1 footer-sig drop-shadow-[0_0_2px_rgba(0,0,0,0.8)]">GoldHen Manager v2.1</span>
                    <span class="text-[9px] font-mono tracking-widest uppercase" style="color: var(--theme-prim);">Developed by SeBaS</span>
                </div>
            </div>
        </main>

        <nav class="dock-nav">
            <button class="dock-item active" onclick="switchTab('tab-biblioteca', this, 0)" title="Biblioteca"><i class="fa-solid fa-gamepad"></i></button>
            <button class="dock-item" onclick="switchTab('tab-ftp', this, 1)" title="Transferir"><i class="fa-solid fa-cloud-arrow-up"></i></button>
            <button class="dock-item" onclick="switchTab('tab-icons', this, 2)" title="Portadas"><i class="fa-solid fa-palette"></i></button>
            <button class="dock-item" onclick="switchTab('tab-explorer', this, 3)" title="Explorar"><i class="fa-solid fa-folder-tree"></i></button>
            <button class="dock-item" onclick="switchTab('tab-payloads', this, 4)" title="Payloads"><i class="fa-solid fa-microchip"></i></button>
            <button class="dock-item" onclick="switchTab('tab-settings', this, 5)" title="Ajustes"><i class="fa-solid fa-gear"></i></button>
        </nav>
    </div>

    <div id="overlay-global" class="overlay-sheet" onclick="cerrarTodo()"></div>
    <div id="overlay-sheet" class="overlay-sheet" onclick="closeItemOptions()"></div>

    <div id="file-viewer-modal" class="hidden fixed inset-0 z-[250] bg-[#05050a]/95 backdrop-blur-md flex flex-col transition-opacity duration-300 opacity-0">
        <div class="flex justify-between items-center p-4 border-b bg-black/50" style="border-color: color-mix(in srgb, var(--theme-prim) 20%, transparent);">
            <span id="file-viewer-title" class="font-bold text-sm truncate pr-4" style="color: var(--theme-prim); filter: drop-shadow(0 0 5px var(--theme-prim));">Archivo...</span>
            <button onclick="closeFileViewer()" class="w-8 h-8 rounded-full bg-white/10 text-[var(--text-main)] flex items-center justify-center hover:bg-red-500 transition-colors"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="file-viewer-content" class="flex-1 overflow-auto p-4 custom-scrollbar flex items-center justify-center relative"></div>
    </div>

    <div id="bottom-sheet" class="bottom-sheet">
        <div class="w-12 h-1 rounded-full mx-auto mt-3 mb-2" style="background-color: color-mix(in srgb, var(--theme-prim) 50%, transparent); box-shadow: 0 0 5px color-mix(in srgb, var(--theme-prim) 50%, transparent);"></div>
        <div class="px-6 pb-6">
            <h4 id="sheet-title" class="text-[var(--text-main)] font-black text-sm mb-4 truncate border-b border-white/10 pb-4 tracking-wide">...</h4>
            <div class="flex flex-col gap-2">
                <button id="btn-view-file" onclick="viewCurrentFile()" class="hidden flex items-center gap-4 p-4 rounded-2xl bg-black/40 border border-white/5 hover:border-white/20 transition-colors text-left mb-2" style="color: var(--theme-prim);"><i class="fa-solid fa-eye w-5 text-center"></i> <span class="font-bold text-xs uppercase tracking-widest" data-i18n="opt_view">Ver Archivo</span></button>
                <button id="btn-download-file" onclick="downloadCurrentFile()" class="hidden flex items-center gap-4 p-4 rounded-2xl bg-black/40 border border-white/5 hover:border-green-500/50 transition-colors text-green-400 text-left mb-2"><i class="fa-solid fa-download w-5 text-center"></i> <span class="font-bold text-xs uppercase tracking-widest" data-i18n="opt_download">Descargar al celular</span></button>
                <button onclick="renameCurrentItem()" class="flex items-center gap-4 p-4 rounded-2xl hover:bg-white/5 border border-transparent hover:border-white/10 text-[var(--text-main)] text-left transition-colors"><i class="fa-solid fa-pen w-5 text-center text-[var(--text-muted)] opacity-50"></i> <span class="font-bold text-xs uppercase tracking-widest" data-i18n="opt_rename">Renombrar</span></button>
                <button onclick="cutCurrentItem()" class="flex items-center gap-4 p-4 rounded-2xl hover:bg-white/5 border border-transparent hover:border-white/10 text-[var(--text-main)] text-left transition-colors"><i class="fa-solid fa-scissors w-5 text-center text-[var(--text-muted)] opacity-50"></i> <span class="font-bold text-xs uppercase tracking-widest" data-i18n="opt_move">Mover</span></button>
                <button onclick="deleteCurrentItem()" class="flex items-center gap-4 p-4 rounded-2xl bg-black/40 hover:bg-red-900/30 border border-white/5 hover:border-red-500/30 text-red-400 text-left mt-2 transition-colors"><i class="fa-solid fa-trash w-5 text-center"></i> <span class="font-bold text-xs uppercase tracking-widest" data-i18n="opt_delete">Eliminar</span></button>
            </div>
        </div>
    </div>

    <div id="sheet-opciones" class="bottom-sheet z-[60]">
        <div class="w-12 h-1 rounded-full mx-auto mt-3 mb-2" style="background-color: color-mix(in srgb, var(--theme-prim) 50%, transparent); box-shadow: 0 0 5px color-mix(in srgb, var(--theme-prim) 50%, transparent);"></div>
        <div class="px-6 pb-6 pt-2">
            
            <div class="flex gap-4 mb-5 border-b border-white/10 pb-5">
                <img id="opt-game-img" src="" class="w-16 h-16 rounded-xl object-cover border border-white/10 shadow-[0_0_15px_rgba(0,0,0,0.8)] shrink-0 bg-black/50">
                <div class="flex flex-col justify-center overflow-hidden w-full">
                    <h4 id="opt-game-title" class="text-[var(--text-main)] font-black text-sm uppercase tracking-wider truncate drop-shadow-[0_0_5px_rgba(0,0,0,0.8)]">...</h4>
                    <div class="flex items-center gap-2 mt-1">
                        <span id="opt-game-cusa" class="text-[9px] font-mono font-bold tracking-widest bg-black/50 px-1.5 py-0.5 rounded border backdrop-blur-md" style="color: var(--theme-prim); border-color: color-mix(in srgb, var(--theme-prim) 30%, transparent); box-shadow: 0 0 8px color-mix(in srgb, var(--theme-prim) 20%, transparent);">...</span>
                        <span id="opt-game-version" class="text-[9px] text-[var(--text-muted)] font-mono bg-black/40 px-1.5 py-0.5 rounded border border-white/10 drop-shadow-[0_0_2px_rgba(0,0,0,0.8)]">...</span>
                    </div>
                    <div class="flex items-center justify-between mt-2.5">
                        <div class="flex items-center gap-1.5 text-[10px] text-[var(--text-muted)] drop-shadow-[0_0_2px_rgba(0,0,0,0.8)]">
                            <i class="fa-solid fa-hard-drive" style="color: color-mix(in srgb, var(--theme-prim) 50%, transparent);"></i> <span id="opt-game-size" class="font-mono font-bold">...</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-[10px] text-[var(--text-muted)] drop-shadow-[0_0_2px_rgba(0,0,0,0.8)]">
                            <i class="fa-solid fa-folder-tree text-[var(--text-muted)] opacity-50"></i> <span id="opt-game-loc" class="font-mono truncate max-w-[100px]">...</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-black/60 p-3 rounded-xl border border-white/5 mb-5 shadow-inner backdrop-blur-md">
                <span class="text-[8px] font-black text-[var(--text-muted)] uppercase tracking-widest block mb-2" data-i18n="move_category">Mover a categoría:</span>
                <div id="selector-categorias" class="flex gap-2 overflow-x-auto custom-scrollbar pb-1"></div>
            </div>

            <div class="flex flex-col gap-1">
                <button onclick="iniciarBackupSaves()" class="w-full flex items-center gap-4 p-3.5 rounded-xl hover:bg-white/5 border border-transparent hover:border-white/10 transition-colors group">
                    <i class="fa-solid fa-floppy-disk group-hover:scale-110 transition-transform w-5 text-center text-lg" style="color: var(--theme-prim); filter: drop-shadow(0 0 5px color-mix(in srgb, var(--theme-prim) 40%, transparent));"></i>
                    <span class="text-[10px] uppercase font-black tracking-widest text-[var(--text-muted)] group-hover:text-[var(--text-main)]" data-i18n="opt_backup_saves">Backup de Partidas (Saves)</span>
                </button>
                <button onclick="abrirGaleriaJuego()" class="w-full flex items-center gap-4 p-3.5 rounded-xl hover:bg-white/5 border border-transparent hover:border-white/10 transition-colors group">
                    <i class="fa-solid fa-images group-hover:scale-110 transition-transform w-5 text-center text-lg" style="color: var(--theme-prim); filter: drop-shadow(0 0 5px color-mix(in srgb, var(--theme-prim) 40%, transparent));"></i>
                    <span class="text-[10px] uppercase font-black tracking-widest text-[var(--text-muted)] group-hover:text-[var(--text-main)]" data-i18n="opt_gallery_caps">Galería de Capturas</span>
                    <span id="opt-caps-badge" class="ml-auto bg-black/60 text-[9px] font-black px-2 py-0.5 rounded-full border border-white/10 shadow-inner hidden font-mono" style="color: var(--theme-prim);">0</span>
                </button>
                <button onclick="simularRedireccionModding()" class="w-full flex items-center gap-4 p-3.5 rounded-xl hover:bg-white/5 border border-transparent hover:border-white/10 transition-colors group">
                    <i class="fa-solid fa-wand-magic-sparkles group-hover:scale-110 transition-transform w-5 text-center text-lg" style="color: var(--theme-prim); filter: drop-shadow(0 0 5px color-mix(in srgb, var(--theme-prim) 40%, transparent));"></i>
                    <span class="text-[10px] uppercase font-black tracking-widest text-[var(--text-muted)] group-hover:text-[var(--text-main)]" data-i18n="opt_modding">Personalizar Portada</span>
                </button>
                <button onclick="abrirMenuDLCs()" class="w-full flex items-center gap-4 p-3.5 rounded-xl hover:bg-white/5 border border-transparent hover:border-white/10 transition-colors group">
                    <i class="fa-solid fa-puzzle-piece group-hover:scale-110 transition-transform w-5 text-center text-lg" style="color: var(--theme-prim); filter: drop-shadow(0 0 5px color-mix(in srgb, var(--theme-prim) 40%, transparent));"></i>
                    <span class="text-[10px] uppercase font-black tracking-widest text-[var(--text-muted)] group-hover:text-[var(--text-main)]" data-i18n="opt_manage_dlc">Gestionar DLCs / Updates</span>
                </button>
                
                <div class="h-px bg-white/10 my-1"></div>
                
                <button onclick="borrarJuegoDeBiblioteca()" class="w-full flex items-center gap-4 p-3.5 rounded-xl hover:bg-red-900/20 transition-colors group border border-transparent hover:border-red-500/30">
                    <i class="fa-solid fa-eye-slash text-red-500 group-hover:scale-110 transition-transform w-5 text-center text-lg drop-shadow-[0_0_5px_rgba(239,68,68,0.4)]"></i>
                    <span class="text-[10px] uppercase font-black tracking-widest text-red-400" data-i18n="opt_remove_lib">Quitar de Biblioteca</span>
                </button>
            </div>
        </div>
    </div>

    <div id="sheet-dlcs" class="bottom-sheet z-[60]">
        <div class="w-12 h-1 rounded-full mx-auto mt-3 mb-2" style="background-color: color-mix(in srgb, var(--theme-prim) 50%, transparent); box-shadow: 0 0 5px color-mix(in srgb, var(--theme-prim) 50%, transparent);"></div>
        <div class="px-6 pb-6">
            <div class="flex justify-between items-center mb-4 border-b border-white/10 pb-4">
                <div>
                    <h4 class="text-[var(--text-main)] font-black text-sm uppercase tracking-widest" data-i18n="dlc_manager_title">Gestor de DLCs & Updates</h4>
                    <span id="dlc-game-cusa" class="text-[9px] font-mono tracking-widest" style="color: var(--theme-prim);">...</span>
                </div>
                <button onclick="volverAOpcionesDesde('sheet-dlcs')" class="w-8 h-8 bg-white/10 rounded-full flex items-center justify-center text-[var(--text-muted)] hover:text-[var(--text-main)] hover:bg-white/20 transition-colors"><i class="fa-solid fa-arrow-left"></i></button>
            </div>
            <div id="dlc-list-container" class="flex flex-col gap-3 max-h-[50vh] overflow-y-auto custom-scrollbar pr-1 pb-4">
            </div>
        </div>
    </div>

    <div id="sheet-capturas" class="bottom-sheet z-[60]">
        <div class="w-12 h-1 rounded-full mx-auto mt-3 mb-2" style="background-color: color-mix(in srgb, var(--theme-prim) 50%, transparent); box-shadow: 0 0 5px color-mix(in srgb, var(--theme-prim) 50%, transparent);"></div>
        <div class="px-6 pb-6">
            <div class="flex justify-between items-center mb-4 border-b border-white/10 pb-4">
                <div>
                    <h4 class="text-[var(--text-main)] font-black text-sm uppercase tracking-widest" data-i18n="caps_ps4_title">Capturas PS4</h4>
                    <span id="capturas-game-cusa" class="text-[9px] font-mono tracking-widest" style="color: var(--theme-prim);">...</span>
                </div>
                <button id="btn-cerrar-capturas" onclick="volverAOpcionesDesde('sheet-capturas')" class="w-8 h-8 bg-white/10 rounded-full flex items-center justify-center text-[var(--text-muted)] hover:text-[var(--text-main)] hover:bg-white/20 transition-colors"><i class="fa-solid fa-arrow-left"></i></button>
            </div>
            <div id="capturas-grid" class="grid grid-cols-2 gap-3 max-h-[60vh] overflow-y-auto custom-scrollbar pr-1 pb-4">
            </div>
        </div>
    </div>

    <div id="custom-modal" class="fixed inset-0 bg-[#05050a]/90 backdrop-blur-md z-[100] hidden flex items-center justify-center transition-opacity opacity-0 px-4">
        <div id="modal-card" class="bg-black/80 backdrop-blur-lg border p-8 rounded-[2rem] w-full max-w-sm text-center transform scale-95 transition-transform relative overflow-hidden glass-panel" style="border-color: color-mix(in srgb, var(--theme-prim) 30%, transparent); box-shadow: 0 0 40px color-mix(in srgb, var(--theme-prim) 15%, transparent);">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-[var(--theme-prim)] via-[var(--text-main)] to-[var(--theme-prim)]"></div>
            <div id="modal-icon" class="w-20 h-20 mx-auto rounded-full border flex items-center justify-center mb-6 relative bg-black/50" style="border-color: color-mix(in srgb, var(--theme-prim) 40%, transparent); box-shadow: 0 0 30px color-mix(in srgb, var(--theme-prim) 20%, transparent);"></div>
            <h3 id="modal-title" class="text-[var(--text-main)] font-black text-xl tracking-widest mb-2 uppercase drop-shadow-[0_0_5px_rgba(255,255,255,0.4)]" data-i18n="modal_loading">CARGANDO</h3>
            <p id="modal-text" class="text-[var(--text-muted)] text-xs font-medium px-4 leading-relaxed h-10 overflow-hidden text-ellipsis" data-i18n="modal_wait">Por favor espera...</p>
            <div id="modal-progress-container" class="mt-8 hidden">
                <div class="flex justify-between text-[10px] font-black mb-2 tracking-widest px-1 uppercase opacity-70" style="color: var(--theme-prim);">
                    <span id="modal-percentage">0%</span>
                    <span id="modal-bytes">0 / 0 GB</span>
                </div>
                <div class="w-full bg-black/50 rounded-full h-2.5 overflow-hidden border border-white/10 p-0.5 shadow-inner">
                    <div id="modal-progress-bar" class="h-full rounded-full transition-all duration-300 w-0 relative overflow-hidden" style="background: var(--theme-prim); box-shadow: 0 0 10px color-mix(in srgb, var(--theme-prim) 50%, transparent);">
                        <div class="absolute inset-0 bg-white/20 animate-[shimmer_1s_infinite] -skew-x-12"></div>
                    </div>
                </div>
                <div class="flex justify-between text-[9px] text-[var(--text-muted)] mt-3 font-mono tracking-widest px-1">
                    <span id="modal-speed"><i class="fa-solid fa-bolt" style="color: color-mix(in srgb, var(--theme-prim) 50%, transparent);"></i> -- MB/s</span>
                    <span id="modal-eta"><i class="fa-solid fa-clock" style="color: color-mix(in srgb, var(--theme-prim) 50%, transparent);"></i> ETA: --:--</span>
                </div>
            </div>
            <div id="modal-controls" class="mt-8 flex gap-3 hidden">
                <button id="modal-cancel-btn" onclick="cancelarEnvio()" class="flex-1 bg-red-900/20 hover:bg-red-900/40 border border-red-500/30 text-red-400 rounded-2xl py-3.5 font-black text-[10px] tracking-widest uppercase transition-all active:scale-95" data-i18n="modal_cancel">CANCELAR</button>
                <button id="modal-action-btn" onclick="togglePauseResume()" class="flex-1 border bg-black/50 rounded-2xl py-3.5 font-black text-[10px] tracking-widest uppercase transition-all active:scale-95" style="border-color: color-mix(in srgb, var(--theme-prim) 30%, transparent); color: var(--theme-prim);" data-i18n="modal_pause">PAUSAR</button>
            </div>
            <button id="modal-close-btn" onclick="closeCustomModal()" class="mt-8 w-full border bg-black/50 rounded-2xl py-3.5 font-black text-[10px] tracking-widest uppercase transition-all active:scale-95 hidden" style="border-color: color-mix(in srgb, var(--theme-prim) 30%, transparent); color: var(--theme-prim);" data-i18n="modal_close">CERRAR</button>
        </div>
    </div>

    <div id="ps5-dialog" class="fixed inset-0 bg-[#05050a]/90 backdrop-blur-md z-[110] hidden flex items-center justify-center transition-opacity opacity-0 px-4">
        <div id="ps5-dialog-card" class="bg-black/80 backdrop-blur-md border border-white/10 p-6 rounded-3xl shadow-[0_0_30px_rgba(0,0,0,0.8)] w-full max-w-sm transform scale-95 transition-transform glass-panel">
            <div class="flex items-start gap-4 mb-4">
                <div id="ps5-dialog-icon" class="w-12 h-12 rounded-full bg-white/5 border border-white/10 flex items-center justify-center shrink-0"></div>
                <div>
                    <h3 id="ps5-dialog-title" class="text-[var(--text-main)] font-black text-sm tracking-widest uppercase mb-1 drop-shadow-[0_0_5px_rgba(255,255,255,0.3)]">Título</h3>
                    <p id="ps5-dialog-text" class="text-[var(--text-muted)] text-[11px] leading-relaxed">Mensaje</p>
                </div>
            </div>
            <input type="text" id="ps5-dialog-input" class="w-full bg-black/60 border border-white/10 rounded-xl px-4 py-3 text-[var(--text-main)] text-xs mb-6 hidden focus:outline-none transition-colors shadow-inner" style="border-color: color-mix(in srgb, var(--theme-prim) 50%, transparent);" autocomplete="off">
            <div class="flex gap-2 mt-6">
                <button id="ps5-dialog-btn-cancel" class="flex-1 bg-white/5 hover:bg-white/10 text-[var(--text-muted)] hover:text-[var(--text-main)] rounded-xl py-3.5 font-black text-[10px] tracking-widest uppercase transition-all hidden" data-i18n="modal_cancel">CANCELAR</button>
                <button id="ps5-dialog-btn-confirm" class="flex-1 text-black rounded-xl py-3.5 font-black text-[10px] tracking-widest uppercase transition-all active:scale-95 border" style="background-color: var(--theme-prim); border-color: var(--theme-prim); box-shadow: 0 0 15px color-mix(in srgb, var(--theme-prim) 40%, transparent);" data-i18n="modal_accept">ACEPTAR</button>
            </div>
        </div>
    </div>

    <div id="lightbox-modal" class="fixed inset-0 z-[300] bg-black/95 hidden flex flex-col transition-opacity opacity-0">
        <div class="flex justify-between items-center p-4 absolute top-0 left-0 w-full z-10 bg-gradient-to-b from-black/80 to-transparent">
            <span id="lightbox-title" class="text-[10px] font-mono text-[var(--text-muted)] truncate pr-4 drop-shadow-md"></span>
            <button onclick="cerrarLightbox()" class="w-10 h-10 rounded-full bg-white/10 text-[var(--text-main)] flex items-center justify-center hover:bg-red-500 transition-colors backdrop-blur-md border border-white/10"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <div class="flex-1 w-full h-full flex items-center justify-center overflow-hidden relative" onclick="cerrarLightboxSiFondo(event)">
            <img id="lightbox-img" src="" class="max-w-full max-h-full object-contain zoom-anim transform scale-100 cursor-zoom-in" onclick="toggleZoom(this)">
        </div>
        <div class="absolute bottom-8 left-0 w-full flex justify-center z-10 pointer-events-none">
            <a id="lightbox-download" href="" download class="px-6 py-3 rounded-full text-black font-black text-[10px] tracking-widest flex items-center gap-2 active:scale-95 transition-transform pointer-events-auto" style="background-color: var(--theme-prim); box-shadow: 0 0 15px color-mix(in srgb, var(--theme-prim) 40%, transparent);"><i class="fa-solid fa-download text-sm"></i> DESCARGAR</a>
        </div>
    </div>






























<script>
        // ==========================================
        // PROTECCIÓN ANTI-CURIOSOS Y FIRMA
        // ==========================================
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('keydown', e => { 
            if(e.keyCode === 123 || (e.ctrlKey && e.shiftKey && (e.keyCode === 73 || e.keyCode === 74)) || (e.ctrlKey && e.keyCode === 85)) { 
                e.preventDefault(); return false; 
            } 
        });
        console.log('%c' + atob('R29sZEhlbiBNYW5hZ2VyIFYyLjEgfCBEZXZlbG9wZWQgYnkgU2VCYVM='), 'color:#22d3ee; font-size:18px; font-weight:900; text-shadow: 0 0 10px rgba(34,211,238,0.5);');

        // ==========================================
        // 1. PARTICULAS DINÁMICAS (ESTRELLAS BLANCAS)
        // ==========================================
        const canvas = document.getElementById('stardust'); 
        const ctx = canvas.getContext('2d'); 
        let particles = []; 
        let isExploding = false;
        
        let maxParticles = parseInt(localStorage.getItem('ps4_particles_count')) || 400;
        let particlesEnabled = localStorage.getItem('ps4_particles_enabled') !== 'false';

        function resize() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
        window.addEventListener('resize', resize); resize();
        
        class Particle {
            constructor() { this.reset(); }
            reset() { 
                this.x = Math.random() * canvas.width; 
                this.y = Math.random() * canvas.height; 
                this.size = Math.random() * 1.5 + 0.5; // Tamaño más sutil (Estrellas)
                this.speedX = (Math.random() - 0.5) * 0.05; // Movimiento casi estático
                this.speedY = (Math.random() - 0.5) * 0.05; 
                this.baseOpacity = Math.random() * 0.5 + 0.2; 
                this.twinkleSpeed = Math.random() * 0.02 + 0.005; // Velocidad de parpadeo
                // Color aleatorio entre blanco puro y un celeste estelar muy suave
                this.color = Math.random() > 0.8 ? '187, 222, 251' : '255, 255, 255'; 
            }
            update() { 
                if (isExploding) { 
                    let dx = this.x - canvas.width / 2; let dy = this.y - canvas.height / 2; 
                    this.x += dx * 0.06; this.y += dy * 0.06; this.size *= 1.01; this.baseOpacity -= 0.03; 
                    if(this.baseOpacity <= 0) { this.reset(); this.baseOpacity = 0; } 
                } else { 
                    this.x += this.speedX; this.y += this.speedY; 
                    // Efecto Twinkle (Parpadeo) con seno del tiempo
                    this.opacity = this.baseOpacity + Math.sin(Date.now() * this.twinkleSpeed) * 0.3;
                    if (this.opacity < 0) this.opacity = 0;
                    if (this.x < 0 || this.x > canvas.width || this.y < 0 || this.y > canvas.height) this.reset(); 
                } 
            }
            draw() { 
                ctx.fillStyle = `rgba(${this.color}, ${Math.max(0, this.opacity)})`; 
                ctx.beginPath(); ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2); ctx.fill(); 
            }
        }
        
        function initParticles() {
            particles = [];
            if(particlesEnabled) {
                for (let i = 0; i < maxParticles; i++) particles.push(new Particle());
            }
        }

        function animateParticles() { 
            ctx.clearRect(0, 0, canvas.width, canvas.height); 
            if(particlesEnabled) {
                particles.forEach(p => { p.update(); p.draw(); }); 
            }
            requestAnimationFrame(animateParticles); 
        }

        // ==========================================
        // AVATAR ANIMADO (GIF) LOCAL
        // ==========================================
        function handleAvatarUpload(event) {
            const file = event.target.files[0];
            if(file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const base64 = e.target.result;
                    try {
                        localStorage.setItem('ps4_custom_avatar', base64);
                        aplicarAvatarLocal(base64);
                        if(typeof ps5Notification === 'function') ps5Notification("PERFIL", "Avatar actualizado con éxito.", "fa-user-astronaut");
                    } catch(err) {
                        if(typeof ps5Alert === 'function') ps5Alert("ESPACIO INSUFICIENTE", "El GIF es muy pesado. Intenta con uno de menor resolución.", "fa-triangle-exclamation");
                    }
                };
                reader.readAsDataURL(file);
            }
        }

        function aplicarAvatarLocal(base64) {
            const av = document.getElementById('profile-avatar'), ini = document.getElementById('profile-initial');
            if(av) {
                if(base64) {
                    av.style.backgroundImage = `url('${base64}')`;
                    if(ini) ini.classList.add('hidden');
                } else {
                    av.style.backgroundImage = '';
                    if(ini) ini.classList.remove('hidden');
                }
            }
        }

        // ==========================================
        // 2. TEMAS VISUALES, WALLPAPER Y BLUR
        // ==========================================
        function changeTheme(theme, btn) {
            document.body.setAttribute('data-theme', theme);
            localStorage.setItem('ps4_theme', theme);
            
            if (btn) {
                document.querySelectorAll('.theme-btn').forEach(b => {
                    b.classList.remove('active');
                    b.style.backgroundColor = 'rgba(255,255,255,0.05)';
                    b.style.borderColor = 'rgba(255,255,255,0.1)';
                    b.style.color = 'var(--text-muted)';
                    b.style.boxShadow = 'none';
                });
                btn.classList.add('active');
                
                // Efecto cristal/neón dependiendo del tema seleccionado
                if(theme === 'glass') {
                    btn.style.backgroundColor = 'rgba(255,255,255,0.1)';
                    btn.style.borderColor = 'var(--theme-prim)';
                    btn.style.color = 'var(--text-main)';
                    btn.style.boxShadow = '0 0 15px rgba(255,255,255,0.2)';
                } else {
                    btn.style.backgroundColor = 'color-mix(in srgb, var(--theme-prim) 20%, transparent)';
                    btn.style.borderColor = 'var(--theme-prim)';
                    btn.style.color = 'var(--theme-prim)';
                    btn.style.boxShadow = '0 0 10px color-mix(in srgb, var(--theme-prim) 40%, transparent)';
                }
            }
            const activeFilter = document.querySelector('.filter-pill.active'); 
            if(activeFilter) filtrarCategoria(activeFilter.dataset.cat, activeFilter);
        }

        function handleWallpaperUpload(event) {
            const file = event.target.files[0];
            if(file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const base64 = e.target.result;
                    aplicarWallpaper(base64);
                    try {
                        localStorage.setItem('ps4_custom_wallpaper', base64);
                        if(typeof ps5Notification === 'function') ps5Notification("WALLPAPER", "Fondo aplicado.", "fa-image");
                    } catch(err) {
                        if(typeof ps5Alert === 'function') ps5Alert("ESPACIO INSUFICIENTE", "Foto demasiado pesada. Intenta con una de menor resolución.", "fa-triangle-exclamation");
                    }
                };
                reader.readAsDataURL(file);
            }
        }

        function aplicarWallpaper(base64) {
            const layer = document.getElementById('custom-bg-layer');
            if(layer) {
                layer.style.backgroundImage = `url('${base64}')`;
                document.body.classList.add('has-wallpaper');
            }
        }

        function clearCustomWallpaper() {
            localStorage.removeItem('ps4_custom_wallpaper');
            const layer = document.getElementById('custom-bg-layer');
            if(layer) layer.style.backgroundImage = 'none';
            document.body.classList.remove('has-wallpaper');
        }

        function updateWallpaperBlur(val) {
            document.documentElement.style.setProperty('--bg-blur', val + 'px');
            localStorage.setItem('ps4_wallpaper_blur', val);
        }

        function updateParticlesCount(val) {
            maxParticles = parseInt(val);
            localStorage.setItem('ps4_particles_count', maxParticles);
            if(particlesEnabled) initParticles();
        }

        function toggleParticles(enabled) {
            particlesEnabled = enabled;
            localStorage.setItem('ps4_particles_enabled', enabled);
            initParticles();
        }

        function loadThemeAndWallpaper() {
            let savedTheme = localStorage.getItem('ps4_theme') || 'ps5';
            document.body.setAttribute('data-theme', savedTheme);
            
            setTimeout(() => {
                let themeBtns = document.querySelectorAll('.theme-btn');
                themeBtns.forEach(b => {
                    b.style.backgroundColor = 'rgba(255,255,255,0.05)';
                    b.style.borderColor = 'rgba(255,255,255,0.1)';
                    b.style.color = 'var(--text-muted)';
                    b.classList.remove('active');
                    if(b.getAttribute('onclick').includes(savedTheme)) {
                        b.classList.add('active');
                        b.style.backgroundColor = 'color-mix(in srgb, var(--theme-prim) 20%, transparent)';
                        b.style.borderColor = 'var(--theme-prim)';
                        b.style.color = 'var(--theme-prim)';
                        b.style.boxShadow = '0 0 10px color-mix(in srgb, var(--theme-prim) 40%, transparent)';
                    }
                });
            }, 100);

            let savedWall = localStorage.getItem('ps4_custom_wallpaper');
            if(savedWall) aplicarWallpaper(savedWall);

            let savedBlur = localStorage.getItem('ps4_wallpaper_blur');
            if(savedBlur !== null) {
                document.documentElement.style.setProperty('--bg-blur', savedBlur + 'px');
                let blurSlider = document.getElementById('blur-slider');
                if(blurSlider) blurSlider.value = savedBlur;
            }

            let pSlider = document.getElementById('particles-slider');
            if(pSlider) pSlider.value = maxParticles;
            
            let pToggle = document.getElementById('toggle_particles');
            if(pToggle) pToggle.checked = particlesEnabled;
        }

        // ==========================================
        // 3. INSTALACIÓN PWA
        // ==========================================
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault(); deferredPrompt = e;
            if(!localStorage.getItem('ps4_pwa_dismissed')) { const pwaContainer = document.getElementById('install-pwa-container'); if(pwaContainer) pwaContainer.classList.remove('hidden'); }
        });
        function installPWA() {
            const pwaContainer = document.getElementById('install-pwa-container'); if(pwaContainer) pwaContainer.classList.add('hidden');
            if (deferredPrompt) { deferredPrompt.prompt(); deferredPrompt.userChoice.then((choiceResult) => { if (choiceResult.outcome === 'accepted') localStorage.setItem('ps4_pwa_dismissed', 'true'); deferredPrompt = null; }); }
        }
        function dismissPWA() { const pwaContainer = document.getElementById('install-pwa-container'); if(pwaContainer) pwaContainer.classList.add('hidden'); localStorage.setItem('ps4_pwa_dismissed', 'true'); }

        // ==========================================
        // 4. SISTEMA MULTI-IDIOMA TOTAL & AUDIO 
        // ==========================================
        const i18n = {
            es: {
                ps4_detected: "DETECTADA", ps4_disconnected: "DESCONECTADA", searching: "BUSCANDO...",
                tab_transfer: "Transferir", desc_transfer: "Envía juegos o aplicaciones a tu consola.",
                dest_routes: '<i class="fa-solid fa-folder-open mr-2" style="color: var(--theme-sec);"></i>RUTAS DE DESTINO',
                files_pkg: '<i class="fa-solid fa-file-circle-plus mr-2" style="color: var(--theme-prim);"></i>ARCHIVOS (PKG/BIN)',
                touch_select: "TOCA PARA SELECCIONAR", btn_send_files: "ENVIAR ARCHIVOS",
                tab_mod: "Modding", desc_mod: "Personaliza el arte de tu biblioteca.",
                gallery: "GALERÍA", backups: "BACKUPS", import: "IMPORTAR", local: "LOCAL",
                btn_apply_art: "APLICAR PORTADA", import_desc: "Pega un link directo para descargar a tu galería.",
                btn_import: "INICIAR IMPORTACIÓN", touch_image: "TOCA PARA BUSCAR IMAGEN",
                tab_exp: "Explorador", desc_exp: "Gestor de archivos interno.",
                btn_paste: "PEGAR", btn_delete: "ELIMINAR", config_ip: "Configura tu IP.",
                tab_pay: "Payloads", desc_pay: "Inyección de código BinLoader.",
                local_port: "PUERTO LOCAL", device: "DISPOSITIVO", touch_bin: "TOCA PARA BUSCAR .BIN", btn_inject: "INYECTAR PAYLOAD",
                tab_set: "Ajustes", desc_set: "Configuración del sistema y visual.",
                install_app: "Instalar App", install_desc: "Añadir a pantalla de inicio", btn_install: "INSTALAR",
                opt_rename: "Renombrar", opt_move: "Mover", opt_delete: "Eliminar",
                modal_pause: "PAUSAR", modal_close: "CERRAR", modal_cancel: "CANCELAR", modal_accept: "ACEPTAR",
                j_err_ip: "FALTA IP", j_err_ip_m: "Escribe la IP.", j_err_file: "FALTA ARCHIVO", j_err_file_m: "Elige un archivo.",
                j_prep: "PREPARANDO", j_prep_m: "Analizando...", j_resume: "REANUDAR", j_resume_m: "¿Reanudar subida?",
                j_exist: "EXISTE", j_exist_m: "Ya existe. ¿Sobrescribir?", j_cancel: "CANCELADO", j_cancel_m: "Abortado.",
                j_comp: "COMPLETADO", j_comp_m: "Éxito.", j_inj: "INYECCION", j_inj_m: "Conectando...", j_succ: "EXITO", j_err: "ERROR",
                j_del_sel: "ELIMINAR SELECCION", j_del_m1: "¿Eliminar", j_elem: "elementos",
                j_warn: "⚠️ ADVERTENCIA", j_warn_m: "Esta acción NO se puede deshacer.",
                j_ren: "RENOMBRAR", j_ren_m: "Nuevo nombre:", j_del1: "¿Eliminar",
                j_new_fold: "NUEVA CARPETA", j_new_fold_m: "Nombre:", j_scan_fail: "FALLO", j_scan_fail_m: "PS4 no encontrada.",
                j_del_route: "ELIMINAR RUTA", j_del_route_m: "¿Quitar ruta?", j_files_sel: "ARCHIVOS", j_empty: "Vacía", j_sel: "seleccionados",
                empty_gal_title: "Coloca tus imágenes <b class='text-white/70'>512x512 .png</b> dentro de la carpeta <br><span class='font-mono bg-black/50 px-1.5 py-0.5 rounded' style='color: var(--theme-sec);'>htdocs/{folder}/</span><br>en tu Android.",
                empty_pay_title: "Coloca tus archivos <b class='text-white/70'>.bin</b> en la carpeta <br><span class='font-mono bg-black/50 px-1.5 py-0.5 rounded' style='color: var(--theme-prim);'>htdocs/payloads/</span>.",
                del_all: "BORRAR TODAS", tab_biblio: "Biblioteca", titles_installed: "TITULOS INSTALADOS", search_placeholder: "Buscar juego o app...",
                rpi_sender: "RPI SENDER", ftp_classic: "FTP CLÁSICO", games_on_phone: "JUEGOS EN EL CELULAR",
                title_id: "TITLE ID", opt_view: "Ver Archivo", opt_download: "Descargar al celular", move_category: "Mover a categoría:",
                opt_backup_saves: "Backup de Partidas (Saves)", opt_gallery_caps: "Galería de Capturas", opt_modding: "Personalizar Portada",
                opt_manage_dlc: "Gestionar DLCs / Updates", opt_remove_lib: "Quitar de Biblioteca", dlc_manager_title: "Gestor de DLCs & Updates",
                caps_ps4_title: "Capturas PS4", modal_loading: "CARGANDO", modal_wait: "Por favor espera...",
                opt_move_btn: "MOVER", opt_delete_btn: "ELIMINAR", btn_install_ps4: "INSTALAR EN PS4",
                set_lang_title: "Idioma", set_lang_desc: "Cambia el idioma.", set_wake_title: "Pantalla Activa", set_wake_desc: "Evita que se apague.",
                set_notif_title: "Notificaciones", set_notif_desc: "Alertas flotantes.", set_sound_title: "UI Sounds", set_sound_desc: "Efectos interactivos.",
                set_vol_title: "Volumen / Sonidos", set_vol_desc: "Ajusta la intensidad.",
                set_temp_title: "Limpiar Temporales", set_temp_desc: "Libera caché del servidor.", ip_placeholder: "192.168.x.x"
            },
            en: {
                ps4_detected: "DETECTED", ps4_disconnected: "DISCONNECTED", searching: "SEARCHING...",
                tab_transfer: "Transfer", desc_transfer: "Send games or apps to your console.",
                dest_routes: '<i class="fa-solid fa-folder-open mr-2" style="color: var(--theme-sec);"></i>DESTINATION PATHS',
                files_pkg: '<i class="fa-solid fa-file-circle-plus mr-2" style="color: var(--theme-prim);"></i>FILES (PKG/BIN)',
                touch_select: "TAP TO SELECT", btn_send_files: "SEND FILES",
                tab_mod: "Modding", desc_mod: "Customize your library artwork.",
                gallery: "GALLERY", backups: "BACKUPS", import: "IMPORT", local: "LOCAL",
                btn_apply_art: "APPLY ARTWORK", import_desc: "Paste a direct image link to download.",
                btn_import: "START IMPORT", touch_image: "TAP TO BROWSE IMAGE",
                tab_exp: "Explorer", desc_exp: "Internal file manager.",
                btn_paste: "PASTE", btn_delete: "DELETE", config_ip: "Set your IP to explore.",
                tab_pay: "Payloads", desc_pay: "BinLoader code injection.",
                local_port: "LOCAL PORT", device: "DEVICE", touch_bin: "TAP TO BROWSE .BIN", btn_inject: "INJECT PAYLOAD",
                tab_set: "Settings", desc_set: "System and visual configuration.",
                install_app: "Install App", install_desc: "Add to home screen", btn_install: "INSTALL",
                opt_rename: "Rename", opt_move: "Move", opt_delete: "Delete",
                modal_pause: "PAUSE", modal_close: "CLOSE", modal_cancel: "CANCEL", modal_accept: "ACCEPT",
                j_err_ip: "MISSING IP", j_err_ip_m: "Please type your PS4 IP.", j_err_file: "MISSING FILE", j_err_file_m: "Choose a file.",
                j_prep: "PREPARING", j_prep_m: "Analyzing...", j_resume: "RESUME", j_resume_m: "Resume upload?",
                j_exist: "FILE EXISTS", j_exist_m: "already exists.<br>Overwrite?", j_cancel: "CANCELED", j_cancel_m: "Transfer aborted.",
                j_comp: "COMPLETED", j_comp_m: "Success.", j_inj: "INJECTING", j_inj_m: "Connecting...", j_succ: "SUCCESS", j_err: "ERROR",
                j_del_sel: "DELETE SELECTION", j_del_m1: "Delete", j_elem: "items",
                j_warn: "⚠️ FINAL WARNING", j_warn_m: "This cannot be undone.",
                j_ren: "RENAME", j_ren_m: "Enter a new name:", j_del1: "Delete",
                j_new_fold: "NEW FOLDER", j_new_fold_m: "Name:", j_scan_fail: "SCAN FAILED", j_scan_fail_m: "PS4 not found.",
                j_del_route: "DELETE PATH", j_del_route_m: "Remove path?", j_files_sel: "FILES", j_empty: "Empty", j_sel: "selected",
                empty_gal_title: "Place your <b class='text-white/70'>512x512 .png</b> images inside the folder <br><span class='font-mono bg-black/50 px-1.5 py-0.5 rounded' style='color: var(--theme-sec);'>htdocs/{folder}/</span><br>in your Android.",
                empty_pay_title: "Place your <b class='text-white/70'>.bin</b> files inside the folder <br><span class='font-mono bg-black/50 px-1.5 py-0.5 rounded' style='color: var(--theme-prim);'>htdocs/payloads/</span>.",
                del_all: "DELETE ALL", tab_biblio: "Library", titles_installed: "INSTALLED TITLES", search_placeholder: "Search game or app...",
                rpi_sender: "RPI SENDER", ftp_classic: "CLASSIC FTP", games_on_phone: "GAMES ON PHONE",
                title_id: "TITLE ID", opt_view: "View File", opt_download: "Download to phone", move_category: "Move to category:",
                opt_backup_saves: "Save Game Backup", opt_gallery_caps: "Screenshots Gallery", opt_modding: "Customize Cover",
                opt_manage_dlc: "Manage DLCs / Updates", opt_remove_lib: "Remove from Library", dlc_manager_title: "DLC & Updates Manager",
                caps_ps4_title: "PS4 Screenshots", modal_loading: "LOADING", modal_wait: "Please wait...",
                opt_move_btn: "MOVE", opt_delete_btn: "DELETE", btn_install_ps4: "INSTALL ON PS4",
                set_lang_title: "Language", set_lang_desc: "Change language.", set_wake_title: "Keep Screen On", set_wake_desc: "Prevents device sleep.",
                set_notif_title: "Notifications", set_notif_desc: "Floating alerts.", set_sound_title: "UI Sounds", set_sound_desc: "Interactive effects.",
                set_vol_title: "Volume / Sounds", set_vol_desc: "Adjust intensity.",
                set_temp_title: "Clear Temp Files", set_temp_desc: "Free up server cache.", ip_placeholder: "192.168.x.x"
            }
        };
        let currentLang = 'es';
        function setLanguage(lang) { 
            currentLang = lang; localStorage.setItem('ps4_ui_lang', lang); 
            const btnEs = document.getElementById('btn-lang-es'); const btnEn = document.getElementById('btn-lang-en'); 
            if(lang === 'es') { if(btnEs) { btnEs.style.backgroundColor = 'var(--theme-prim)'; btnEs.style.color = 'black'; btnEs.style.boxShadow = '0 0 10px color-mix(in srgb, var(--theme-prim) 40%, transparent)'; } if(btnEn) { btnEn.style.backgroundColor = 'transparent'; btnEn.style.color = 'var(--text-muted)'; btnEn.style.boxShadow = 'none'; } } 
            else { if(btnEn) { btnEn.style.backgroundColor = 'var(--theme-prim)'; btnEn.style.color = 'black'; btnEn.style.boxShadow = '0 0 10px color-mix(in srgb, var(--theme-prim) 40%, transparent)'; } if(btnEs) { btnEs.style.backgroundColor = 'transparent'; btnEs.style.color = 'var(--text-muted)'; btnEs.style.boxShadow = 'none'; } } 
            document.querySelectorAll('[data-i18n]').forEach(el => { const key = el.getAttribute('data-i18n'); if (i18n[lang] && i18n[lang][key]) el.innerHTML = i18n[lang][key]; }); 
            document.querySelectorAll('[data-i18n-placeholder]').forEach(el => { const key = el.getAttribute('data-i18n-placeholder'); if (i18n[lang] && i18n[lang][key]) el.setAttribute('placeholder', i18n[lang][key]); });
            if (typeof actualizarGaleria === 'function') actualizarGaleria(); if (typeof actualizarPayloads === 'function') actualizarPayloads(); 
        }
        function t(key) { return (i18n[currentLang] && i18n[currentLang][key]) ? i18n[currentLang][key] : key; }

        const AudioEngine = {
            ctx: null, globalVol: 0.5,
            init: function() { if (!this.ctx) { this.ctx = new (window.AudioContext || window.webkitAudioContext)(); } if (this.ctx.state === 'suspended') this.ctx.resume(); },
            loadSettings: function() { 
                let savedVol = localStorage.getItem('ps4_ui_vol'); if(savedVol !== null) { this.globalVol = parseFloat(savedVol); let slider = document.getElementById('volume_slider'); if(slider) slider.value = this.globalVol; } 
                let snd = localStorage.getItem('ps4_ui_sound'); let t = document.getElementById('toggle_sound'); if(t && snd !== null) { t.checked = (snd === 'true'); }
            },
            updateVol: function(v) { this.globalVol = parseFloat(v); localStorage.setItem('ps4_ui_vol', this.globalVol); this.playClick(); },
            playTone: function(freq, type, duration, vol=0.5) { const t = document.getElementById('toggle_sound'); if (t && !t.checked) return; this.init(); if (!this.ctx) return; const finalVol = vol * this.globalVol; if (finalVol <= 0) return; const osc = this.ctx.createOscillator(); const gain = this.ctx.createGain(); osc.type = type; osc.frequency.setValueAtTime(freq, this.ctx.currentTime); gain.gain.setValueAtTime(0, this.ctx.currentTime); gain.gain.linearRampToValueAtTime(finalVol, this.ctx.currentTime + 0.02); gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + duration); osc.connect(gain); gain.connect(this.ctx.destination); osc.start(); osc.stop(this.ctx.currentTime + duration); },
            playClick: function() { const t = document.getElementById('toggle_sound'); if (t && !t.checked) return; this.init(); if (!this.ctx) return; const finalVol = 0.8 * this.globalVol; if (finalVol <= 0) return; const duration = 0.04; const osc = this.ctx.createOscillator(); const gain = this.ctx.createGain(); osc.type = 'sine'; osc.frequency.setValueAtTime(1800, this.ctx.currentTime); osc.frequency.exponentialRampToValueAtTime(300, this.ctx.currentTime + duration); gain.gain.setValueAtTime(0, this.ctx.currentTime); gain.gain.linearRampToValueAtTime(finalVol, this.ctx.currentTime + 0.002); gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + duration); osc.connect(gain); gain.connect(this.ctx.destination); osc.start(); osc.stop(this.ctx.currentTime + duration); },     
            playSuccess: function() { this.playTone(523.25, 'sine', 0.8, 0.6); setTimeout(() => this.playTone(659.25, 'sine', 0.8, 0.6), 80); setTimeout(() => this.playTone(783.99, 'sine', 1.2, 0.7), 160); },
            playError: function() { this.playTone(200, 'triangle', 0.2, 0.9); setTimeout(() => this.playTone(200, 'triangle', 0.3, 0.9), 180); },
            playDisconnect: function() { this.playTone(350, 'triangle', 0.3, 0.7); setTimeout(() => this.playTone(200, 'sawtooth', 0.5, 0.8), 250); },
            playToast: function() { this.playTone(750, 'sine', 0.1, 0.4); setTimeout(() => this.playTone(950, 'sine', 0.2, 0.5), 100); }, 
            playPS5Boot: function() { const t = document.getElementById('toggle_sound'); if (t && !t.checked) return; this.init(); if (!this.ctx) return; const freqs = [150, 225, 300, 450]; freqs.forEach((freq, i) => { const osc = this.ctx.createOscillator(); const gain = this.ctx.createGain(); osc.type = i % 2 === 0 ? 'sine' : 'triangle'; osc.frequency.setValueAtTime(freq, this.ctx.currentTime); gain.gain.setValueAtTime(0, this.ctx.currentTime); gain.gain.linearRampToValueAtTime(0.12 * this.globalVol, this.ctx.currentTime + 1.5); gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + 4.5); osc.connect(gain); gain.connect(this.ctx.destination); osc.start(); osc.stop(this.ctx.currentTime + 5); }); }
        };
        
        document.body.addEventListener('click', () => AudioEngine.init(), {once:true});
        document.addEventListener('touchstart', () => AudioEngine.init(), {once:true});
        document.addEventListener('change', e => { if(e.target.id === 'toggle_sound') localStorage.setItem('ps4_ui_sound', e.target.checked); });
        document.addEventListener('click', (e) => { const isBtn = e.target.closest('button, .dock-item, .gallery-item, .folder-btn, .cursor-pointer, input[type="checkbox"], input[type="range"]'); const t = document.getElementById('toggle_sound'); if(isBtn && t && t.checked && e.target.id !== 'volume_slider') AudioEngine.playClick(); });

        // ==========================================
        // 5. MODALES Y NOTIFICACIONES
        // ==========================================
        function showPS5Dialog(title, message, type = 'alert', iconClass = 'fa-info', inputValue = '', confirmColor = '') {
            return new Promise((resolve) => {
                const dialogEl = document.getElementById('ps5-dialog');
                if(!dialogEl) {
                    let cleanMsg = message.replace(/<[^>]*>?/gm, ''); 
                    if(type === 'alert') { alert(title + ": " + cleanMsg); resolve(true); }
                    else if(type === 'confirm') { resolve(confirm(title + "\n" + cleanMsg)); }
                    else if(type === 'prompt') { resolve(prompt(title + "\n" + cleanMsg, inputValue)); }
                    return;
                }
                const dialogCard = document.getElementById('ps5-dialog-card'), dialogTitle = document.getElementById('ps5-dialog-title'), dialogText = document.getElementById('ps5-dialog-text'), dialogIcon = document.getElementById('ps5-dialog-icon'), dialogInput = document.getElementById('ps5-dialog-input'), dialogBtnCancel = document.getElementById('ps5-dialog-btn-cancel'), dialogBtnConfirm = document.getElementById('ps5-dialog-btn-confirm');
                if(type === 'alert' && iconClass.includes('text-red')) AudioEngine.playError(); else if (type === 'confirm' && iconClass.includes('fa-trash')) AudioEngine.playError();
                
                if(dialogTitle) dialogTitle.innerText = title; 
                if(dialogText) dialogText.innerHTML = message; 
                if(dialogIcon) dialogIcon.innerHTML = `<i class="fa-solid ${iconClass} text-2xl text-[var(--text-main)]"></i>`;
                
                if(dialogInput) dialogInput.classList.add('hidden'); 
                if(dialogBtnCancel) { dialogBtnCancel.classList.add('hidden'); dialogBtnCancel.innerText = t('modal_cancel'); }
                if(dialogBtnConfirm) { 
                    if (confirmColor && confirmColor !== '') {
                        dialogBtnConfirm.className = `flex-1 rounded-xl py-3.5 font-black text-[10px] tracking-widest uppercase transition-all active:scale-95 ${confirmColor}`;
                        dialogBtnConfirm.style.boxShadow = '';
                    } else {
                        dialogBtnConfirm.className = `flex-1 text-black rounded-xl py-3.5 font-black text-[10px] tracking-widest uppercase transition-all active:scale-95 border`;
                        dialogBtnConfirm.style.backgroundColor = 'var(--theme-prim)';
                        dialogBtnConfirm.style.borderColor = 'var(--theme-prim)';
                        dialogBtnConfirm.style.boxShadow = '0 0 15px color-mix(in srgb, var(--theme-prim) 40%, transparent)';
                    }
                    dialogBtnConfirm.innerText = t('modal_accept'); 
                }
                
                if (type === 'prompt' && dialogInput && dialogBtnCancel) { dialogInput.classList.remove('hidden'); dialogInput.value = inputValue; dialogBtnCancel.classList.remove('hidden'); setTimeout(() => dialogInput.focus(), 300); } else if (type === 'confirm' && dialogBtnCancel) { dialogBtnCancel.classList.remove('hidden'); }
                const closeDialog = (result) => { if(dialogEl) dialogEl.classList.add('opacity-0'); if(dialogCard) dialogCard.classList.add('scale-95'); setTimeout(() => { if(dialogEl) dialogEl.classList.add('hidden'); resolve(result); }, 300); };
                if(dialogBtnConfirm) dialogBtnConfirm.onclick = () => { if (type === 'prompt') closeDialog(dialogInput ? dialogInput.value.trim() : ''); else closeDialog(true); };
                if(dialogBtnCancel) dialogBtnCancel.onclick = () => closeDialog(false);
                if(dialogEl) { dialogEl.classList.remove('hidden'); setTimeout(() => { dialogEl.classList.remove('opacity-0'); if(dialogCard) dialogCard.classList.remove('scale-95'); }, 10); }
            });
        }
        const ps5Alert = (title, msg, icon='fa-info') => showPS5Dialog(title, msg, 'alert', icon);
        const ps5Confirm = (title, msg, icon='fa-circle-question', confirmColor='') => showPS5Dialog(title, msg, 'confirm', icon, '', confirmColor);
        const ps5Prompt = (title, msg, defaultVal='') => showPS5Dialog(title, msg, 'prompt', 'fa-pen', defaultVal);

        function ps5Notification(title, message, iconClass = 'fa-check') {
            const toggle = document.getElementById('toggle_notifications'); if (toggle && !toggle.checked) return; 
            const area = document.getElementById('notification-area'); if(!area) return;
            const toast = document.createElement('div'); toast.className = 'ps5-toast';
            toast.innerHTML = `<div class="ps5-toast-icon"><i class="fa-solid ${iconClass}"></i></div><div class="flex flex-col"><span class="text-[9px] font-black tracking-widest uppercase mb-0.5" style="color: var(--theme-prim); filter: drop-shadow(0 0 2px rgba(0,0,0,0.8));">${title}</span><span class="text-[11px] font-bold text-[var(--text-main)]">${message}</span></div>`;
            area.appendChild(toast); try { AudioEngine.playToast(); } catch(e) {}
            setTimeout(() => { toast.classList.add('hide'); setTimeout(() => toast.remove(), 400); }, 3500);
        }

        // ==========================================
        // 6. INICIO Y NAVEGACIÓN
        // ==========================================
        window.addEventListener('load', () => {
            setLanguage(localStorage.getItem('ps4_ui_lang') || 'es'); 
            AudioEngine.loadSettings(); 
            initParticles();
            animateParticles(); 
            
            const introScreen = document.getElementById('intro-screen');
            const logoWrap = document.getElementById('logo-wrapper');
            const ambientBg = document.getElementById('ambient-bg');
            const appUi = document.getElementById('app-ui');
            
            if(logoWrap) setTimeout(() => { logoWrap.style.opacity = '1'; logoWrap.style.transform = 'scale(1)'; logoWrap.style.filter = 'blur(0px)'; }, 500);
            setTimeout(() => { try { AudioEngine.playPS5Boot(); } catch(e) {} }, 500);
            
            setTimeout(() => {
                isExploding = true; 
                if(logoWrap) { logoWrap.style.transform = 'scale(1.5)'; logoWrap.style.opacity = '0'; logoWrap.style.filter = 'blur(20px)'; }
                
                setTimeout(() => { 
                    isExploding = false; 
                    
                    const sd = document.getElementById('stardust');
                    if(sd) { sd.style.zIndex = '0'; }
                    
                    if(introScreen) introScreen.style.opacity = '0'; 
                    if(appUi) appUi.style.opacity = '1'; 
                    if(ambientBg) ambientBg.style.transform = 'scale(1)'; 
                    
                    setTimeout(() => { if(introScreen) introScreen.remove(); }, 1500); 
                }, 800);
            }, 3000);
            
            switchTransferMode('rpi'); 
        });

        const tabsOrder = ['tab-biblioteca', 'tab-ftp', 'tab-icons', 'tab-explorer', 'tab-payloads', 'tab-settings']; let currentTabIndex = 0;
        
        let touchstartX = 0, touchendX = 0, touchstartY = 0, touchendY = 0; 
        let isSwipeIgnored = false;
        
        document.addEventListener('touchstart', e => { 
            if(e.target.closest('.custom-scrollbar') || e.target.closest('input[type="range"]')) { isSwipeIgnored = true; return; }
            isSwipeIgnored = false;
            touchstartX = e.changedTouches[0].screenX; touchstartY = e.changedTouches[0].screenY; 
        }, {passive: true});
        
        document.addEventListener('touchend', e => { 
            if(isSwipeIgnored) return;
            touchendX = e.changedTouches[0].screenX; touchendY = e.changedTouches[0].screenY; handleSwipe(); 
        }, {passive: true});
        
        function handleSwipe() {
            if((document.getElementById('custom-modal') && !document.getElementById('custom-modal').classList.contains('hidden')) || (document.getElementById('ps5-dialog') && !document.getElementById('ps5-dialog').classList.contains('hidden')) || (document.getElementById('bottom-sheet') && document.getElementById('bottom-sheet').classList.contains('open')) || (document.getElementById('sheet-opciones') && document.getElementById('sheet-opciones').classList.contains('open')) || (document.getElementById('sheet-capturas') && document.getElementById('sheet-capturas').classList.contains('open')) || (document.getElementById('lightbox-modal') && !document.getElementById('lightbox-modal').classList.contains('hidden'))) return;
            const deltaX = touchendX - touchstartX; const deltaY = touchendY - touchstartY;
            if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 60) {
                const navButtons = document.querySelectorAll('.dock-item');
                if (deltaX < 0) { if (currentTabIndex < tabsOrder.length - 1) { currentTabIndex++; switchTab(tabsOrder[currentTabIndex], navButtons[currentTabIndex], currentTabIndex); } } 
                else { if (currentTabIndex > 0) { currentTabIndex--; switchTab(tabsOrder[currentTabIndex], navButtons[currentTabIndex], currentTabIndex); } }
            }
        }

        function switchTab(tabId, btnElement, targetIndex = null) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active')); document.querySelectorAll('.dock-item').forEach(b => b.classList.remove('active'));
            const targetView = document.getElementById(tabId); if(btnElement) btnElement.classList.add('active'); window.scrollTo({ top: 0, behavior: 'smooth' });
            if(targetIndex !== null) currentTabIndex = targetIndex; else currentTabIndex = tabsOrder.indexOf(tabId);
            if(targetView) setTimeout(() => { targetView.classList.add('active'); }, 50);
            
            const btnFtp = document.getElementById('floating-btn-ftp'), btnRpi = document.getElementById('floating-btn-rpi'), btnAplicar = document.getElementById('floating-btn-aplicar'), btnPayload = document.getElementById('floating-btn-payload');
            if(btnFtp) btnFtp.classList.add('floating-hidden'); 
            if(btnRpi) btnRpi.classList.add('floating-hidden'); 
            if(btnAplicar) btnAplicar.classList.add('floating-hidden'); 
            if(btnPayload) btnPayload.classList.add('floating-hidden');
            
            if (tabId === 'tab-ftp') { 
                if (currentTransferMode === 'rpi') {
                    if(rpiQueue && rpiQueue.length > 0 && btnRpi) btnRpi.classList.remove('floating-hidden');
                } else {
                    if(btnFtp) btnFtp.classList.remove('floating-hidden');
                }
            } else if (tabId === 'tab-icons' && currentIconSource !== 'import') { 
                if(btnAplicar && selectedIconValue) btnAplicar.classList.remove('floating-hidden'); 
            } else if (tabId === 'tab-payloads') { 
                if(btnPayload) btnPayload.classList.remove('floating-hidden'); 
            }
            if (tabId === 'tab-explorer' && currentExplorerPath === '') { if (typeof loadExplorerPath === 'function') loadExplorerPath('/'); }
        }

        // ==========================================
        // 7. VARIABLES GLOBALES (ESTADO)
        // ==========================================
        let currentTitle = "", currentCusa = "", currentVersion = "1.00";
        let isTransferring = false, uploadAbortController = null, isPaused = false, currentFileName = ""; 
        const CHUNK_SIZE = 20 * 1024 * 1024; 
        let currentExplorerPath = '', optionsPath = '', optionsName = '', optionsIsDir = false, clipboardItems = [], isCutMode = false, currentExplorerItems = [], isSelectMode = false, selectedItems = [];
        let PAYLOADS_LOCALES = [], selectedPayloadValue = null, currentPayloadSource = 'gallery';
        let ICONOS_LOCALES = [], BACKUPS_LOCALES = [], selectedIconValue = null, currentIconSource = 'gallery';
        let cachedAvatar = null, customUserName = localStorage.getItem('ps4_custom_username') || 'SEBAS';
        let isScanning = false, abortController = null, radarAnimInterval = null, connectionMonitorInterval = null, wasConnected = false, failedPings = 0; 
        let wakeLock = null, timeoutModalClose = null;
        let esBovedaGlobal = false;

        // ==========================================
        // 8. MODALES DE CARGA Y CERRADO GENERAL
        // ==========================================
        function mostrarCarga(titulo, subtitulo, iconClass="fa-circle-notch fa-spin") { 
            const modal = document.getElementById('custom-modal');
            if(!modal) return;
            if(timeoutModalClose) clearTimeout(timeoutModalClose); 
            document.getElementById('modal-title').innerText = titulo; 
            document.getElementById('modal-text').innerHTML = subtitulo; 
            const mIcon = document.getElementById('modal-icon');
            mIcon.innerHTML = `<div class="absolute inset-0 rounded-full border animate-ping" style="border-color: var(--theme-prim); opacity: 0.5;"></div><i class="fa-solid ${iconClass} text-3xl relative z-10" style="color: var(--theme-prim);"></i>`;
            document.getElementById('modal-progress-container').classList.add('hidden'); 
            document.getElementById('modal-controls').classList.add('hidden'); 
            document.getElementById('modal-close-btn').classList.add('hidden'); 
            modal.classList.remove('hidden'); 
            setTimeout(() => { modal.classList.remove('opacity-0'); document.getElementById('modal-card').classList.remove('scale-95'); }, 10); 
        }

        function closeCustomModal() { 
            const modal = document.getElementById('custom-modal');
            if(!modal) return;
            modal.classList.add('opacity-0'); document.getElementById('modal-card').classList.add('scale-95'); 
            timeoutModalClose = setTimeout(() => modal.classList.add('hidden'), 300); 
        }

        function mostrarErrorFinal(titulo, msg) { 
            const modal = document.getElementById('custom-modal');
            if(!modal) return;
            if(timeoutModalClose) clearTimeout(timeoutModalClose); 
            AudioEngine.playError(); 
            modal.classList.remove('hidden', 'opacity-0'); document.getElementById('modal-card').classList.remove('scale-95'); 
            document.getElementById('modal-progress-container').classList.add('hidden'); document.getElementById('modal-controls').classList.add('hidden'); 
            document.getElementById('modal-close-btn').classList.remove('hidden'); 
            document.getElementById('modal-title').innerText = titulo;
            document.getElementById('modal-text').innerHTML = msg; 
            document.getElementById('modal-icon').innerHTML = '<i class="fa-solid fa-triangle-exclamation text-4xl text-red-500"></i>'; 
        }
        
        async function requestWakeLock() { if (!document.getElementById('toggle_wakelock') || !document.getElementById('toggle_wakelock').checked) return; try { if ('wakeLock' in navigator) wakeLock = await navigator.wakeLock.request('screen'); } catch (err) {} }
        function releaseWakeLock() { if (wakeLock !== null) { wakeLock.release().then(() => wakeLock = null); } }
        function cerrarTodo() { document.querySelectorAll('.bottom-sheet').forEach(s => s.classList.remove('open')); const overlay = document.getElementById('overlay-global'); if(overlay) overlay.classList.remove('open'); }

        // ==========================================
        // 9. BIBLIOTECA, CATEGORÍAS Y OPCIONES
        // ==========================================
        function buscarEnBiblioteca(q) {
            q = q.toLowerCase(); const items = document.querySelectorAll('.item-biblio');
            const activeFilterBtn = document.querySelector('.filter-pill.active'); const activeCat = activeFilterBtn ? activeFilterBtn.dataset.cat : 'todos';
            items.forEach(item => {
                const title = item.getAttribute('data-name').toLowerCase();
                const itemCat = item.getAttribute('data-category');
                const matchesSearch = title.includes(q);
                const matchesCat = activeCat === 'todos' || itemCat === activeCat;
                item.style.display = (matchesSearch && matchesCat) ? '' : 'none';
            });
        }

        function renderCategorias() {
            let customCats = JSON.parse(localStorage.getItem('ps4_custom_categories')) || []; const nav = document.getElementById('categoria-nav'); 
            if(nav) {
                let htmlNav = `<button onclick="crearNuevaCategoria()" class="h-7 w-7 shrink-0 rounded-lg bg-black/50 border border-white/10 flex items-center justify-center text-[var(--text-muted)] hover:text-[var(--text-main)] active:scale-95 transition-colors mr-2"><i class="fa-solid fa-plus text-[10px]"></i></button>`;
                const baseStyle = "filter-pill h-7 px-3 rounded-lg text-[9px] font-black tracking-widest uppercase transition-all border";
                const inactStyle = "bg-transparent text-[var(--text-muted)] border-transparent hover:text-[var(--text-main)] hover:bg-white/5";
                
                htmlNav += `<button data-cat="todos" onclick="filtrarCategoria('todos', this)" class="${baseStyle} active" style="background-color: var(--theme-prim); color: black; border-color: var(--theme-prim); box-shadow: 0 0 10px var(--theme-prim); opacity: 0.9;">Todos</button>`;
                htmlNav += `<button data-cat="juegos" onclick="filtrarCategoria('juegos', this)" class="${baseStyle} ${inactStyle}">Juegos</button>`;
                htmlNav += `<button data-cat="apps" onclick="filtrarCategoria('apps', this)" class="${baseStyle} ${inactStyle}">Apps</button>`;
                
                customCats.forEach(c => { 
                    htmlNav += `<div class="relative group flex items-center shrink-0">
                        <button data-cat="${c.id}" onclick="filtrarCategoria('${c.id}', this)" class="${baseStyle} ${inactStyle} pr-6">${c.name}</button>
                        <div onclick="eliminarCategoria('${c.id}')" class="absolute right-1 w-4 h-4 bg-red-500/80 hover:bg-red-500 rounded-md flex items-center justify-center text-white cursor-pointer opacity-0 group-hover:opacity-100 transition-opacity z-10"><i class="fa-solid fa-xmark text-[8px]"></i></div>
                    </div>`; 
                });
                nav.innerHTML = htmlNav;
            }
        }

        function filtrarCategoria(cat, btn) {
            document.querySelectorAll('.filter-pill').forEach(b => { 
                b.classList.remove('active'); 
                b.style.backgroundColor = 'transparent';
                b.style.color = 'var(--text-muted)';
                b.style.borderColor = 'transparent';
                b.style.boxShadow = 'none';
            }); 
            if(btn) { 
                btn.classList.add('active'); 
                btn.style.backgroundColor = 'var(--theme-prim)';
                btn.style.color = 'black';
                btn.style.borderColor = 'var(--theme-prim)';
                btn.style.boxShadow = `0 0 10px var(--theme-prim)`;
                btn.style.opacity = '0.9';
            }
            const items = document.querySelectorAll('.item-biblio'); 
            items.forEach(item => { let itemCat = item.getAttribute('data-category'); item.style.display = (cat === 'todos' || itemCat === cat) ? '' : 'none'; });
        }

        async function cargarBibliotecaLocal() {
            const grid = document.getElementById('grid-biblioteca'); if(!grid) return;
            try {
                let res = await fetch('api/library.php?action=get_cached_games&_t=' + new Date().getTime()); 
                let data = await res.json();
                if(data.status === 'success') {
                    let html = ''; data.data.forEach((game, index) => { html += agregarJuegoHTML(game, index); });
                    html += `<div onclick="abrirMenuInstalar()" class="game-card rounded-2xl overflow-hidden relative cursor-pointer aspect-square active:scale-95 flex flex-col items-center justify-center bg-black/40 hover:bg-black/60 transition-colors border border-dashed border-white/20" data-id="9999" style="opacity: 0.8;"><div class="w-10 h-10 rounded-xl flex items-center justify-center mb-3 shadow-lg" style="background-color: var(--theme-prim); color: black;"><i class="fa-solid fa-plus text-lg"></i></div><span class="text-[9px] font-black tracking-widest text-[var(--text-muted)] uppercase">Instalar Nuevo</span></div>`;
                    grid.innerHTML = html; 
                    if(document.getElementById('total-games-badge')) document.getElementById('total-games-badge').innerText = data.data.length;
                    filtrarCategoria('todos', document.querySelector('.filter-pill[data-cat="todos"]'));
                }
            } catch(e) { console.error("Error cargando biblioteca", e); }
        }

        function agregarJuegoHTML(game, index) {
            let tipo = game.type === 'game' ? 'juegos' : (game.type === 'app' ? 'apps' : game.type);
            let savedCats = JSON.parse(localStorage.getItem('ps4_game_categories')) || {}; 
            let catAsignada = savedCats[game.id] || tipo || 'juegos';
            let iconSrc = game.icon ? game.icon + '?_t=' + new Date().getTime() : 'icon-512.png';
            let safeTitle = game.title.replace(/'/g, "\\'").replace(/"/g, "&quot;");
            return `<div class="item-biblio game-card rounded-2xl overflow-hidden relative group cursor-pointer aspect-square active:scale-95 shadow-lg animate-fade-in border border-white/5" data-category="${catAsignada}" data-name="${safeTitle}" data-id="${index}" onclick="abrirOpciones('${safeTitle}', '${game.id}', '${iconSrc}', '${game.version || "1.00"}')">
                <img src="${iconSrc}" loading="lazy" class="w-full h-full object-cover relative z-10" onerror="this.src='icon-512.png'">
                <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-transparent to-transparent pointer-events-none z-20 opacity-80 group-hover:opacity-100 transition-opacity"></div>
                <div class="absolute bottom-0 left-0 right-0 p-2 pointer-events-none z-30">
                    <h3 class="text-[9px] font-black text-white leading-tight truncate uppercase drop-shadow-md mb-0.5">${game.title}</h3>
                    <span class="text-[7px] font-mono font-bold tracking-widest px-1 py-0.5 rounded bg-black/60 border border-white/10" style="color: var(--theme-prim);">${game.id}</span>
                </div>
            </div>`;
        }

        function abrirPanelSecundario(id) { document.getElementById('sheet-opciones').classList.remove('open'); setTimeout(() => { document.getElementById(id).classList.add('open'); }, 100); }

        function volverAOpcionesDesde(id) { 
            document.getElementById(id).classList.remove('open'); 
            if (esBovedaGlobal) {
                setTimeout(() => { document.getElementById('overlay-global').classList.remove('open'); }, 100);
            } else {
                setTimeout(() => { document.getElementById('sheet-opciones').classList.add('open'); }, 100); 
            }
        }

        function abrirMenuInstalar() { const navButtons = document.querySelectorAll('.dock-item'); switchTab('tab-ftp', navButtons[1], 1); }
        function simularRedireccionModding() { const navButtons = document.querySelectorAll('.dock-item'); switchTab('tab-icons', navButtons[2], 2); document.getElementById('icon-cusa').value = currentCusa; cerrarTodo(); }

        function renderizarCategoriasModal(cusa) {
            const container = document.getElementById('selector-categorias');
            if (!container) return;
            let customCats = JSON.parse(localStorage.getItem('ps4_custom_categories')) || [];
            let categorias = [{id:'juegos', name:'Juegos'}, {id:'apps', name:'Apps'}, ...customCats];
            let savedCats = JSON.parse(localStorage.getItem('ps4_game_categories')) || {};
            let categoriaActual = savedCats[cusa] || 'juegos'; 
            let html = '';
            categorias.forEach(cat => {
                let esActivo = (cat.id === categoriaActual);
                let clasesColor = esActivo ? 'style="background-color: color-mix(in srgb, var(--theme-prim) 20%, transparent); color: var(--theme-prim); border-color: color-mix(in srgb, var(--theme-prim) 50%, transparent); box-shadow: 0 0 10px color-mix(in srgb, var(--theme-prim) 30%, transparent);"' : 'class="bg-black/40 text-[var(--text-muted)] border-white/5 hover:text-[var(--text-main)] hover:bg-white/10"';
                let classesExtra = esActivo ? '' : 'bg-black/40 text-[var(--text-muted)] border-white/5 hover:text-[var(--text-main)] hover:bg-white/10';
                html += `<button onclick="moverAGrupo('${cat.id}')" class="h-8 px-4 rounded-xl border text-[9px] font-black tracking-widest uppercase transition-all shrink-0 ${classesExtra}" ${clasesColor}>${cat.name}</button>`;
            });
            container.innerHTML = html;
        }

        async function abrirOpciones(titulo, cusa, imgSrc, version) {
            currentTitle = titulo; currentCusa = cusa; currentVersion = version || "1.00";
            document.getElementById('opt-game-title').innerText = titulo; document.getElementById('opt-game-cusa').innerText = cusa; document.getElementById('opt-game-img').src = imgSrc; document.getElementById('opt-game-version').innerText = 'v' + currentVersion;
            
            const badgeCaps = document.getElementById('opt-caps-badge');
            if(badgeCaps) badgeCaps.classList.add('hidden');

            renderizarCategoriasModal(currentCusa);
            document.getElementById('opt-game-size').innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="color: var(--theme-prim);"></i>'; document.getElementById('opt-game-loc').innerText = '...';
            document.getElementById('overlay-global').classList.add('open'); document.getElementById('sheet-opciones').classList.add('open');
            const ip = document.getElementById('host-ip') ? document.getElementById('host-ip').value : '';
            if(!ip) { document.getElementById('opt-game-size').innerText = "Falta IP"; return; }
            
            const fdInfo = new FormData(); fdInfo.append('host_ip', ip); fdInfo.append('cusa_id', currentCusa);
            const fdCaps = new FormData(); fdCaps.append('host_ip', ip); fdCaps.append('cusa_id', currentCusa); fdCaps.append('action', 'count_only');
            
            try {
                fetch('api/tech_info.php', { method: 'POST', body: fdInfo }).then(r => r.json()).then(data => {
                    if (data.status === 'success') { document.getElementById('opt-game-size').innerText = data.size; document.getElementById('opt-game-loc').innerText = data.location; } else { document.getElementById('opt-game-size').innerText = "Error"; }
                }).catch(e => { document.getElementById('opt-game-size').innerText = "Error red"; });
                
                fetch('api/ps4_screenshots.php', { method: 'POST', body: fdCaps }).then(r => r.json()).then(data => {
                    if (data.status === 'success' && data.count > 0 && badgeCaps) {
                        badgeCaps.innerText = data.count;
                        badgeCaps.classList.remove('hidden');
                    }
                }).catch(e => {});
            } catch(e) {}
        }

        async function crearNuevaCategoria() {
            const nombre = await ps5Prompt("NUEVA CATEGORÍA", "Escribe el nombre:");
            if(nombre && nombre.trim() !== "") {
                const catId = nombre.trim().toLowerCase().replace(/[^a-z0-9]/g, '-');
                if(catId === 'todos' || catId === 'juegos' || catId === 'apps') return; 
                let customCats = JSON.parse(localStorage.getItem('ps4_custom_categories')) || [];
                if(!customCats.find(c => c.id === catId)) { customCats.push({id: catId, name: nombre}); localStorage.setItem('ps4_custom_categories', JSON.stringify(customCats)); renderCategorias(); ps5Notification("EXITO", `Categoría creada.`, "fa-folder-plus"); }
            }
        }

        async function eliminarCategoria(catId) {
            const seguro = await ps5Confirm("ELIMINAR CATEGORÍA", "¿Borrar esta categoría? Los juegos volverán a su sección por defecto.", "fa-trash", "bg-red-600 text-white border border-red-500/50 shadow-[0_0_15px_rgba(239,68,68,0.4)]");
            if(!seguro) return;
            let customCats = JSON.parse(localStorage.getItem('ps4_custom_categories')) || []; customCats = customCats.filter(c => c.id !== catId); localStorage.setItem('ps4_custom_categories', JSON.stringify(customCats));
            let savedCats = JSON.parse(localStorage.getItem('ps4_game_categories')) || {}; for(let cusa in savedCats) { if(savedCats[cusa] === catId) delete savedCats[cusa]; } localStorage.setItem('ps4_game_categories', JSON.stringify(savedCats));
            renderCategorias(); cargarBibliotecaLocal(); ps5Notification("ELIMINADA", "Categoría borrada.", "fa-trash");
        }

        function moverAGrupo(cat) { 
            const items = document.querySelectorAll('.item-biblio');
            items.forEach(item => { if(item.getAttribute('data-name') === currentTitle) { item.setAttribute('data-category', cat); let savedCats = JSON.parse(localStorage.getItem('ps4_game_categories')) || {}; savedCats[currentCusa] = cat; localStorage.setItem('ps4_game_categories', JSON.stringify(savedCats)); } });
            ps5Notification("MOVIDO", "Juego reubicado con éxito.", "fa-check"); renderizarCategoriasModal(currentCusa); const activeFilter = document.querySelector('.filter-pill.active'); if(activeFilter) filtrarCategoria(activeFilter.dataset.cat, activeFilter);
        }

        async function borrarJuegoDeBiblioteca() {
            const seguro = await ps5Confirm("QUITAR JUEGO", `¿Ocultar de la biblioteca local?<br><br><span class="text-[9px] text-[var(--text-muted)] block mt-2">Solo borra la portada del celular, no borra el juego de tu PS4.</span>`, "fa-eye-slash", "bg-red-600 text-white border border-red-500/50 shadow-[0_0_15px_rgba(239,68,68,0.4)]");
            if (!seguro) return;
            const fd = new FormData(); fd.append('action', 'delete_game'); fd.append('cusa_id', currentCusa);
            try { await fetch('api/library.php', { method: 'POST', body: fd }); ps5Notification("OCULTO", "Juego removido de la vista.", "fa-eye-slash"); cerrarTodo(); cargarBibliotecaLocal(); } catch(e) {}
        }

        async function limpiarBibliotecaEntera() {
            const seguro = await ps5Confirm("VACIAR CACHÉ", "¿Estás seguro de limpiar por completo la biblioteca local?<br><br><span class='text-[9px] text-[var(--text-muted)] block mt-2'>Tendrás que volver a sincronizar la consola para que aparezcan tus juegos.</span>", "fa-dumpster-fire", "bg-red-600 text-white border border-red-500/50 shadow-[0_0_20px_rgba(220,38,38,0.5)]");
            if(!seguro) return;
            mostrarCarga("LIMPIANDO", "Borrando portadas...", "fa-trash fa-bounce text-red-500");
            const items = document.querySelectorAll('.item-biblio');
            for(let item of items) { let cusaSpan = item.querySelector('span.font-mono'); if(cusaSpan) { const fdDel = new FormData(); fdDel.append('action', 'delete_game'); fdDel.append('cusa_id', cusaSpan.innerText); try { await fetch('api/library.php', { method: 'POST', body: fdDel }); } catch(e){} } }
            localStorage.removeItem('ps4_game_categories'); closeCustomModal(); ps5Notification("LIMPIEZA", "Biblioteca formateada.", "fa-broom"); cargarBibliotecaLocal();
        }

        let ordenActual = 0; 
        function cambiarOrden() {
            ordenActual++; if (ordenActual > 2) ordenActual = 0;
            const grid = document.getElementById('grid-biblioteca'); if(!grid) return;
            const cards = Array.from(grid.querySelectorAll('.game-card')).filter(card => card.dataset.id !== "9999");
            const btnInstalar = grid.querySelector('[data-id="9999"]'); const icono = document.getElementById('icono-orden');
            cards.forEach(card => card.classList.add('sorting-anim')); if(btnInstalar) btnInstalar.classList.add('sorting-anim');
            setTimeout(() => {
                if (ordenActual === 0) { icono.className = "fa-solid fa-arrow-down-short-wide text-sm transition-colors"; cards.sort((a, b) => parseInt(a.dataset.id) - parseInt(b.dataset.id)); if(typeof ps5Notification === 'function') ps5Notification("ORDEN", "Instalación (Por Defecto)", "fa-clock"); } 
                else if (ordenActual === 1) { icono.className = "fa-solid fa-arrow-down-a-z text-sm transition-colors"; cards.sort((a, b) => a.dataset.name.localeCompare(b.dataset.name)); if(typeof ps5Notification === 'function') ps5Notification("ORDEN", "Alfabético (A-Z)", "fa-font"); } 
                else if (ordenActual === 2) { icono.className = "fa-solid fa-arrow-up-z-a text-sm transition-colors"; cards.sort((a, b) => b.dataset.name.localeCompare(a.dataset.name)); if(typeof ps5Notification === 'function') ps5Notification("ORDEN", "Alfabético inverso (Z-A)", "fa-font"); }
                grid.innerHTML = ''; cards.forEach(card => grid.appendChild(card)); if(btnInstalar) grid.appendChild(btnInstalar);
                setTimeout(() => { const newCards = grid.querySelectorAll('.game-card'); newCards.forEach(c => c.classList.remove('sorting-anim')); const activeFilter = document.querySelector('.filter-pill.active'); if(activeFilter) filtrarCategoria(activeFilter.dataset.cat, activeFilter); }, 50);
            }, 200); 
        }

        // ==========================================
        // 10. DLCS Y BACKUP SAVES
        // ==========================================
        async function abrirMenuDLCs() {
            document.getElementById('dlc-game-cusa').innerText = `${currentCusa} - ${currentTitle}`; abrirPanelSecundario('sheet-dlcs');
            const container = document.getElementById('dlc-list-container');
            container.innerHTML = `<div class="text-center py-12"><i class="fa-solid fa-circle-notch fa-spin text-3xl mb-4 block" style="color: var(--theme-prim);"></i><p class="text-[10px] font-black tracking-widest uppercase text-[var(--text-muted)]">Calculando pesos en PS4...</p></div>`;
            const ip = document.getElementById('host-ip') ? document.getElementById('host-ip').value : '';
            if(!ip) { container.innerHTML = `<div class="text-center py-10 text-red-400 text-xs font-bold">Falta conectar la IP de PS4.</div>`; return; }
            const fd = new FormData(); fd.append('action', 'scan'); fd.append('host_ip', ip); fd.append('cusa_id', currentCusa);
            try {
                let res = await fetch('api/dlc_manager.php', { method: 'POST', body: fd }); let data = await res.json();
                if (data.status === 'success') {
                    if (data.items.length === 0) { container.innerHTML = `<div class="text-center py-10 bg-black/40 rounded-2xl border border-white/5 shadow-inner"><i class="fa-solid fa-ghost text-4xl text-white/10 mb-3 block"></i><p class="text-[10px] text-[var(--text-muted)] font-black tracking-widest uppercase">Juego Base Limpio</p><p class="text-[9px] text-white/30 mt-1">No hay Updates ni DLCs que borrar.</p></div>`; return; }
                    let html = '';
                    data.items.forEach(item => {
                        let icon = item.type === 'update' ? `fa-arrow-up-right-dots` : `fa-puzzle-piece`;
                        let title = item.type === 'update' ? 'UPDATE (PARCHE)' : 'EXPANSIÓN / DLC';
                        html += `<div class="flex items-center justify-between p-4 rounded-2xl border group cursor-pointer hover:bg-white/5 transition-colors bg-black/40" style="border-color: color-mix(in srgb, var(--theme-prim) 20%, transparent);"><div class="flex items-center gap-4 overflow-hidden"><div class="w-11 h-11 rounded-xl bg-black/60 flex items-center justify-center shrink-0 border border-white/5"><i class="fa-solid ${icon} text-lg" style="color: var(--theme-prim);"></i></div><div class="flex flex-col overflow-hidden pr-2"><span class="text-[9px] font-black tracking-widest text-[var(--text-muted)] uppercase mb-0.5">${title}</span><span class="text-xs font-bold text-[var(--text-main)] truncate">${item.name}</span><span class="text-[11px] font-mono text-green-400 font-bold mt-1 tracking-wider"><i class="fa-solid fa-weight-hanging text-[9px] mr-1 text-green-400/50"></i>${item.size_formatted}</span></div></div><button onclick="eliminarDLCUpdate('${item.path}', '${item.type}', '${item.size_formatted}')" class="w-12 h-12 rounded-2xl bg-red-900/20 border border-red-500/30 text-red-500 flex items-center justify-center shrink-0 hover:bg-red-600 hover:text-white transition-all active:scale-95"><i class="fa-solid fa-trash-can text-lg"></i></button></div>`;
                    });
                    container.innerHTML = html;
                } else { container.innerHTML = `<div class="text-center py-10 text-red-400 text-xs font-bold">${data.message}</div>`; }
            } catch(e) { container.innerHTML = `<div class="text-center py-10 text-red-400 text-xs font-bold">Error de conexión al escanear.</div>`; }
        }

        async function eliminarDLCUpdate(path, type, size) {
            let tipoNombre = type === 'update' ? 'la Actualización' : 'este DLC';
            const seguro = await ps5Confirm("LIBERAR ESPACIO", `¿Estás seguro de eliminar <b>${tipoNombre}</b>?<br><br>Se liberarán <b class="text-green-400">${size}</b>.<br><span class="text-[9px] text-red-400 block mt-2">No se borrará el juego base.</span>`, "fa-dumpster-fire", "bg-red-600 text-white shadow-[0_0_15px_rgba(220,38,38,0.5)] border border-red-500/50");
            if (!seguro) return;
            mostrarCarga("BORRANDO", `Desinstalando...<br><span class='text-[10px] font-bold text-green-400 block mt-1'>Liberando ${size}</span>`, "fa-trash fa-bounce text-red-500");
            const ip = document.getElementById('host-ip') ? document.getElementById('host-ip').value : '';
            const fd = new FormData(); fd.append('action', 'delete'); fd.append('host_ip', ip); fd.append('path', path);
            try {
                let res = await fetch('api/dlc_manager.php', { method: 'POST', body: fd }); let data = await res.json();
                closeCustomModal(); await new Promise(r => setTimeout(r, 350));
                if (data.status === 'success') { if(typeof AudioEngine !== 'undefined') AudioEngine.playSuccess(); ps5Notification("ESPACIO RECUPERADO", `Se han liberado ${size}.`, "fa-broom"); abrirMenuDLCs(); } else { mostrarErrorFinal("ERROR", data.message); }
            } catch(e) { mostrarErrorFinal("ERROR", "Falló la conexión al intentar borrar."); }
        }

        async function iniciarBackupSaves() {
            const ip = document.getElementById('host-ip') ? document.getElementById('host-ip').value : '';
            if (!ip) { await ps5Alert(t('j_err_ip'), t('j_err_ip_m'), 'fa-network-wired'); return; }
            cerrarTodo();
            mostrarCarga("ANALIZANDO", "Buscando partidas en la PS4...", "fa-magnifying-glass fa-bounce");
            document.querySelector('#modal-icon i').style.color = 'var(--theme-prim)';
            const fdCheck = new FormData(); fdCheck.append('action', 'check_saves'); fdCheck.append('host_ip', ip); fdCheck.append('cusa_id', currentCusa);
            try {
                let res = await fetch('api/saves.php', { method: 'POST', body: fdCheck }); let data = await res.json();
                closeCustomModal(); await new Promise(r => setTimeout(r, 350));
                if (data.status === 'success') {
                    const msg = `Se encontraron <b>${data.files} archivos</b> de guardado.<br>Peso total: <b style="color: var(--theme-prim);">${data.size_mb} MB</b><br>Perfiles: <b>${data.users}</b><br><br>¿Deseas comprimir todo y descargar el ZIP a tu celular?`;
                    const confirmar = await ps5Confirm("PARTIDAS ENCONTRADAS", msg, "fa-floppy-disk");
                    if (confirmar) {
                        mostrarCarga("CREANDO BACKUP", "Comprimiendo y descargando...<br><span class='text-[9px] mt-1 block uppercase tracking-widest text-[var(--text-muted)]'>No cierres la app.</span>", "fa-file-zipper fa-bounce");
                        document.querySelector('#modal-icon i').style.color = 'var(--theme-prim)';
                        const fdBackup = new FormData(); fdBackup.append('action', 'backup'); fdBackup.append('host_ip', ip); fdBackup.append('cusa_id', currentCusa);
                        let resBackup = await fetch('api/saves.php', { method: 'POST', body: fdBackup }); let dataBackup = await resBackup.json();
                        if (dataBackup.status === 'success') { closeCustomModal(); ps5Notification("BACKUP", "Descarga iniciada.", "fa-check"); window.location.href = dataBackup.download_url; } else { mostrarErrorFinal("ERROR DE ZIP", dataBackup.message); }
                    }
                } else { ps5Alert("SIN PARTIDAS", data.message, "fa-ghost"); }
            } catch(e) { mostrarErrorFinal("ERROR", "Falló la conexión."); }
        }
        // ==========================================
        // 11. SINCRONIZACIÓN Y LIMPIEZA / OTA
        // ==========================================
        let isSyncCanceled = false;
        function cancelarEnvio() { if (uploadAbortController) uploadAbortController.abort(); isSyncCanceled = true; const fUp = document.getElementById('file-upload'); if(fUp) { fUp.value = ''; updateFileName(fUp); } }

        async function simularSincronizacion() {
            const ip = document.getElementById('host-ip').value; if (!ip) { await ps5Alert(t('j_err_ip'), t('j_err_ip_m'), 'fa-network-wired'); return; }
            mostrarCarga("SINCRONIZANDO", "Buscando en PS4...", "fa-brands fa-playstation"); document.querySelector('#modal-icon i').style.color = 'var(--theme-prim)'; isTransferring = true; isSyncCanceled = false; 
            try {
                const fd = new FormData(); fd.append('action', 'scan_list'); fd.append('host_ip', ip);
                let res = await fetch('api/library.php?_t=' + new Date().getTime(), { method: 'POST', body: fd }); 
                let data = await res.json();
                if (data.status === 'success') {
                    const missing = data.missing; 
                    if (missing.length === 0) { AudioEngine.playSuccess(); closeCustomModal(); ps5Notification("AL DIA", "Biblioteca sincronizada.", "fa-check-double"); cargarBibliotecaLocal(); isTransferring = false; return; }
                    
                    document.getElementById('modal-progress-container').classList.remove('hidden'); 
                    document.getElementById('modal-controls').classList.remove('hidden'); 
                    document.getElementById('modal-action-btn').classList.add('hidden'); 
                    document.getElementById('modal-cancel-btn').classList.remove('hidden'); 
                    
                    let count = 0;
                    for (let game of missing) {
                        if (isSyncCanceled) break; count++; 
                        document.getElementById('modal-title').innerText = `LEYENDO (${count}/${missing.length})`; 
                        let pct = (count / missing.length) * 100; 
                        document.getElementById('modal-progress-bar').style.width = pct + '%'; 
                        document.getElementById('modal-percentage').innerText = pct.toFixed(0) + '%'; 
                        document.getElementById('modal-text').innerText = game.id;
                        
                        const fdData = new FormData(); fdData.append('action', 'get_game_data'); fdData.append('host_ip', ip); fdData.append('cusa_id', game.id);
                        try { await fetch('api/library.php', { method: 'POST', body: fdData }); } catch(e) {}
                    }
                    if (isSyncCanceled) { mostrarErrorFinal("CANCELADO", "Sincronización detenida."); cargarBibliotecaLocal(); } 
                    else { AudioEngine.playSuccess(); closeCustomModal(); ps5Notification("NUEVOS", `${count} títulos cargados.`, "fa-gamepad"); cargarBibliotecaLocal(); }
                } else { mostrarErrorFinal("ERROR", data.message || "No se pudo leer la biblioteca."); }
            } catch (e) { mostrarErrorFinal("ERROR", "Falló la conexión al escanear."); } finally { isTransferring = false; }
        }

        async function limpiarTemporales() {
            const ok = await ps5Confirm("LIMPIAR", "¿Borrar archivos temporales del servidor?", "fa-broom");
            if(!ok) return;
            mostrarCarga("LIMPIANDO", "Borrando caché...", "fa-trash fa-bounce text-red-500");
            try { await fetch('api/library.php?action=clear_temp'); closeCustomModal(); ps5Notification("LIMPIEZA", "Temporales borrados.", "fa-check"); } catch(e) { mostrarErrorFinal("ERROR", "No se pudo limpiar."); }
        }

        async function buscarActualizacionOTA() {
            const confirm = await ps5Confirm("ACTUALIZACION OTA", "¿Buscar e instalar la última versión desde la nube?<br><br><span class='text-[9px] text-[var(--text-muted)] mt-1 block'>Tus juegos y portadas no se perderán.</span>", "fa-cloud-arrow-down");
            if(!confirm) return;
            
            mostrarCarga("ACTUALIZANDO", "Descargando código...", "fa-cloud-arrow-down fa-bounce");
            document.querySelector('#modal-icon i').style.color = 'var(--theme-prim)';
            
            try {
                let res = await fetch('index.php?ota_update=1');
                let data = await res.json();
                
                if (data.status === 'updated') {
                    AudioEngine.playSuccess();
                    ps5Notification("¡ACTUALIZADO!", "Reiniciando app...", "fa-check");
                    setTimeout(() => window.location.reload(), 1500);
                } else if (data.status === 'uptodate') {
                    closeCustomModal();
                    ps5Alert("AL DIA", "Ya tienes la última versión instalada.", "fa-check-double");
                } else {
                    mostrarErrorFinal("ERROR OTA", data.message + "<br><br><span class='text-[8px] font-mono text-red-400 mt-2 block break-all'>" + data.log + "</span>");
                }
            } catch(e) {
                mostrarErrorFinal("ERROR", "Falló la conexión con el servidor interno.");
            }
        }


        // ==========================================
        // 12. RPI SENDER Y FTP CLÁSICO (Y PASARELA PC)
        // ==========================================
        let currentTransferMode = 'ftp';
        let rpiQueue = []; 
        let rpiRawList = []; 

        function switchTransferMode(mode) {
            currentTransferMode = mode;
            let btnRpi = document.getElementById('btn-mode-rpi'), btnFtp = document.getElementById('btn-mode-ftp');
            let boxRpi = document.getElementById('box-mode-rpi'), boxFtp = document.getElementById('ftp-form');
            let floatingFtp = document.getElementById('floating-btn-ftp'), floatingRpi = document.getElementById('floating-btn-rpi');
            
            let classInactive = "flex-1 py-3 text-[9px] font-black tracking-widest rounded-xl text-[var(--text-muted)] hover:text-[var(--text-main)] whitespace-nowrap relative z-10 transition-all";
            let classActive = "flex-1 py-3 text-[9px] font-black tracking-widest rounded-xl text-[var(--text-main)] shadow-lg whitespace-nowrap relative z-10 transition-all";

            if(btnRpi) { btnRpi.className = classInactive; btnRpi.style.backgroundColor = ''; }
            if(btnFtp) { btnFtp.className = classInactive; btnFtp.style.backgroundColor = ''; }
            if(boxRpi) boxRpi.classList.add('hidden');
            if(boxFtp) boxFtp.classList.add('hidden');
            if(floatingFtp) floatingFtp.classList.add('floating-hidden');
            if(floatingRpi) floatingRpi.classList.add('floating-hidden');
            
            let activeBtn = document.getElementById('btn-mode-' + mode);
            if(activeBtn) { activeBtn.className = classActive; activeBtn.style.backgroundColor = 'var(--theme-prim)'; activeBtn.style.color = '#000'; }
            
            if(mode === 'rpi') {
                if(boxRpi) boxRpi.classList.remove('hidden');
                if(currentTabIndex === 1 && rpiQueue.length > 0 && floatingRpi) floatingRpi.classList.remove('floating-hidden');
                renderRpiList();
            } else {
                if(boxFtp) boxFtp.classList.remove('hidden');
                if(currentTabIndex === 1 && floatingFtp && document.getElementById('file-upload').files.length > 0) floatingFtp.classList.remove('floating-hidden');
            }
        }

        // --- PASARELA PC (STREAMING RAM) ---
        async function handlePCUpload(input) {
            if (!input.files || input.files.length === 0) return;
            let file = input.files[0];
            let ps4Ip = document.getElementById('host-ip').value;
            if (!ps4Ip) { await ps5Alert(t('j_err_ip'), t('j_err_ip_m'), "fa-network-wired"); input.value = ''; return; }

            mostrarCarga("PASARELA PC", `Transmitiendo a PS4 vía RAM...<br><span class='text-[10px] font-bold text-yellow-400 block mt-1'>${file.name}</span>`, "fa-computer fa-bounce text-yellow-400");
            document.querySelector('#modal-icon i').style.color = 'var(--theme-prim)';

            document.getElementById('modal-progress-container').classList.remove('hidden');
            document.getElementById('modal-controls').classList.remove('hidden');
            document.getElementById('modal-close-btn').classList.add('hidden');
            document.getElementById('modal-cancel-btn').classList.remove('hidden');
            document.getElementById('modal-action-btn').classList.add('hidden');

            isTransferring = true;
            isSyncCanceled = false;

            const CHUNK_RAM = 50 * 1024 * 1024; // Buffer Volátil de 50MB
            let totalChunks = Math.ceil(file.size / CHUNK_RAM);
            let bytesSent = 0;

            try {
                for (let i = 0; i < totalChunks; i++) {
                    if (isSyncCanceled) break;
                    
                    let start = i * CHUNK_RAM;
                    let end = Math.min(start + CHUNK_RAM, file.size);
                    let chunk = file.slice(start, end);

                    let fd = new FormData();
                    fd.append('action', 'stream_pc_ram');
                    fd.append('host_ip', ps4Ip);
                    fd.append('file_name', file.name);
                    fd.append('chunk', chunk);
                    fd.append('chunk_index', i);
                    fd.append('total_chunks', totalChunks);

                    uploadAbortController = new AbortController();
                    
                    let startTime = Date.now();
                    let res = await fetch('api/upload.php', { 
                        method: 'POST', 
                        body: fd, 
                        signal: uploadAbortController.signal 
                    });
                    
                    let endTime = Date.now();
                    let duration = (endTime - startTime) / 1000;
                    let speed = (chunk.size / 1024 / 1024) / (duration || 0.1);
                    
                    bytesSent += chunk.size;
                    let pct = (bytesSent / file.size) * 100;
                    document.getElementById('modal-progress-bar').style.width = pct + '%';
                    document.getElementById('modal-percentage').innerText = pct.toFixed(0) + '%';
                    document.getElementById('modal-bytes').innerText = `${(bytesSent / 1e9).toFixed(2)} / ${(file.size / 1e9).toFixed(2)} GB`;
                    document.getElementById('modal-speed').innerHTML = `<i class="fa-solid fa-microchip" style="color: var(--theme-prim);"></i> 50MB RAM | ${speed.toFixed(1)} MB/s`;
                }
            } catch(e) {
                if (e.name !== 'AbortError') {
                    mostrarErrorFinal("ERROR DE PUENTE", "Conexión interrumpida en la pasarela RAM.");
                }
            }

            isTransferring = false;
            if (isSyncCanceled) {
                mostrarErrorFinal("CANCELADO", "Transmisión detenida. La RAM fue liberada.");
            } else {
                AudioEngine.playSuccess();
                document.getElementById('modal-title').innerText = "TRANSMISIÓN EXITOSA";
                document.getElementById('modal-text').innerText = "El juego se ha transmitido directo a la PS4.";
                document.getElementById('modal-controls').classList.add('hidden');
                document.getElementById('modal-close-btn').classList.remove('hidden');
                document.getElementById('modal-icon').innerHTML = `<div class="absolute inset-0 rounded-full border animate-ping" style="border-color: var(--theme-prim); opacity: 0.4;"></div><i class="fa-solid fa-check text-4xl relative z-10" style="color: var(--theme-prim);"></i>`;
            }
            input.value = '';
        }
        // ------------------------------------

        function selectPath(btn, path) {
            document.querySelectorAll('.folder-btn').forEach(b => {
                if(b.id !== 'btn-otra') {
                    b.classList.remove('active');
                    b.style.borderColor = 'rgba(255,255,255,0.1)';
                    b.style.color = 'var(--text-muted)';
                    b.style.backgroundColor = 'transparent';
                }
            });
            btn.classList.add('active');
            btn.style.borderColor = 'var(--theme-prim)';
            btn.style.color = 'var(--theme-prim)';
            btn.style.backgroundColor = 'rgba(255,255,255,0.1)';
            document.getElementById('selected-path-input').value = path;
        }

        function showAddPathUI() { document.getElementById('add-path-ui').classList.remove('hidden'); }
        function hideAddPathUI() { document.getElementById('add-path-ui').classList.add('hidden'); }

        function saveNewPath() {
            const input = document.getElementById('new-path-input');
            let newPath = input.value.trim();
            if(newPath) {
                if(!newPath.startsWith('/')) newPath = '/' + newPath;
                if(!newPath.endsWith('/')) newPath = newPath + '/';
                
                crearBotonCarpeta(newPath, true);
                input.value = '';
                hideAddPathUI();
                
                let savedFolders = JSON.parse(localStorage.getItem('ps4_custom_folders')) || [];
                if(!savedFolders.includes(newPath)) {
                    savedFolders.push(newPath);
                    localStorage.setItem('ps4_custom_folders', JSON.stringify(savedFolders));
                }
            }
        }

        function crearBotonCarpeta(newPath, autoSelect = false) {
            const grid = document.getElementById('paths-grid');
            if(!grid) return;
            
            const divWrapper = document.createElement('div');
            divWrapper.className = 'relative w-full h-full group';
            
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'folder-btn w-full h-full btn-ps5 rounded-xl py-3 px-2 text-[11px] font-bold text-[var(--text-muted)] border border-white/10 transition-colors truncate';
            btn.onclick = function() { selectPath(this, newPath); };
            btn.innerText = newPath;
            
            const btnEliminar = document.createElement('div');
            btnEliminar.className = 'absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-600 rounded-full flex items-center justify-center text-white z-10 cursor-pointer shadow-[0_0_10px_rgba(239,68,68,0.5)] border border-red-500 opacity-0 group-hover:opacity-100 transition-opacity';
            btnEliminar.onclick = function(e) { e.stopPropagation(); eliminarCarpetaPersonalizada(newPath, divWrapper); };
            btnEliminar.innerHTML = '<i class="fa-solid fa-xmark text-[10px]"></i>';

            divWrapper.appendChild(btn);
            divWrapper.appendChild(btnEliminar);

            const btnOtra = document.getElementById('btn-otra');
            if(btnOtra) grid.insertBefore(divWrapper, btnOtra);
            if(autoSelect) selectPath(btn, newPath);
        }

        async function eliminarCarpetaPersonalizada(path, divElement) {
            const seguro = await ps5Confirm(t('j_del_route'), t('j_del_route_m') + `<br><b class="text-[var(--text-main)] mt-1 block">${path}</b>`, "fa-trash", "bg-red-600 text-white border border-red-500/50");
            if(!seguro) return;
            
            let savedFolders = JSON.parse(localStorage.getItem('ps4_custom_folders')) || [];
            savedFolders = savedFolders.filter(f => f !== path);
            localStorage.setItem('ps4_custom_folders', JSON.stringify(savedFolders));
            
            divElement.remove();
            ps5Notification(t('j_comp'), "Ruta eliminada.", "fa-trash");
            
            if(document.getElementById('selected-path-input').value === path) {
                const firstPathBtn = document.querySelector('#paths-grid > button.folder-btn');
                if(firstPathBtn) selectPath(firstPathBtn, firstPathBtn.innerText);
            }
        }

        function updateFileName(input) {
            const display = document.getElementById('file-name-display');
            const iconContainer = document.getElementById('upload-icon-container');
            if (input.files && input.files.length > 0) {
                if (input.files.length === 1) {
                    display.innerText = input.files[0].name;
                } else {
                    display.innerText = `${input.files.length} ARCHIVOS`;
                }
                display.classList.replace('text-[var(--text-muted)]', 'text-[var(--text-main)]');
                iconContainer.innerHTML = '<i class="fa-solid fa-check text-2xl text-black"></i>';
                iconContainer.style.backgroundColor = 'var(--theme-prim)';
                iconContainer.style.color = '#000';
                document.getElementById('floating-btn-ftp').classList.remove('floating-hidden');
            } else {
                display.innerText = t('touch_select');
                display.classList.replace('text-[var(--text-main)]', 'text-[var(--text-muted)]');
                iconContainer.innerHTML = '<i class="fa-solid fa-cloud-arrow-up text-2xl"></i>';
                iconContainer.style.backgroundColor = 'color-mix(in srgb, var(--theme-prim) 10%, transparent)';
                iconContainer.style.color = 'var(--theme-prim)';
                document.getElementById('floating-btn-ftp').classList.add('floating-hidden');
            }
        }

        async function actualizarIpCelular() {
            let actual = localStorage.getItem('ps4_phone_ip') || '<?php echo isset($ip_servidor) ? $ip_servidor : ""; ?>';
            let nuevaIp = await ps5Prompt('IP DE TU CELULAR', 'Escribe la IP actual de tu Wi-Fi (Ej: 192.168.0.21):', actual);
            if (nuevaIp && nuevaIp.trim() !== '') {
                localStorage.setItem('ps4_phone_ip', nuevaIp.trim());
                ps5Notification("IP ACTUALIZADA", "IP guardada exitosamente.", "fa-network-wired");
                renderRpiList(); 
            }
        }

        async function renderRpiList() {
            const container = document.getElementById('rpi-pkg-list');
            if(!container) return;
            container.innerHTML = `<div class="col-span-full text-center py-6"><i class="fa-solid fa-circle-notch fa-spin text-3xl mb-4" style="color: var(--theme-prim);"></i><p class="text-[10px] text-[var(--text-muted)] tracking-widest font-black uppercase">CARGANDO LISTA...</p></div>`;
            rpiQueue = [];
            document.getElementById('floating-btn-rpi').classList.add('floating-hidden');
            
            try {
                let res = await fetch('index.php?get_rpi_list=1&_t=' + new Date().getTime());
                let data = await res.json();
                
                if (data.status === 'success') {
                    rpiRawList = data.data;
                    if (rpiRawList.length === 0) {
                        container.innerHTML = `<div class="col-span-full flex flex-col items-center justify-center min-h-[150px] bg-black/40 rounded-[1.5rem] border border-dashed border-white/20 p-6 text-center"><i class="fa-solid fa-box-open text-4xl mb-4 text-[var(--text-muted)]"></i><p class="text-[10px] text-[var(--text-muted)] leading-relaxed">No hay juegos .pkg en la carpeta<br><span class="font-mono bg-black/60 px-1.5 py-0.5 rounded mt-1 inline-block border border-white/10" style="color: var(--theme-prim);">htdocs/servidor_rpi/</span></p><button onclick="renderRpiList()" class="mt-4 px-4 py-2 rounded-lg text-[9px] font-black tracking-widest active:scale-95 transition-transform border hover:text-black hover:bg-white" style="color: var(--theme-prim); border-color: var(--theme-prim);"><i class="fa-solid fa-rotate mr-1"></i> REFRESCAR</button></div>`;
                        return;
                    }
                    
                    let htmlControls = `<div class="col-span-full flex justify-center gap-3 mb-4">
                        <button onclick="actualizarIpCelular()" class="bg-yellow-600/20 text-yellow-400 hover:bg-yellow-600 hover:text-white px-5 py-2 rounded-xl text-[10px] font-black tracking-widest transition-colors active:scale-95 border border-yellow-500/30"><i class="fa-solid fa-pen mr-1"></i> EDITAR IP</button>
                        <button onclick="renderRpiList()" class="hover:text-black px-5 py-2 rounded-xl text-[10px] font-black tracking-widest transition-colors active:scale-95 border hover:bg-white" style="color: var(--theme-prim); border-color: var(--theme-prim);"><i class="fa-solid fa-rotate mr-1"></i> REFRESCAR</button>
                    </div>`;
                    
                    let htmlCards = '';
                    rpiRawList.forEach((pkg, idx) => {
                        let idCard = 'rpi-card-' + idx;
                        let iconOrigen = pkg.origen && pkg.origen.includes('MicroSD') ? '<i class="fa-solid fa-sd-card text-[9px]"></i>' : '<i class="fa-solid fa-mobile-screen text-[9px]"></i>';
                        let origenHtml = pkg.origen ? `<span class="text-[8px] font-mono tracking-widest bg-black/80 px-1.5 py-0.5 rounded border border-white/10 text-white flex items-center gap-1">${iconOrigen} ${pkg.origen.replace(/💾 |📱 /g, '')}</span>` : '';
                        
                        htmlCards += `
                        <div id="${idCard}" class="rpi-card rounded-2xl overflow-hidden aspect-[3/4] flex flex-col relative group bg-black/40 border border-white/5 cursor-pointer shadow-lg" onclick="selectRpiPkg('${pkg.path || pkg.nombre}', '${idCard}')">
                            <div class="rpi-card-check"><i class="fa-solid fa-check"></i></div>
                            <div class="w-full flex-1 relative bg-black/60 flex items-center justify-center overflow-hidden">
                                <i id="icon-${idCard}" class="fa-solid fa-spinner fa-spin text-3xl z-0" style="color: var(--theme-prim); opacity: 0.5;"></i>
                                <img id="img-${idCard}" src="" class="absolute inset-0 w-full h-full object-cover hidden z-10 transition-transform duration-500 group-hover:scale-105">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent z-20 pointer-events-none"></div>
                                <div class="absolute bottom-0 left-0 right-0 p-2.5 z-30 flex flex-col gap-1 pointer-events-none">
                                    <div id="title-container-${idCard}" class="w-full">
                                        <h3 class="text-[10px] font-black text-white leading-tight truncate drop-shadow-md">Cargando...</h3>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1">
                                        ${origenHtml}
                                        <span id="cusa-${idCard}" class="text-[8px] font-mono font-bold tracking-widest bg-black/80 px-1.5 py-0.5 rounded border border-white/10 text-white truncate hidden"></span>
                                        <span class="text-[8px] font-mono tracking-widest bg-black/80 px-1.5 py-0.5 rounded border border-white/10 text-white">${pkg.size_fmt}</span>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    });
                    
                    container.innerHTML = htmlControls + htmlCards;
                    procesarColaMetadatosRPI([...rpiRawList]);
                }
            } catch(e) {
                container.innerHTML = `<div class="col-span-full text-center text-red-400 text-xs font-bold py-6">Error al cargar la lista.</div>`;
            }
        }

        async function procesarColaMetadatosRPI(cola) {
            if (cola.length === 0) return;
            let pkg = cola.shift();
            let idx = rpiRawList.findIndex(p => p.nombre === pkg.nombre);
            let idCard = 'rpi-card-' + idx;
            
            try {
                let res = await fetch('index.php?extract_pkg=' + encodeURIComponent(pkg.nombre));
                let data = await res.json();
                
                if (data.status === 'success') {
                    let titleContainer = document.getElementById('title-container-' + idCard);
                    let cusaEl = document.getElementById('cusa-' + idCard);
                    let iconEl = document.getElementById('icon-' + idCard);
                    let imgEl = document.getElementById('img-' + idCard);
                    
                    if (titleContainer) {
                        let cleanTitle = data.data.title.replace(/<[^>]*>?/gm, '');
                        cleanTitle = cleanTitle.replace(/CUSA\d{5}/gi, '')
                                               .replace(/v\d+\.\d+/gi, '')
                                               .replace(/\[.*?\]/g, '')
                                               .replace(/\(COPY\)/gi, '')
                                               .replace(/\.pkg/gi, '')
                                               .replace(/[-_]/g, ' ')
                                               .replace(/\s{2,}/g, ' ')
                                               .trim();
                        
                        if(!cleanTitle || cleanTitle === "") cleanTitle = pkg.nombre;

                        if (cleanTitle.length > 20) {
                            titleContainer.innerHTML = `<marquee behavior="alternate" scrollamount="1"><h3 class="text-[10px] font-black text-white leading-tight inline uppercase">${cleanTitle}</h3></marquee>`;
                        } else {
                            titleContainer.innerHTML = `<h3 class="text-[10px] font-black text-white leading-tight truncate uppercase">${cleanTitle}</h3>`;
                        }
                    }
                    
                    if (cusaEl && data.data.cusa && data.data.cusa !== 'UNKNOWN') {
                        cusaEl.innerText = data.data.cusa;
                        cusaEl.classList.remove('hidden');
                    }
                    
                    if (data.data.icon !== '') {
                        if (imgEl) {
                            imgEl.src = data.data.icon + '?v=' + new Date().getTime();
                            imgEl.onload = () => { imgEl.classList.remove('hidden'); if(iconEl) iconEl.classList.add('hidden'); };
                        }
                    } else {
                        if (iconEl) { iconEl.classList.remove('fa-spinner', 'fa-spin'); iconEl.style.color = ''; iconEl.style.opacity = '1'; iconEl.classList.add('fa-gamepad', 'text-white/20'); }
                    }
                }
            } catch (e) {
                let iconEl = document.getElementById('icon-' + idCard);
                if (iconEl) { iconEl.classList.remove('fa-spinner', 'fa-spin'); iconEl.style.color = ''; iconEl.style.opacity = '1'; iconEl.classList.add('fa-triangle-exclamation', 'text-yellow-500/50'); }
            }
            
            procesarColaMetadatosRPI(cola);
        }

        function selectRpiPkg(path, cardId) {
            const card = document.getElementById(cardId);
            const index = rpiQueue.indexOf(path);
            
            if (index > -1) {
                rpiQueue.splice(index, 1);
                card.classList.remove('selected');
            } else {
                rpiQueue.push(path);
                card.classList.add('selected');
            }
            
            const floatingBtn = document.getElementById('floating-btn-rpi');
            const installText = document.getElementById('rpi-install-text');
            
            if (rpiQueue.length > 0) {
                installText.innerText = `INSTALAR (${rpiQueue.length})`;
                floatingBtn.classList.remove('floating-hidden');
            } else {
                floatingBtn.classList.add('floating-hidden');
            }
        }

        async function iniciarColaInstalacionRPI() {
            if(rpiQueue.length === 0) { await ps5Alert(t('j_err_file'), "Selecciona al menos un juego de la lista.", "fa-hand-pointer"); return; }
            const ps4Ip = document.getElementById('host-ip').value;
            if(!ps4Ip) { await ps5Alert(t('j_err_ip'), t('j_err_ip_m'), "fa-network-wired"); return; }
            
            let phoneIp = '<?php echo isset($ip_servidor) ? $ip_servidor : "127.0.0.1"; ?>';
            let storedIp = localStorage.getItem('ps4_phone_ip');
            if (storedIp && storedIp.trim() !== '') phoneIp = storedIp.trim();
            
            let getSubnet = (ip) => ip.split('.').slice(0, 3).join('.');
            if (getSubnet(ps4Ip) !== getSubnet(phoneIp) || phoneIp === '127.0.0.1' || phoneIp === 'localhost' || phoneIp === '::1' || phoneIp.startsWith('fe80:')) {
                let advertencia = await ps5Confirm("ADVERTENCIA DE RED", `Detectamos que tu celular y la PS4 están en redes diferentes o Android bloqueó la IP automática.<br><br>Celular (Wi-Fi): <b class="text-yellow-400">${phoneIp}</b><br>PS4: <b style="color: var(--theme-prim);">${ps4Ip}</b><br><br>Para evitar que el envío falle, toca "CANCELAR", luego ve al botón "EDITAR IP" y coloca tu IP Wi-Fi actual. ¿O quieres forzar el envío de todos modos?`, "fa-network-wired", "bg-yellow-500 text-black border border-yellow-400/50");
                if (!advertencia) return;
            }

            let baseUrl = window.location.protocol + '//' + phoneIp + ':8081';

            document.getElementById('custom-modal').classList.remove('hidden', 'opacity-0'); 
            document.getElementById('modal-card').classList.remove('scale-95');
            document.getElementById('modal-progress-container').classList.remove('hidden'); 
            document.getElementById('modal-controls').classList.add('hidden'); 
            document.getElementById('modal-close-btn').classList.add('hidden');
            document.getElementById('modal-icon').innerHTML = '<div class="absolute inset-0 rounded-full border border-yellow-500/50 animate-ping"></div><i class="fa-solid fa-satellite-dish text-4xl text-yellow-400 relative z-10 animate-pulse"></i>';
            
            let exitos = 0;
            let errores = [];
            
            for (let i = 0; i < rpiQueue.length; i++) {
                let selectedPath = rpiQueue[i]; 
                
                if (!selectedPath.includes('servidor_rpi/')) {
                    selectedPath = 'servidor_rpi/' + selectedPath;
                }

                let pathParts = selectedPath.split('/');
                
                // SANITIZADOR EXTREMO PARA RPI SENDER (Reemplaza los caracteres que la PS4 rechaza)
                let encodedPath = pathParts.map(p => {
                    return encodeURIComponent(p)
                        .replace(/'/g, "%27")
                        .replace(/\+/g, "%2B")
                        .replace(/\(/g, "%28")
                        .replace(/\)/g, "%29")
                        .replace(/\[/g, "%5B")
                        .replace(/\]/g, "%5D");
                }).join('/');
                
                let urlToSend = baseUrl + '/' + encodedPath;
                let displayName = pathParts[pathParts.length - 1]; 
                
                document.getElementById('modal-title').innerText = `ENVIANDO (${i+1}/${rpiQueue.length})`;
                document.getElementById('modal-text').innerHTML = `Instalando:<br><b class="text-[10px] break-all uppercase" style="color: var(--theme-prim);">${displayName}</b>`;
                
                let pct = ((i) / rpiQueue.length) * 100;
                document.getElementById('modal-progress-bar').style.width = pct + '%';
                document.getElementById('modal-percentage').innerText = pct.toFixed(0) + '%';
                
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 12000); 

                try {
                    let res = await fetch('index.php?rpi_proxy=1', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ip: ps4Ip, url_pkg: urlToSend }),
                        signal: controller.signal
                    });
                    clearTimeout(timeoutId);
                    let data = await res.json();
                    
                    if(data.status === 'success') { exitos++; await new Promise(r => setTimeout(r, 1500)); } 
                    else { errores.push(`${displayName}: Rechazado por la consola.`); }
                } catch(e) {
                    clearTimeout(timeoutId);
                    if (e.name === 'AbortError') errores.push(`${displayName}: Tiempo agotado (Timeout).`);
                    else errores.push(`${displayName}: Error de red local.`);
                }
            }
            
            document.getElementById('modal-progress-bar').style.width = '100%';
            document.getElementById('modal-percentage').innerText = '100%';
            closeCustomModal();
            
            setTimeout(() => {
                if (errores.length === 0) {
                    AudioEngine.playSuccess(); ps5Notification("INSTALACIÓN", `Se enviaron ${exitos} juegos a la consola.`, "fa-check-double"); renderRpiList(); 
                } else {
                    AudioEngine.playError();
                    let logErrores = errores.join('\n');
                    ps5Alert("COMPLETADO CON ERRORES", `Se enviaron ${exitos} juegos, pero fallaron ${errores.length}.<br><textarea class="w-full h-24 bg-black/80 text-red-400 font-mono text-[9px] mt-2 p-2 rounded-xl border border-red-500/30 custom-scrollbar shadow-inner" readonly>${logErrores}</textarea>`, "fa-triangle-exclamation");
                }
            }, 400);
        }

        // ==========================================
        // 13. EXPLORADOR FTP: VISOR, DESCARGA Y MULTI-MOVER
        // ==========================================
        function renderShortcuts() { let shortcuts = JSON.parse(localStorage.getItem('ps4_explorer_shortcuts')) || []; const container = document.getElementById('explorer-shortcuts'); if(shortcuts.length === 0) { container.classList.add('hidden'); return; } container.classList.remove('hidden'); let html = '<i class="fa-solid fa-star text-yellow-500 text-[10px] mr-1 shrink-0"></i>'; shortcuts.forEach(path => { let name = path === '/' ? 'RAÍZ' : path.split('/').filter(Boolean).pop(); html += `<div class="flex items-center gap-1 bg-white/5 border border-white/10 hover:bg-white/10 px-3 py-1.5 rounded-full text-[10px] font-bold text-[var(--text-main)] cursor-pointer whitespace-nowrap group"><span onclick="loadExplorerPath('${path}')" class="truncate max-w-[80px]">${name}</span><div onclick="removeShortcut('${path}', event)" class="w-4 h-4 rounded-full bg-black/40 hover:bg-red-500/80 flex items-center justify-center ml-1 transition-colors"><i class="fa-solid fa-xmark text-[10px] text-[var(--text-muted)] group-hover:text-white"></i></div></div>`; }); container.innerHTML = html; }
        function addCurrentPathToShortcuts() { if(!currentExplorerPath) return; let shortcuts = JSON.parse(localStorage.getItem('ps4_explorer_shortcuts')) || []; if(!shortcuts.includes(currentExplorerPath)) { shortcuts.push(currentExplorerPath); localStorage.setItem('ps4_explorer_shortcuts', JSON.stringify(shortcuts)); renderShortcuts(); ps5Notification(t('j_comp'), "Ruta añadida.", "fa-star"); } }
        async function removeShortcut(path, e) { e.stopPropagation(); const seguro = await ps5Confirm(t('opt_delete'), `¿Quitar acceso a <br><b class="mt-1 block" style="color: var(--theme-prim);">${path}</b>?`, 'fa-star-half-stroke', 'bg-red-600 text-white border border-red-500/50'); if(!seguro) return; let shortcuts = JSON.parse(localStorage.getItem('ps4_explorer_shortcuts')) || []; shortcuts = shortcuts.filter(p => p !== path); localStorage.setItem('ps4_explorer_shortcuts', JSON.stringify(shortcuts)); renderShortcuts(); ps5Notification(t('j_comp'), "Removido.", "fa-trash"); }
        function toggleSelectMode() { isSelectMode = !isSelectMode; selectedItems = []; const btn = document.getElementById('btn-select-mode'), panel = document.getElementById('multi-action-panel'); if (isSelectMode) { btn.style.backgroundColor = 'var(--theme-prim)'; btn.style.color = 'black'; btn.style.borderColor = 'var(--theme-prim)'; btn.classList.remove('bg-white/5', 'border-white/10', 'text-white'); panel.classList.remove('hidden'); } else { btn.style.backgroundColor = ''; btn.style.color = ''; btn.style.borderColor = ''; btn.classList.add('bg-white/5', 'border-white/10', 'text-white'); panel.classList.add('hidden'); } if(currentExplorerItems) renderExplorer(currentExplorerItems, currentExplorerPath); }
        function toggleSelectItem(path, isDir, name) { let idx = selectedItems.findIndex(i => i.path === path); if(idx > -1) { selectedItems.splice(idx, 1); } else { selectedItems.push({path, isDir, name}); } document.getElementById('multi-select-count').innerText = `${selectedItems.length} ${t('j_sel')}`; renderExplorer(currentExplorerItems, currentExplorerPath); }
        async function deleteSelectedItems() { if (selectedItems.length === 0) return; const seguro1 = await ps5Confirm(t('j_del_sel'), `${t('j_del_m1')} <b class="text-[var(--text-main)]">${selectedItems.length} ${t('j_elem')}</b>?`, 'fa-trash', 'bg-red-600 text-white border border-red-500/50'); if(!seguro1) return; const seguro2 = await ps5Confirm(t('j_warn'), t('j_warn_m'), 'fa-triangle-exclamation', 'bg-red-600 text-white border border-red-500/50'); if(!seguro2) return; mostrarCarga(t('j_del_sel'), `Borrando...`, "fa-trash fa-bounce text-red-500"); let successCount = 0; const ip = document.getElementById('host-ip').value; isTransferring = true; for (let i = 0; i < selectedItems.length; i++) { let item = selectedItems[i]; const fd = new FormData(); fd.append('action', 'delete_item'); fd.append('host_ip', ip); fd.append('path', item.path); fd.append('is_dir', item.isDir); try { let res = await fetch('api/explorer.php', { method: 'POST', body: fd }); let data = await res.json(); if(data.status === 'success') successCount++; } catch(e) {} } isTransferring = false; closeCustomModal(); toggleSelectMode(); loadExplorerPath(currentExplorerPath); ps5Notification(t('j_comp'), `${successCount} borrados.`, "fa-trash"); }
        async function loadExplorerPath(path) { const ip = document.getElementById('host-ip').value; if(!ip) return; if(isSelectMode && path !== currentExplorerPath) toggleSelectMode(); document.getElementById('explorer-path-text').innerText = path; currentExplorerPath = path; document.getElementById('explorer-list').innerHTML = `<div class="text-center text-[10px] tracking-widest font-black uppercase py-10" style="color: var(--theme-prim); opacity: 0.5;"><i class="fa-solid fa-circle-notch fa-spin text-3xl mb-3 block"></i>${t('searching')}</div>`; const fd = new FormData(); fd.append('action', 'list_dir'); fd.append('host_ip', ip); fd.append('path', path); isTransferring = true; try { let res = await fetch('api/explorer.php', { method: 'POST', body: fd }); let data = await res.json(); if (data.status === 'success') renderExplorer(data.data, path); else document.getElementById('explorer-list').innerHTML = `<div class="text-center text-red-400 text-xs py-10 font-bold">${data.message}</div>`; } catch(e) { } finally { isTransferring = false; } }
        function renderExplorer(items, currentPath) { currentExplorerItems = items; const listContainer = document.getElementById('explorer-list'); listContainer.innerHTML = ''; if (currentPath !== '/') { let parentPath = currentPath.substring(0, currentPath.lastIndexOf('/', currentPath.length - 2)) + '/'; if(parentPath === '') parentPath = '/'; listContainer.innerHTML += `<div onclick="loadExplorerPath('${parentPath}')" class="flex items-center gap-4 p-3 rounded-xl hover:bg-white/5 cursor-pointer transition-colors border border-transparent"><i class="fa-solid fa-level-up-alt text-xl" style="color: var(--theme-prim); opacity: 0.5;"></i><span class="text-sm font-medium text-[var(--text-muted)]">Volver</span></div>`; } items.forEach(item => { let isDir = item.is_dir; let icon = isDir ? 'fa-folder text-yellow-500/80' : 'fa-file-lines text-white/40'; let nextPath = currentPath.endsWith('/') ? currentPath + item.name : currentPath + '/' + item.name; let isChecked = selectedItems.some(i => i.path === nextPath); let cleanName = item.name.replace(/'/g, "\\'"); let clickAction = isSelectMode ? `onclick="toggleSelectItem('${nextPath}', ${isDir}, '${cleanName}')"` : (isDir ? `onclick="loadExplorerPath('${nextPath}')"` : ''); let checkboxStyle = isChecked ? 'background-color: var(--theme-prim); border-color: var(--theme-prim);' : ''; let checkboxHTML = isSelectMode ? `<div class="w-5 h-5 rounded border border-white/30 flex items-center justify-center shrink-0 mr-1" style="${checkboxStyle}"><i class="fa-solid fa-check text-[10px] text-black ${isChecked ? 'opacity-100' : 'opacity-0'}"></i></div>` : ''; let activeBorderStyle = isChecked ? 'border-color: var(--theme-prim);' : 'border-color: transparent;'; listContainer.innerHTML += `<div class="flex items-center justify-between p-3 rounded-xl transition-colors border ${isChecked ? 'bg-black/40' : 'hover:bg-white/5'}" style="${activeBorderStyle}"><div class="flex items-center gap-3 flex-1 cursor-pointer overflow-hidden" ${clickAction}>${checkboxHTML}<i class="fa-solid ${icon} text-2xl w-6 text-center shrink-0"></i><div class="flex flex-col overflow-hidden"><span class="text-sm font-medium text-[var(--text-main)] tracking-wide truncate pr-2">${item.name}</span></div></div>${!isSelectMode ? `<button onclick="openItemOptions('${nextPath}', '${cleanName}', ${isDir})" class="text-[var(--text-muted)] hover:text-[var(--text-main)] p-2 px-3 shrink-0 transition-colors"><i class="fa-solid fa-ellipsis-vertical"></i></button>` : ''}</div>`; }); }
        function openItemOptions(path, name, isDir) { optionsPath = path; optionsName = name; optionsIsDir = isDir; document.getElementById('sheet-title').innerText = name; if(isDir) { document.getElementById('btn-view-file').classList.add('hidden'); document.getElementById('btn-download-file').classList.add('hidden'); } else { document.getElementById('btn-view-file').classList.remove('hidden'); document.getElementById('btn-download-file').classList.remove('hidden'); } document.getElementById('overlay-sheet').classList.add('open'); document.getElementById('bottom-sheet').classList.add('open'); }
        function closeItemOptions() { document.getElementById('overlay-sheet').classList.remove('open'); document.getElementById('bottom-sheet').classList.remove('open'); }
        async function viewCurrentFile() { closeItemOptions(); mostrarCarga("LEYENDO", "Obteniendo archivo...", "fa-file-lines fa-bounce"); document.querySelector('#modal-icon i').style.color = 'var(--theme-prim)'; const ip = document.getElementById('host-ip').value; const fd = new FormData(); fd.append('action', 'read_file'); fd.append('host_ip', ip); fd.append('path', optionsPath); try { let res = await fetch('api/explorer.php', { method: 'POST', body: fd }); let data = await res.json(); closeCustomModal(); if (data.status === 'success') { const viewer = document.getElementById('file-viewer-modal'), content = document.getElementById('file-viewer-content'); document.getElementById('file-viewer-title').innerText = optionsName; if (data.type === 'image') { content.innerHTML = `<img src="${data.data}" class="max-w-full max-h-full object-contain rounded-lg shadow-[0_0_30px_rgba(0,0,0,0.8)] border border-white/10">`; } else { content.innerHTML = `<pre class="text-[10px] text-[var(--text-muted)] font-mono whitespace-pre-wrap break-words w-full h-full text-left bg-black/60 p-4 rounded-xl border shadow-inner" style="border-color: var(--theme-prim);">${data.data}</pre>`; } viewer.classList.remove('hidden'); setTimeout(() => viewer.classList.remove('opacity-0'), 10); } else { ps5Alert(t('j_err'), data.message, "fa-triangle-exclamation"); } } catch(e) { closeCustomModal(); ps5Alert(t('j_err'), "Error al leer.", "fa-wifi"); } }
        function closeFileViewer() { const viewer = document.getElementById('file-viewer-modal'); viewer.classList.add('opacity-0'); setTimeout(() => { viewer.classList.add('hidden'); document.getElementById('file-viewer-content').innerHTML = ''; }, 300); }
        function downloadCurrentFile() { closeItemOptions(); const ip = document.getElementById('host-ip').value; window.location.href = `api/explorer.php?action=download&host_ip=${ip}&path=${encodeURIComponent(optionsPath)}`; ps5Notification(t('j_comp'), "Iniciada.", "fa-download"); }
        async function renameCurrentItem() { closeItemOptions(); let newName = await ps5Prompt(t('j_ren'), t('j_ren_m'), optionsName); if(!newName || newName === optionsName) return; let pathParts = optionsPath.split('/').filter(Boolean); pathParts.pop(); let basePath = '/' + pathParts.join('/') + (pathParts.length > 0 ? '/' : ''); let newPath = basePath + newName + (optionsIsDir ? '/' : ''); const fd = new FormData(); fd.append('action', 'rename'); fd.append('host_ip', document.getElementById('host-ip').value); fd.append('old_path', optionsPath); fd.append('new_path', newPath); isTransferring = true; try { let res = await fetch('api/explorer.php', { method: 'POST', body: fd }); let data = await res.json(); if(data.status === 'success') { loadExplorerPath(currentExplorerPath); ps5Notification(t('j_comp'), "Éxito.", "fa-pen"); } } catch(e) {} finally { isTransferring = false; } }
        function cutCurrentItem() { closeItemOptions(); clipboardItems = [{path: optionsPath, name: optionsName, isDir: optionsIsDir}]; isCutMode = true; document.getElementById('clipboard-panel').classList.remove('hidden'); document.getElementById('clipboard-text').innerText = optionsName; ps5Notification(t('j_comp'), "Listo para mover.", "fa-scissors"); }
        function cutSelectedItems() { if (selectedItems.length === 0) return; clipboardItems = [...selectedItems]; isCutMode = true; toggleSelectMode(); document.getElementById('clipboard-panel').classList.remove('hidden'); document.getElementById('clipboard-text').innerText = `${clipboardItems.length} copiados`; ps5Notification(t('j_comp'), "Listos para mover.", "fa-scissors"); }
        function cancelPaste() { clipboardItems = []; isCutMode = false; document.getElementById('clipboard-panel').classList.add('hidden'); ps5Notification(t('j_cancel'), "", "fa-xmark"); }
        async function executePaste() { if(clipboardItems.length === 0) return; const ip = document.getElementById('host-ip').value; mostrarCarga("MOVIENDO", "Reubicando...", "fa-people-carry-box fa-bounce"); document.querySelector('#modal-icon i').style.color = 'var(--theme-prim)'; let successCount = 0; isTransferring = true; for (let item of clipboardItems) { let name = item.name || item.path.split('/').filter(Boolean).pop(); let newPath = currentExplorerPath.endsWith('/') ? currentExplorerPath + name : currentExplorerPath + '/' + name; const fd = new FormData(); fd.append('action', 'rename'); fd.append('host_ip', ip); fd.append('old_path', item.path); fd.append('new_path', newPath); try { let res = await fetch('api/explorer.php', { method: 'POST', body: fd }); let data = await res.json(); if(data.status === 'success') successCount++; } catch(e) {} } isTransferring = false; cancelPaste(); closeCustomModal(); loadExplorerPath(currentExplorerPath); ps5Notification(t('j_comp'), `${successCount} reubicados.`, "fa-check"); }
        async function deleteCurrentItem() { closeItemOptions(); const seguro1 = await ps5Confirm(t('j_del1'), `${t('j_del1')} <b class="text-[var(--text-main)]">${optionsName}</b>?`, 'fa-trash', 'bg-red-600 text-white border border-red-500/50'); if(!seguro1) return; const seguro2 = await ps5Confirm(t('j_warn'), t('j_warn_m'), 'fa-triangle-exclamation', 'bg-red-600 text-white border border-red-500/50'); if(!seguro2) return; const fd = new FormData(); fd.append('action', 'delete_item'); fd.append('host_ip', document.getElementById('host-ip').value); fd.append('path', optionsPath); fd.append('is_dir', optionsIsDir); isTransferring = true; try { let res = await fetch('api/explorer.php', { method: 'POST', body: fd }); let data = await res.json(); if(data.status === 'success') { loadExplorerPath(currentExplorerPath); ps5Notification(t('j_comp'), "Éxito.", "fa-trash"); } } catch(e) {} finally { isTransferring = false; } }
        async function promptCreateFolder() { let name = await ps5Prompt(t('j_new_fold'), t('j_new_fold_m')); if(!name) return; let newPath = currentExplorerPath.endsWith('/') ? currentExplorerPath + name : currentExplorerPath + '/' + name; const fd = new FormData(); fd.append('action', 'mkdir'); fd.append('host_ip', document.getElementById('host-ip').value); fd.append('path', newPath); isTransferring = true; try { let res = await fetch('api/explorer.php', { method: 'POST', body: fd }); let data = await res.json(); if(data.status === 'success') { loadExplorerPath(currentExplorerPath); ps5Notification(t('j_comp'), "Creada.", "fa-folder-plus"); } } catch(e) {} finally { isTransferring = false; } }

        // ==========================================
        // 14. GALERÍAS Y MODDING (ICONOS)
        // ==========================================
        function cargarGaleria(lista, containerId, folder) {
            const container = document.getElementById(containerId); container.innerHTML = '';
            if (!lista || lista.length === 0) { container.innerHTML = `<div class="flex flex-col items-center justify-center min-h-[220px] bg-black/40 rounded-[1.5rem] border border-dashed border-white/10 p-6 text-center shadow-inner"><i class="fa-solid fa-folder text-6xl mb-4" style="color: var(--theme-prim); opacity: 0.3;"></i><p class="text-[11px] text-[var(--text-muted)] leading-relaxed max-w-[250px] mx-auto">${t('empty_gal_title').replace('{folder}', folder)}</p></div>`; return; }
            const topBar = document.createElement('div'); topBar.className = 'flex justify-between items-center mb-3 px-1'; topBar.innerHTML = `<span class="text-[10px] font-black tracking-widest uppercase" style="color: var(--theme-prim); opacity: 0.6;">${lista.length} PORTADAS</span><button onclick="eliminarTodasLasImagenes('${folder}')" class="text-red-500 hover:text-red-400 text-[10px] font-black tracking-widest transition-colors active:scale-95"><i class="fa-solid fa-trash-can mr-1"></i> ${t('del_all')}</button>`; container.appendChild(topBar);
            
            // FIX GALERIA 4 COLUMNAS
            const grid = document.createElement('div'); grid.className = 'grid grid-cols-4 gap-1.5 overflow-y-auto custom-scrollbar pr-1 gallery-grid-fix'; 
            
            const deseleccionarEnScroll = () => { const items = grid.querySelectorAll('.gallery-item.selected'); if(items.length > 0) { items.forEach(el => el.classList.remove('selected')); selectedIconValue = null; const btnAplicar = document.getElementById('floating-btn-aplicar'); if(btnAplicar) btnAplicar.classList.add('floating-hidden'); } }; grid.addEventListener('scroll', deseleccionarEnScroll, { passive: true }); grid.addEventListener('touchmove', deseleccionarEnScroll, { passive: true });
            lista.forEach(img => { const item = document.createElement('div'); item.className = 'gallery-item group border border-white/5 transition-all'; item.onclick = function() { document.querySelectorAll(`#${containerId} .gallery-item`).forEach(el => { el.classList.remove('selected'); el.style.borderColor = 'transparent'; }); this.classList.add('selected'); this.style.borderColor = 'var(--theme-prim)'; selectedIconValue = folder + '/' + img.nombre; document.getElementById('floating-btn-aplicar').classList.remove('floating-hidden'); }; item.innerHTML = `<img src="${img.url}" loading="lazy"><div onclick="eliminarImagenGaleria('${img.nombre}', '${folder}', event)" class="absolute top-1 right-1 w-7 h-7 bg-red-600 rounded-full flex items-center justify-center text-white z-10 cursor-pointer shadow-[0_0_10px_rgba(239,68,68,0.5)] gallery-trash-btn border border-red-500"><i class="fa-solid fa-trash text-[10px]"></i></div>`; grid.appendChild(item); });
            container.appendChild(grid);
        }
        
        async function eliminarImagenGaleria(nombre, folder, e) { e.stopPropagation(); const seguro = await ps5Confirm(t('opt_delete'), `¿Borrar la imagen <b>${nombre}</b> del servidor?`, "fa-trash", "bg-red-600 text-white border border-red-500/50 shadow-[0_0_15px_rgba(239,68,68,0.4)]"); if(!seguro) return; const fd = new FormData(); fd.append('action', 'delete_image'); fd.append('folder', folder); fd.append('file_name', nombre); try { let res = await fetch('api/gallery.php', { method:'POST', body: fd }); let data = await res.json(); if(data.status === 'success') { if(folder === 'iconos') actualizarGaleria(); else actualizarBackups(); ps5Notification(t('j_comp'), "Imagen borrada del servidor.", "fa-trash"); document.getElementById('floating-btn-aplicar').classList.add('floating-hidden'); } else { ps5Alert(t('j_err'), data.message, "fa-triangle-exclamation"); } } catch(err) {} }
        async function eliminarTodasLasImagenes(folder) { const seguro = await ps5Confirm(t('del_all'), `¿Estás seguro de eliminar <b class="text-[var(--text-main)]">TODAS</b> las imágenes?`, "fa-skull-crossbones", "bg-red-600 text-white border border-red-500/50"); if(!seguro) return; mostrarCarga(t('j_del_sel'), "Eliminando imágenes...", "fa-trash fa-bounce text-red-500"); let list = folder === 'iconos' ? ICONOS_LOCALES : BACKUPS_LOCALES; for(let img of list) { const fd = new FormData(); fd.append('action', 'delete_image'); fd.append('folder', folder); fd.append('file_name', img.nombre); try { await fetch('api/gallery.php', { method:'POST', body: fd }); } catch(e){} } closeCustomModal(); if(folder === 'iconos') actualizarGaleria(); else actualizarBackups(); ps5Notification(t('j_comp'), "Carpeta vaciada completamente.", "fa-trash-can"); document.getElementById('floating-btn-aplicar').classList.add('floating-hidden'); }
        function previewLocal(input) { if (input.files && input.files[0]) { const reader = new FileReader(); reader.onload = function(e) { document.getElementById('preview-img-local').src = e.target.result; document.getElementById('preview-img-local').classList.remove('hidden'); document.getElementById('icon-file-placeholder').classList.add('hidden'); document.getElementById('icon-file-name').innerText = input.files[0].name; document.getElementById('floating-btn-aplicar').classList.remove('floating-hidden'); }; reader.readAsDataURL(input.files[0]); } }
        function ejecutarFormIconos() { const form = document.getElementById('icon-form'); if (form.reportValidity()) document.getElementById('icon-form-submit').click(); }
        function switchIconSource(type) { currentIconSource = type; ['btn-src-gallery', 'btn-src-backup', 'btn-src-import', 'btn-src-local'].forEach(id => { let btn = document.getElementById(id); btn.className = "flex-1 py-3 px-3 text-[9px] font-black tracking-widest rounded-xl text-[var(--text-muted)] hover:text-[var(--text-main)] transition-colors whitespace-nowrap"; btn.style.backgroundColor = ''; btn.style.color = ''; btn.style.boxShadow = ''; }); ['box-src-gallery', 'box-src-backup', 'box-src-import', 'box-src-local'].forEach(id => document.getElementById(id).classList.add('hidden')); let activeBtn = document.getElementById(`btn-src-${type}`); activeBtn.className = "flex-1 py-3 px-3 text-[9px] font-black tracking-widest rounded-xl whitespace-nowrap"; activeBtn.style.backgroundColor = 'var(--theme-prim)'; activeBtn.style.color = '#000'; document.getElementById(`box-src-${type}`).classList.remove('hidden'); if(type === 'import') document.getElementById('floating-btn-aplicar').classList.add('floating-hidden'); else document.getElementById('floating-btn-aplicar').classList.remove('floating-hidden'); if(type === 'gallery') actualizarGaleria(); if(type === 'backup') actualizarBackups(); }
        async function importarURL() { const urlInput = document.getElementById('import-url').value.trim(); if (!urlInput) return; mostrarCarga("IMPORTANDO", "Descargando imágenes...", "fa-cloud-arrow-down fa-bounce"); document.querySelector('#modal-icon i').style.color = 'var(--theme-prim)'; isTransferring = true; const fd = new FormData(); fd.append('action', 'import_url'); fd.append('url', urlInput); try { let res = await fetch('api/gallery.php', { method: 'POST', body: fd }); let data = await res.json(); if (data.status === 'success') { AudioEngine.playSuccess(); closeCustomModal(); document.getElementById('import-url').value = ''; await actualizarGaleria(); switchIconSource('gallery'); ps5Notification(t('j_comp'), "Imágenes guardadas.", "fa-download"); } else { mostrarErrorFinal(t('j_err'), data.message); } } catch (e) { mostrarErrorFinal(t('j_err'), "Error de red."); } finally { isTransferring = false; } }
        async function actualizarGaleria() { try { let res = await fetch('api/gallery.php?action=get_gallery&_t=' + new Date().getTime()); let data = await res.json(); if (data.status === 'success') { ICONOS_LOCALES = data.data; cargarGaleria(ICONOS_LOCALES, 'gallery-container', 'iconos'); } } catch(e) {} }
        async function actualizarBackups() { try { let res = await fetch('api/gallery.php?action=get_backups&_t=' + new Date().getTime()); let data = await res.json(); if (data.status === 'success') { BACKUPS_LOCALES = data.data; cargarGaleria(BACKUPS_LOCALES, 'backup-container', 'backup_icons'); } } catch(e) {} }
        async function respaldarOriginal() { const ip = document.getElementById('host-ip').value, cusa = document.getElementById('icon-cusa').value.trim().toUpperCase(); if (!ip || !cusa) return; mostrarCarga("EXTRAYENDO", "Descargando portada...", "fa-download fa-bounce"); document.querySelector('#modal-icon i').style.color = 'var(--theme-prim)'; isTransferring = true; const fd = new FormData(); fd.append('action', 'backup_original'); fd.append('host_ip', ip); fd.append('cusa_id', cusa); try { let res = await fetch('api/modding.php', { method: 'POST', body: fd }); let data = await res.json(); if (data.status === 'success') { AudioEngine.playSuccess(); closeCustomModal(); switchIconSource('backup'); ps5Notification(t('j_comp'), `Portada guardada.`, "fa-download"); } else { mostrarErrorFinal(t('j_err'), data.message); } } catch (e) { mostrarErrorFinal(t('j_err'), "Error de red."); } finally { isTransferring = false; } }
        async function respaldarTodos() { const ip = document.getElementById('host-ip').value; if (!ip) return; mostrarCarga("ESCANEO", "Leyendo biblioteca...", "fa-brands fa-playstation"); isTransferring = true; try { const fd = new FormData(); fd.append('action', 'get_all_cusa'); fd.append('host_ip', ip); let res = await fetch('api/modding.php', { method: 'POST', body: fd }); let data = await res.json(); if (data.status === 'success' && data.juegos.length > 0) { await actualizarBackups(); let existentes = BACKUPS_LOCALES.map(b => b.nombre.split('_')[0]); let juegosFaltantes = data.juegos.filter(c => !existentes.includes(c)); if(juegosFaltantes.length === 0) { closeCustomModal(); ps5Notification(t('j_comp'), "Todas respaldadas.", "fa-check-double"); isTransferring = false; return; } let exitos = 0; document.getElementById('modal-progress-container').classList.remove('hidden'); document.getElementById('modal-controls').classList.add('hidden'); document.getElementById('modal-icon').innerHTML = `<div class="absolute inset-0 rounded-full border animate-ping" style="border-color: var(--theme-prim); opacity: 0.4;"></div><i class="fa-solid fa-layer-group text-3xl relative z-10" style="color: var(--theme-prim);"></i>`; for(let i=0; i < juegosFaltantes.length; i++) { let cusa = juegosFaltantes[i]; document.getElementById('modal-title').innerText = `SAQUEO (${i+1}/${juegosFaltantes.length})`; let pct = ((i) / juegosFaltantes.length) * 100; document.getElementById('modal-progress-bar').style.width = pct + '%'; document.getElementById('modal-percentage').innerText = pct.toFixed(0) + '%'; const fdBak = new FormData(); fdBak.append('action', 'backup_original'); fdBak.append('host_ip', ip); fdBak.append('cusa_id', cusa); try { let r = await fetch('api/modding.php', { method: 'POST', body: fdBak }); let d = await r.json(); if (d.status === 'success') exitos++; if (exitos % 2 === 0) actualizarBackups(); } catch(e) {} } document.getElementById('modal-progress-bar').style.width = '100%'; document.getElementById('modal-percentage').innerText = '100%'; AudioEngine.playSuccess(); closeCustomModal(); switchIconSource('backup'); ps5Notification(t('j_comp'), "Completado.", "fa-layer-group"); } else { mostrarErrorFinal(t('j_err'), "No se encontraron juegos."); } } catch(e) { mostrarErrorFinal(t('j_err'), "Falló la conexión."); } finally { isTransferring = false; } }
        async function enviarIcono(e) { e.preventDefault(); const ip = document.getElementById('host-ip').value, cusa = document.getElementById('icon-cusa').value.trim().toUpperCase(); if (!ip || !cusa) return; const formData = new FormData(); formData.append('action', 'upload_icon'); formData.append('host_ip', ip); formData.append('cusa_id', cusa); if (currentIconSource === 'gallery' || currentIconSource === 'backup') { if (!selectedIconValue) { await ps5Alert(t('j_err_file'), "Selecciona una imagen.", 'fa-image'); return; } formData.append('source_type', 'local_gallery'); formData.append('icon_path', selectedIconValue); } else { const fileInput = document.getElementById('icon-file'); if (!fileInput || !fileInput.files || fileInput.files.length === 0) { await ps5Alert(t('j_err_file'), "Busca una imagen.", 'fa-folder-open'); return; } formData.append('source_type', 'local'); formData.append('local_icon', fileInput.files[0]); } mostrarCarga("MODDING", "Inyectando portada...", "fa-wand-magic-sparkles fa-bounce"); document.querySelector('#modal-icon i').style.color = 'var(--theme-prim)'; isTransferring = true; try { let res = await fetch('api/modding.php', { method: 'POST', body: formData }); let data = await res.json(); if (data.status === 'success') { AudioEngine.playSuccess(); document.getElementById('modal-title').innerText = t('j_succ'); document.getElementById('modal-text').innerHTML = "Portada inyectada."; document.getElementById('modal-close-btn').classList.remove('hidden'); document.getElementById('modal-icon').innerHTML = `<div class="absolute inset-0 rounded-full border animate-ping" style="border-color: var(--theme-prim); opacity: 0.4;"></div><i class="fa-solid fa-palette text-4xl relative z-10" style="color: var(--theme-prim);"></i>`; ps5Notification("MODDING", "Arte aplicado.", "fa-palette"); } else { mostrarErrorFinal(t('j_err'), data.message); } } catch (error) { mostrarErrorFinal(t('j_err'), "Error de conexión."); } finally { isTransferring = false; } }

        // ==========================================
        // 15. PAYLOADS (RESTAURADOS)
        // ==========================================
        function cargarPayloads(lista, containerId) { 
            const container = document.getElementById(containerId); container.innerHTML = ''; 
            if (!lista || lista.length === 0) { container.innerHTML = `<div class="flex flex-col items-center justify-center min-h-[220px] bg-black/40 rounded-[1.5rem] border border-dashed border-white/10 p-6 text-center shadow-inner"><i class="fa-solid fa-folder text-6xl mb-4" style="color: var(--theme-prim); opacity: 0.3;"></i><p class="text-[11px] text-[var(--text-muted)] leading-relaxed max-w-[250px] mx-auto">${t('empty_pay_title')}</p></div>`; return; } 
            const topBar = document.createElement('div'); topBar.className = 'flex justify-between items-center mb-4 pr-1 bg-black/60 rounded-xl p-2 border border-white/5 shadow-inner'; topBar.innerHTML = `<span class="text-[10px] font-black tracking-widest pl-2 uppercase" style="color: var(--theme-prim); opacity: 0.6;">${lista.length} PAYLOADS</span><button class="bg-transparent px-4 py-2"></button>`; container.appendChild(topBar);
            const grid = document.createElement('div'); grid.className = 'flex flex-col gap-2 overflow-y-auto custom-scrollbar pr-1 gallery-grid-fix'; 
            lista.forEach(bin => { const item = document.createElement('div'); item.className = 'payload-item flex items-center justify-between p-3 rounded-xl cursor-pointer bg-black/40 hover:bg-white/5 border border-white/5 transition-colors group shadow-[0_0_10px_rgba(0,0,0,0.5)]'; item.onclick = function() { document.querySelectorAll(`#${containerId} .payload-item`).forEach(el => { el.classList.remove('selected'); el.style.borderColor = 'transparent'; }); this.classList.add('selected'); this.style.borderColor = 'var(--theme-prim)'; selectedPayloadValue = 'payloads/' + bin.nombre; }; item.innerHTML = `<div class="flex items-center gap-3"><i class="fa-solid fa-file-code text-lg" style="color: var(--theme-prim); opacity: 0.8;"></i><span class="text-xs font-mono text-[var(--text-main)] tracking-wide">${bin.nombre}</span></div><button type="button" onclick="eliminarPayloadServidor('${bin.nombre}', event)" class="w-8 h-8 rounded-full bg-red-900/20 border border-red-500/30 text-red-400 flex items-center justify-center hover:bg-red-600 hover:text-white transition-colors z-10 shrink-0 shadow-[0_0_10px_rgba(239,68,68,0.2)]"><i class="fa-solid fa-trash text-xs"></i></button>`; grid.appendChild(item); }); container.appendChild(grid); 
        }
        
        async function eliminarPayloadServidor(nombre, e) { e.stopPropagation(); const seguro = await ps5Confirm(t('opt_delete'), `¿Borrar <b>${nombre}</b>?`, "fa-trash", "bg-red-600 text-white border border-red-500/50 shadow-[0_0_15px_rgba(239,68,68,0.4)]"); if(!seguro) return; const fd = new FormData(); fd.append('action', 'delete_payload'); fd.append('file_name', nombre); try { let res = await fetch('api/payload.php', { method:'POST', body: fd }); let data = await res.json(); if(data.status === 'success') { actualizarPayloads(); ps5Notification(t('j_comp'), "Payload borrado.", "fa-trash"); } else ps5Alert(t('j_err'), data.message, "fa-triangle-exclamation"); } catch(err) {} }
        async function actualizarPayloads() { try { let res = await fetch('api/payload.php?action=get_payloads&_t=' + new Date().getTime()); let data = await res.json(); if (data.status === 'success') { PAYLOADS_LOCALES = data.data; } } catch(e) {} cargarPayloads(PAYLOADS_LOCALES, 'payload-gallery-container'); }
        
        function switchPayloadSource(type) { currentPayloadSource = type; ['btn-pay-gallery', 'btn-pay-local'].forEach(id => { let btn = document.getElementById(id); btn.className = "flex-1 py-3 text-[9px] font-black tracking-widest rounded-xl text-[var(--text-muted)] hover:text-[var(--text-main)] transition-colors"; btn.style.backgroundColor = ''; btn.style.color = ''; btn.style.boxShadow = ''; }); ['box-pay-gallery', 'box-pay-local'].forEach(id => document.getElementById(id).classList.add('hidden')); let activeBtn = document.getElementById(`btn-pay-${type}`); activeBtn.className = "flex-1 py-3 text-[9px] font-black tracking-widest rounded-xl"; activeBtn.style.backgroundColor = 'var(--theme-prim)'; activeBtn.style.color = '#000'; activeBtn.style.boxShadow = '0 0 10px color-mix(in srgb, var(--theme-prim) 40%, transparent)'; document.getElementById(`box-pay-${type}`).classList.remove('hidden'); if(type === 'gallery') actualizarPayloads(); }
        
        function updatePayloadName(input) { const display = document.getElementById('payload-name-display'), iconContainer = document.getElementById('payload-icon-container'); if (input.files.length > 0) { display.innerText = input.files[0].name; display.classList.replace('text-[var(--text-muted)]', 'text-[var(--text-main)]'); iconContainer.innerHTML = '<i class="fa-solid fa-check text-2xl text-black"></i>'; iconContainer.style.backgroundColor = 'var(--theme-prim)'; iconContainer.style.color = '#000'; } else { display.innerText = t('touch_bin'); display.classList.replace('text-[var(--text-main)]', 'text-[var(--text-muted)]'); iconContainer.innerHTML = '<i class="fa-solid fa-file-code text-2xl"></i>'; iconContainer.style.backgroundColor = 'color-mix(in srgb, var(--theme-prim) 10%, transparent)'; iconContainer.style.color = 'var(--theme-prim)'; } }
        
        async function enviarPayload(e) { e.preventDefault(); const ip = document.getElementById('host-ip').value, port = document.getElementById('payload-port').value; if (!ip) { await ps5Alert(t('j_err_ip'), t('j_err_ip_m'), 'fa-network-wired'); return; } const formData = new FormData(); formData.append('action', 'send_payload'); formData.append('host_ip', ip); formData.append('port', port); if (currentPayloadSource === 'gallery') { if (!selectedPayloadValue) { await ps5Alert(t('j_err_file'), t('j_err_file_m'), 'fa-microchip'); return; } formData.append('source_type', 'gallery'); formData.append('payload_path', selectedPayloadValue); } else { const fileInput = document.getElementById('payload-file'); if (!fileInput.files.length) { await ps5Alert(t('j_err_file'), t('j_err_file_m'), 'fa-file-code'); return; } formData.append('source_type', 'local'); formData.append('payload_file', fileInput.files[0]); } mostrarCarga(t('j_inj'), t('j_inj_m'), "fa-microchip fa-bounce"); document.querySelector('#modal-icon i').style.color = 'var(--theme-prim)'; isTransferring = true; try { let res = await fetch('api/payload.php', { method: 'POST', body: formData }); let data = await res.json(); if (data.status === 'success') { AudioEngine.playSuccess(); document.getElementById('modal-title').innerText = t('j_succ'); document.getElementById('modal-text').innerHTML = data.message; document.getElementById('modal-close-btn').classList.remove('hidden'); document.getElementById('modal-icon').innerHTML = `<div class="absolute inset-0 rounded-full border animate-ping" style="border-color: color-mix(in srgb, var(--theme-prim) 40%, transparent);"></div><i class="fa-solid fa-check text-4xl relative z-10" style="color: var(--theme-prim);"></i>`; if(currentPayloadSource === 'local') { document.getElementById('payload-file').value = ''; updatePayloadName(document.getElementById('payload-file')); } ps5Notification("INYECCIÓN", "Payload enviado.", "fa-bolt"); } else { mostrarErrorFinal(t('j_err'), data.message); } } catch (error) { mostrarErrorFinal(t('j_err'), t('j_err_net')); } finally { isTransferring = false; } }

        // ==========================================
        // 16. ESCANER DE RED Y PERFIL (EL RADAR HACKER RESTAURADO)
        // ==========================================
        async function cambiarNombreUsuario() { if(!wasConnected) { await ps5Alert("DESCONECTADO", "Debes estar conectado.", "fa-plug-circle-xmark"); return; } let nuevoNombre = await ps5Prompt("NOMBRE DE USUARIO", "Introduce tu nombre:", customUserName); if(nuevoNombre && nuevoNombre.trim() !== "") { customUserName = nuevoNombre.trim().toUpperCase(); localStorage.setItem('ps4_custom_username', customUserName); document.getElementById('profile-name').innerText = customUserName; ps5Notification(t('j_comp'), "Actualizado.", "fa-user"); } }
        async function fetchPS4Profile(ip) { if (cachedAvatar) { aplicarAvatar(cachedAvatar, customUserName); return; } isTransferring = true; const fd = new FormData(); fd.append('action', 'get_ps4_profile'); fd.append('host_ip', ip); try { let res = await fetch('api/modding.php', { method: 'POST', body: fd }); let data = await res.json(); if (data.status === 'success' && data.avatar) { cachedAvatar = data.avatar; aplicarAvatar(cachedAvatar, customUserName); } else { aplicarAvatar(null, customUserName); } } catch(e) { aplicarAvatar(null, customUserName); } finally { isTransferring = false; } }
        function aplicarAvatar(base64Img, nombre) { const av = document.getElementById('profile-avatar'), ini = document.getElementById('profile-initial'), nm = document.getElementById('profile-name'); if(!av) return; if (base64Img) { av.style.backgroundImage = `url('${base64Img}')`; av.style.backgroundColor = 'transparent'; av.classList.remove('rounded-full'); av.classList.add('rounded-xl'); if(ini) ini.classList.add('hidden'); } else { av.style.backgroundImage = ''; av.style.backgroundColor = 'rgba(0,0,0,0.5)'; av.classList.remove('rounded-xl'); av.classList.add('rounded-full'); if(ini) { ini.innerText = nombre.charAt(0); ini.classList.remove('hidden'); } } if(nm) { nm.innerText = nombre; nm.style.color = 'var(--theme-prim)'; } }
        function resetAvatarLocal() { const av = document.getElementById('profile-avatar'), ini = document.getElementById('profile-initial'), nm = document.getElementById('profile-name'); if(!av) return; av.style.backgroundImage = ''; av.style.backgroundColor = 'rgba(0,0,0,0.5)'; av.classList.remove('rounded-xl'); av.classList.add('rounded-full'); if(ini) { ini.innerText = 'S'; ini.classList.remove('hidden'); } if(nm) { nm.innerText = 'BY SEBAS'; nm.style.color = 'var(--theme-prim)'; } }

        const SUBRED_PHP = "<?php echo isset($subred_actual) ? $subred_actual : ''; ?>";
        function setPS4State(isConnected) { if (wasConnected === true && isConnected === false) { AudioEngine.playDisconnect(); resetAvatarLocal(); ps5Notification(t('j_err'), "Se perdió la conexión.", "fa-plug-circle-xmark"); } wasConnected = isConnected; const badgeOn = document.getElementById('badge-detectada'), badgeOff = document.getElementById('badge-desconectada'); if(isConnected) { badgeOn.classList.remove('hidden'); badgeOn.classList.add('flex'); badgeOff.classList.add('hidden'); badgeOff.classList.remove('flex'); const ipEl = document.getElementById('host-ip'); if(ipEl && ipEl.value) fetchPS4Profile(ipEl.value); } else { badgeOff.classList.remove('hidden'); badgeOff.classList.add('flex'); badgeOn.classList.add('hidden'); badgeOn.classList.remove('flex'); resetAvatarLocal(); } }
        function startConnectionMonitor(ip) { if(connectionMonitorInterval) clearInterval(connectionMonitorInterval); failedPings = 0; setPS4State(true); connectionMonitorInterval = setInterval(async () => { if (isTransferring) return; try { const checkController = new AbortController(); const timeoutId = setTimeout(() => checkController.abort(), 6000); let res = await fetch(`api/scanner.php?ip=${ip}`, { signal: checkController.signal, cache: 'no-store' }); clearTimeout(timeoutId); let data = await res.json(); if (data.status === 'success') { failedPings = 0; setPS4State(true); } else { failedPings++; if (failedPings >= 2) setPS4State(false); } } catch(e) { failedPings++; if (failedPings >= 2) setPS4State(false); } }, 12000); }
        window.addEventListener('online', () => { const ipEl = document.getElementById('host-ip'); if(ipEl && ipEl.value) startConnectionMonitor(ipEl.value); });
        function clearIP() { if(isScanning) toggleRealScan(); document.getElementById('host-ip').value = ''; localStorage.removeItem('ps4_ip_guardada'); setPS4State(false); if(connectionMonitorInterval) clearInterval(connectionMonitorInterval); }
        function detenerUI() { isScanning = false; document.getElementById('host-ip').disabled = false; document.getElementById('global-status').classList.add('hidden'); clearInterval(radarAnimInterval); document.getElementById('btn-scan').innerHTML = '<i class="fa-solid fa-satellite-dish text-xs"></i>'; document.getElementById('btn-scan').className = 'w-[42px] h-[42px] rounded-[1.2rem] flex items-center justify-center active:scale-95 shrink-0 transition-all'; document.getElementById('btn-scan').style.backgroundColor = 'var(--theme-prim)'; document.getElementById('btn-scan').style.color = '#000'; }
        
        async function toggleRealScan() { 
            if (isScanning) { if (abortController) abortController.abort(); detenerUI(); return; } 
            isScanning = true; document.getElementById('host-ip').value = ''; document.getElementById('host-ip').disabled = true; document.getElementById('global-status').classList.remove('hidden'); document.getElementById('btn-scan').className = 'w-[42px] h-[42px] rounded-[1.2rem] bg-red-600 flex items-center justify-center text-[var(--text-main)] shrink-0 shadow-[0_0_15px_rgba(239,68,68,0.5)] animate-pulse transition-all'; document.getElementById('btn-scan').style.backgroundColor = ''; document.getElementById('btn-scan').style.color = ''; document.getElementById('btn-scan').innerHTML = '<i class="fa-solid fa-stop text-xs"></i>';
            radarAnimInterval = setInterval(() => { document.getElementById('scan-text').innerText = `BUSCANDO IP: 192.168.${Math.random() < 0.5 ? '0' : '100'}.${Math.floor(Math.random() * 254) + 1}`; }, 30); 
            abortController = new AbortController(); const signal = abortController.signal; 
            try { 
                let subnets = ['192.168.0.', '192.168.100.', '192.168.1.']; 
                if (SUBRED_PHP && !subnets.includes(SUBRED_PHP) && SUBRED_PHP.startsWith('192')) subnets.unshift(SUBRED_PHP); 
                
                let ipsToScan = []; 
                for(let s of subnets) { for(let i = 10; i <= 40; i++) ipsToScan.push(s + i); }
                for(let s of subnets) { for(let i = 2; i < 10; i++) ipsToScan.push(s + i); }
                for(let s of subnets) { for(let i = 41; i < 255; i++) ipsToScan.push(s + i); }
                
                const BATCH_SIZE = 4; 
                let foundIp = null; 
                for (let i = 0; i < ipsToScan.length; i += BATCH_SIZE) { 
                    if (signal.aborted) break; 
                    const batch = ipsToScan.slice(i, i + BATCH_SIZE); 
                    const promises = batch.map(ip => {
                        const pingController = new AbortController();
                        const id = setTimeout(() => pingController.abort(), 3000); 
                        return fetch(`api/scanner.php?ip=${ip}`, { signal: pingController.signal })
                            .then(res => { clearTimeout(id); return res.json(); })
                            .then(data => { if (data.status === 'success') throw data; return null; })
                            .catch(err => { clearTimeout(id); if (err.status === 'success') return err.ip; return null; });
                    }); 
                    try { const results = await Promise.all(promises); const winner = results.find(res => res !== null); if (winner) { foundIp = winner; break; } } catch(e) {} 
                } 
                if (foundIp) { detenerUI(); document.getElementById('host-ip').value = foundIp; localStorage.setItem('ps4_ip_guardada', foundIp); setPS4State(true); startConnectionMonitor(foundIp); AudioEngine.playSuccess(); ps5Notification(t('j_succ'), "Enlace establecido.", "fa-gamepad"); } else if (!signal.aborted) { detenerUI(); await ps5Alert(t('j_scan_fail'), t('j_scan_fail_m'), 'fa-satellite-dish'); } 
            } catch (e) { detenerUI(); } 
        }

        function connectManualIP() { const ip = document.getElementById('host-ip').value.trim(); if(ip) { localStorage.setItem('ps4_ip_guardada', ip); setPS4State(true); startConnectionMonitor(ip); ps5Notification(t('j_succ'), "IP Manual enlazada.", "fa-gamepad"); } }

        // ==========================================
        // 18. CARGA DE ARCHIVOS FTP (CHUNKS)
        // ==========================================
        async function enviarArchivoChunks(e) {
            e.preventDefault();
            const ip = document.getElementById('host-ip').value;
            const pathDest = document.getElementById('selected-path-input').value;
            const files = document.getElementById('file-upload').files;
            
            if (!ip) { await ps5Alert(t('j_err_ip'), t('j_err_ip_m'), 'fa-network-wired'); return; }
            if (files.length === 0) { await ps5Alert(t('j_err_file'), t('j_err_file_m'), 'fa-file'); return; }

            document.getElementById('custom-modal').classList.remove('hidden', 'opacity-0');
            document.getElementById('modal-card').classList.remove('scale-95');
            document.getElementById('modal-progress-container').classList.remove('hidden');
            document.getElementById('modal-controls').classList.remove('hidden');
            document.getElementById('modal-action-btn').classList.remove('hidden');
            document.getElementById('modal-action-btn').innerText = t('modal_pause');
            document.getElementById('modal-cancel-btn').classList.remove('hidden');
            document.getElementById('modal-close-btn').classList.add('hidden');
            document.getElementById('modal-icon').innerHTML = '<div class="absolute inset-0 rounded-full border border-[var(--theme-prim)]/50 animate-ping"></div><i class="fa-solid fa-cloud-arrow-up text-4xl relative z-10" style="color: var(--theme-prim);"></i>';

            isTransferring = true;
            isSyncCanceled = false;
            isPaused = false;
            
            for (let i = 0; i < files.length; i++) {
                if (isSyncCanceled) break;
                let file = files[i];
                currentFileName = file.name;
                document.getElementById('modal-title').innerText = `ENVIANDO (${i+1}/${files.length})`;
                document.getElementById('modal-text').innerHTML = `<b class="text-[10px] break-all uppercase" style="color: var(--theme-prim);">${file.name}</b><br><span class="text-[9px] uppercase tracking-widest text-[var(--text-muted)]">${pathDest}</span>`;

                let totalChunks = Math.ceil(file.size / CHUNK_SIZE);
                let startChunk = 0;

                try {
                    const fdCheck = new FormData();
                    fdCheck.append('action', 'check_file');
                    fdCheck.append('host_ip', ip);
                    fdCheck.append('path', pathDest + file.name);
                    
                    let resCheck = await fetch('api/explorer.php', { method: 'POST', body: fdCheck });
                    let dataCheck = await resCheck.json();
                    
                    if (dataCheck.status === 'success') {
                        if (dataCheck.exists) {
                            if (dataCheck.size === file.size) {
                                const override = await ps5Confirm(t('j_exist'), `<b class="text-white">${file.name}</b> ${t('j_exist_m')}`, 'fa-file-circle-exclamation', 'bg-yellow-500 text-black border-yellow-400');
                                if (!override) continue; 
                                startChunk = 0;
                            } else if (dataCheck.size < file.size) {
                                const resume = await ps5Confirm(t('j_resume'), `Se encontró una transferencia incompleta de <b class="text-white">${file.name}</b>.<br><br>${t('j_resume_m')}`, 'fa-forward', 'bg-green-500 text-black border-green-400');
                                if (resume) {
                                    startChunk = Math.floor(dataCheck.size / CHUNK_SIZE);
                                    let remainingSize = dataCheck.size % CHUNK_SIZE;
                                    if(remainingSize > 0) startChunk--; 
                                } else {
                                    startChunk = 0;
                                }
                            }
                        }
                    }
                } catch(e) {}

                for (let chunkIndex = startChunk; chunkIndex < totalChunks; chunkIndex++) {
                    if (isSyncCanceled) break;
                    
                    while (isPaused) {
                        await new Promise(r => setTimeout(r, 500));
                        if (isSyncCanceled) break;
                    }
                    if (isSyncCanceled) break;

                    let start = chunkIndex * CHUNK_SIZE;
                    let end = Math.min(start + CHUNK_SIZE, file.size);
                    let chunk = file.slice(start, end);

                    let fd = new FormData();
                    fd.append('action', 'upload_chunk');
                    fd.append('host_ip', ip);
                    fd.append('path', pathDest);
                    fd.append('file_name', file.name);
                    fd.append('chunk', chunk);
                    fd.append('chunk_index', chunkIndex);
                    fd.append('total_chunks', totalChunks);

                    uploadAbortController = new AbortController();
                    
                    try {
                        let startTime = Date.now();
                        let res = await fetch('api/explorer.php', {
                            method: 'POST',
                            body: fd,
                            signal: uploadAbortController.signal
                        });
                        let data = await res.json();
                        
                        if (data.status !== 'success') {
                            throw new Error(data.message || 'Error en servidor PS4');
                        }

                        let endTime = Date.now();
                        let duration = (endTime - startTime) / 1000; 
                        let speed = (chunk.size / 1024 / 1024) / duration; 
                        let pct = ((chunkIndex + 1) / totalChunks) * 100;
                        let bytesSent = end / 1024 / 1024 / 1024;
                        let totalGb = file.size / 1024 / 1024 / 1024;
                        
                        let remainingBytes = file.size - end;
                        let speedBps = chunk.size / duration;
                        let etaSeconds = remainingBytes / speedBps;
                        let etaMins = Math.floor(etaSeconds / 60);
                        let etaSecs = Math.floor(etaSeconds % 60);

                        document.getElementById('modal-progress-bar').style.width = pct + '%';
                        document.getElementById('modal-percentage').innerText = pct.toFixed(0) + '%';
                        document.getElementById('modal-bytes').innerText = `${bytesSent.toFixed(2)} / ${totalGb.toFixed(2)} GB`;
                        document.getElementById('modal-speed').innerHTML = `<i class="fa-solid fa-bolt" style="color: var(--theme-prim);"></i> ${speed.toFixed(1)} MB/s`;
                        document.getElementById('modal-eta').innerHTML = `<i class="fa-solid fa-clock" style="color: var(--theme-prim);"></i> ETA: ${etaMins}:${etaSecs.toString().padStart(2, '0')}`;
                        
                    } catch (e) {
                        if (e.name === 'AbortError') {
                            break;
                        } else {
                            mostrarErrorFinal("ERROR", `Falló el envío en el chunk ${chunkIndex}.<br><br><span class="text-[9px] text-white/50">${e.message}</span>`);
                            isTransferring = false;
                            return;
                        }
                    }
                }
            }

            isTransferring = false;
            if (isSyncCanceled) {
                mostrarErrorFinal(t('j_cancel'), t('j_cancel_m'));
                ps5Notification("FTP", t('j_cancel_m'), "fa-xmark");
            } else {
                AudioEngine.playSuccess();
                document.getElementById('modal-title').innerText = t('j_comp');
                document.getElementById('modal-text').innerText = t('j_comp_m');
                document.getElementById('modal-controls').classList.add('hidden');
                document.getElementById('modal-close-btn').classList.remove('hidden');
                document.getElementById('modal-icon').innerHTML = `<div class="absolute inset-0 rounded-full border border-[var(--theme-prim)]/50 animate-ping"></div><i class="fa-solid fa-check text-4xl relative z-10" style="color: var(--theme-prim);"></i>`;
                ps5Notification("FTP", "Transferencia completada.", "fa-cloud-arrow-up");
                document.getElementById('file-upload').value = '';
                updateFileName(document.getElementById('file-upload'));
            }
        }

        function togglePauseResume() {
            isPaused = !isPaused;
            const btn = document.getElementById('modal-action-btn');
            if (isPaused) {
                btn.innerText = t('j_resume');
                document.getElementById('modal-title').innerText = "PAUSADO";
                btn.classList.add('bg-yellow-500/20', 'text-yellow-500', 'border-yellow-500/50');
                btn.classList.remove('bg-black/50');
            } else {
                btn.innerText = t('modal_pause');
                document.getElementById('modal-title').innerText = "ENVIANDO...";
                btn.classList.remove('bg-yellow-500/20', 'text-yellow-500', 'border-yellow-500/50');
                btn.classList.add('bg-black/50');
            }
        }

        // ==========================================
        // 21. INICIALIZADOR FINAL
        // ==========================================
        document.addEventListener('DOMContentLoaded', () => {
            const notifToggle = document.getElementById('toggle_notifications');
            if (notifToggle) { let savedNotif = localStorage.getItem('ps4_ui_notif'); if (savedNotif !== null) notifToggle.checked = (savedNotif === 'true'); notifToggle.addEventListener('change', (e) => { localStorage.setItem('ps4_ui_notif', e.target.checked); if(e.target.checked) ps5Notification("SISTEMA", "Notificaciones encendidas.", "fa-message"); }); }
            document.querySelectorAll('.dock-item').forEach(btn => { btn.addEventListener('click', function(e) { let ripple = document.createElement('div'); ripple.className = 'dock-ripple'; this.appendChild(ripple); setTimeout(() => ripple.remove(), 500); }); });
            
            loadThemeAndWallpaper();

            if(localStorage.getItem('ps4_custom_username')) customUserName = localStorage.getItem('ps4_custom_username');
            const savedIp = localStorage.getItem('ps4_ip_guardada');
            if(savedIp) { document.getElementById('host-ip').value = savedIp; setPS4State(true); startConnectionMonitor(savedIp); } else { resetAvatarLocal(); }
            
            const savedFolders = JSON.parse(localStorage.getItem('ps4_custom_folders')) || [];
            savedFolders.forEach(folder => {
                if (typeof crearBotonCarpeta === 'function') crearBotonCarpeta(folder, false);
            });
            
            if (typeof renderShortcuts === 'function') renderShortcuts(); 
            if (typeof actualizarGaleria === 'function') actualizarGaleria(); 
            if (typeof actualizarPayloads === 'function') actualizarPayloads(); 
            if (typeof renderCategorias === 'function') renderCategorias(); 
            if (typeof cargarBibliotecaLocal === 'function') cargarBibliotecaLocal(); 
            
            AudioEngine.loadSettings();

            let blurSlider = document.getElementById('blur-slider');
            if (blurSlider) {
                blurSlider.addEventListener('input', (e) => updateWallpaperBlur(e.target.value));
                blurSlider.addEventListener('change', (e) => updateWallpaperBlur(e.target.value));
            }
            let partSlider = document.getElementById('particles-slider');
            if (partSlider) { 
                partSlider.addEventListener('input', (e) => updateParticlesCount(e.target.value)); 
                partSlider.addEventListener('change', (e) => updateParticlesCount(e.target.value)); 
            }
            
            const updateDynamicColors = () => {
                const primColor = getComputedStyle(document.documentElement).getPropertyValue('--theme-prim');
                const btnScan = document.getElementById('btn-scan');
                if (btnScan && !isScanning) { btnScan.style.backgroundColor = primColor; btnScan.style.color = '#000'; btnScan.style.boxShadow = `0 0 15px color-mix(in srgb, ${primColor} 50%, transparent)`; }
            };
            setTimeout(updateDynamicColors, 200);
            document.querySelectorAll('.theme-btn').forEach(btn => btn.addEventListener('click', () => setTimeout(updateDynamicColors, 50)));
        });
    </script>
</body>
</html>
