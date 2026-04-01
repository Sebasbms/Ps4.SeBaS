<?php
/**
 * ARCHIVO: api/library.php
 * Motor de Biblioteca (Actualizado con ftp_rawlist para evitar el error de AL DÍA)
 */
header('Content-Type: application/json');
set_time_limit(120); 

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$host_ip = $_POST['host_ip'] ?? $_GET['host_ip'] ?? '';
$cusa_id = $_POST['cusa_id'] ?? $_GET['cusa_id'] ?? '';

$cache_dir = '../cache_biblioteca';
if (!file_exists($cache_dir)) { @mkdir($cache_dir, 0777, true); }
if (!file_exists($cache_dir . '/.nomedia')) { @file_put_contents($cache_dir . '/.nomedia', ''); }

if ($action === 'delete_game') {
    @unlink($cache_dir . '/' . $cusa_id . '.png');
    @unlink($cache_dir . '/' . $cusa_id . '.sfo');
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action === 'get_cached_games') {
    $games = [];
    $iconos = glob($cache_dir . '/*.png');
    
    if (is_array($iconos)) {
        foreach ($iconos as $icon_file) {
            $cusa = pathinfo($icon_file, PATHINFO_FILENAME);
            $sfo_file = $cache_dir . '/' . $cusa . '.sfo';
            $title = $cusa; 
            $version = "1.00";
            
            if (file_exists($sfo_file) && filesize($sfo_file) > 0) {
                $sfo_data = parse_sfo($sfo_file);
                if ($sfo_data) {
                    if (!empty($sfo_data['TITLE'])) {
                        $clean_title = str_replace(array('"', "'", "\\", "\0", "\n", "\r", "<", ">"), ' ', $sfo_data['TITLE']);
                        $clean_title = preg_replace('/\s+/', ' ', $clean_title);
                        if (trim($clean_title) !== '') { $title = trim($clean_title); }
                    }
                    if (!empty($sfo_data['APP_VER'])) {
                        $version = $sfo_data['APP_VER'];
                    } elseif (!empty($sfo_data['VERSION'])) {
                        $version = $sfo_data['VERSION'];
                    }
                }
            }
            
            $type = in_array($cusa, ['TOOL00001', 'NPXS29005', 'NPXS30017', 'APOL00004']) ? 'apps' : 'juegos';
            $games[] = [ 
                'id' => $cusa, 
                'title' => $title, 
                'type' => $type,
                'version' => $version,
                'icon' => 'cache_biblioteca/' . $cusa . '.png' 
            ];
        }
    }
    usort($games, function($a, $b) { return strcasecmp($a['title'], $b['title']); });
    echo json_encode(['status' => 'success', 'data' => $games]);
    exit;
}

if (!$host_ip) { echo json_encode(['status' => 'error', 'message' => 'Falta la IP de la PS4']); exit; }

if ($action === 'scan_list') {
    $conn = @ftp_connect($host_ip, 2121, 10);
    if (!$conn) { echo json_encode(['status' => 'error', 'message' => 'No se pudo conectar al FTP.']); exit; }
    @ftp_login($conn, "anonymous", ""); ftp_pasv($conn, true);

    // MAGIA DEL BACKUP: Buscar en las 6 rutas para encontrar los 107 juegos
    $rutas = [
        '/user/appmeta',
        '/user/appmeta/external',
        '/user/appmeta/push_resource',
        '/system_data/priv/appmeta',
        '/system_data/priv/appmeta/external',
        '/system_data/priv/appmeta/push_resource'
    ];
    
    $games_list = []; $patron_id = '/^[A-Z]{4}\d{5}$/i';

    foreach ($rutas as $ruta) {
        // USAMOS RAWLIST EN LUGAR DE FTP_SIZE PARA NO FALLAR
        $raw_list = @ftp_rawlist($conn, $ruta);
        if (is_array($raw_list)) {
            foreach ($raw_list as $line) {
                $parts = preg_split('/\s+/', $line, 9);
                if (count($parts) >= 9) {
                    $name = $parts[8];
                    if ($name === '.' || $name === '..') continue;
                    
                    // Verificamos si es carpeta
                    $is_dir = (substr($parts[0], 0, 1) === 'd');
                    if ($is_dir) {
                        $cusa = strtoupper($name);
                        if (preg_match($patron_id, $cusa)) {
                            $games_list[$cusa] = true;
                        }
                    }
                }
            }
        } else {
            // Plan B estricto por si acaso
            $folders = @ftp_nlist($conn, $ruta);
            if (is_array($folders)) {
                foreach ($folders as $folder) {
                    $cusa = strtoupper(basename($folder));
                    if (preg_match($patron_id, $cusa)) {
                        $games_list[$cusa] = true;
                    }
                }
            }
        }
    }
    @ftp_close($conn);

    $cached_files = glob($cache_dir . '/*');
    if (is_array($cached_files)) {
        foreach ($cached_files as $file) {
            if (basename($file) === '.nomedia') continue;
            $cusa = pathinfo($file, PATHINFO_FILENAME);
            if (!isset($games_list[$cusa])) { @unlink($file); }
        }
    }

    $missing = [];
    foreach ($games_list as $cusa => $val) {
        $icon = $cache_dir . '/' . $cusa . '.png';
        $sfo = $cache_dir . '/' . $cusa . '.sfo';
        if (!file_exists($icon) || filesize($icon) == 0 || !file_exists($sfo) || filesize($sfo) == 0) {
            $missing[] = ['id' => $cusa]; 
        }
    }

    echo json_encode(['status' => 'success', 'missing' => $missing]);
    exit;
}

if ($action === 'get_game_data' && $cusa_id) {
    $local_icon = $cache_dir . '/' . $cusa_id . '.png';
    $local_sfo = $cache_dir . '/' . $cusa_id . '.sfo';
    
    if (!file_exists($local_icon) || !file_exists($local_sfo) || filesize($local_sfo) == 0) {
        $conn = @ftp_connect($host_ip, 2121, 5);
        if ($conn) {
            @ftp_login($conn, "anonymous", ""); ftp_pasv($conn, true);
            
            // LA MAGIA DE TU BACKUP: Buscar en las 4 rutas posibles para bajar el icono real
            $rutas_posibles = [
                "/user/appmeta/$cusa_id",
                "/system_data/priv/appmeta/$cusa_id",
                "/user/appmeta/external/$cusa_id",
                "/system_data/priv/appmeta/external/$cusa_id"
            ];

            foreach ($rutas_posibles as $ruta) {
                // Como los archivos sí tienen tamaño real (no -1), esto es rapidísimo
                $size_sfo = @ftp_size($conn, "$ruta/param.sfo");
                $size_png = @ftp_size($conn, "$ruta/icon0.png");
                
                if ($size_sfo > 0 && $size_png > 0) {
                    @ftp_get($conn, $local_sfo, "$ruta/param.sfo", FTP_BINARY);
                    @ftp_get($conn, $local_icon, "$ruta/icon0.png", FTP_BINARY);
                    break; 
                } elseif ($size_sfo > 0) {
                    @ftp_get($conn, $local_sfo, "$ruta/param.sfo", FTP_BINARY);
                } elseif ($size_png > 0) {
                    @ftp_get($conn, $local_icon, "$ruta/icon0.png", FTP_BINARY);
                }
            }
            @ftp_close($conn);
        }
    }
    echo json_encode(['status' => 'success']);
    exit;
}

function parse_sfo($filepath) {
    $sfo = @file_get_contents($filepath);
    if (!$sfo || substr($sfo, 0, 4) !== "\0PSF") return false;
    $key_table_offset = unpack("V", substr($sfo, 0x08, 4))[1];
    $data_table_offset = unpack("V", substr($sfo, 0x0C, 4))[1];
    $entries = unpack("V", substr($sfo, 0x10, 4))[1];
    $keys = [];
    for ($i = 0; $i < $entries; $i++) {
        $entry_offset = 0x14 + ($i * 16);
        $key_offset = unpack("v", substr($sfo, $entry_offset, 2))[1];
        $data_format = unpack("v", substr($sfo, $entry_offset + 2, 2))[1];
        $data_len = unpack("V", substr($sfo, $entry_offset + 4, 4))[1];
        $data_offset = unpack("V", substr($sfo, $entry_offset + 12, 4))[1];
        $k_offset_abs = $key_table_offset + $key_offset;
        $k_end = strpos($sfo, "\0", $k_offset_abs);
        $key = substr($sfo, $k_offset_abs, $k_end - $k_offset_abs);
        $d_offset_abs = $data_table_offset + $data_offset;
        $data = substr($sfo, $d_offset_abs, $data_len);
        if ($data_format == 0x0204) $data = rtrim($data, "\0");
        $keys[$key] = $data;
    }
    return $keys;
}
?>
