<?php
/**
 * ARCHIVO: api/dlc_manager.php
 * Gestor Final: Updates siempre arriba y Borrado corregido
 * (Réplica exacta de la lógica SeBaS, impulsada por cURL)
 */
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
set_time_limit(180);

$action = $_POST['action'] ?? '';
$host_ip = $_POST['host_ip'] ?? '';
$cusa = $_POST['cusa_id'] ?? '';
$port = 2121;

if (!$host_ip) { echo json_encode(['status' => 'error', 'message' => 'Falta IP.']); exit; }
if ($action === 'scan' && !$cusa) { echo json_encode(['status' => 'error', 'message' => 'Falta CUSA.']); exit; }

// ==========================================
// FUNCIONES NÚCLEO (Reemplazos cURL)
// ==========================================

function format_size($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

function curl_ftp_list($ip, $port, $dir, $detailed = false) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$ip:$port" . rtrim($dir, '/') . '/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $detailed ? "LIST" : "NLST");
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    $res = curl_exec($ch);
    curl_close($ch);
    
    $items = [];
    if ($res) {
        $lines = explode("\n", trim($res));
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if ($detailed) {
                $items[] = $line; // Devolvemos la línea RAW para que get_dir_size la procese igual
            } else {
                $basename = basename($line);
                if ($basename !== '.' && $basename !== '..') {
                    $items[] = $basename;
                }
            }
        }
    }
    return $items;
}

// Tu función original, adaptada para procesar las líneas RAW que devuelve cURL
function get_dir_size($ip, $port, $dir) {
    $size = 0;
    $items = curl_ftp_list($ip, $port, $dir, true);
    foreach ($items as $item) {
        $info = preg_split("/[\s]+/", $item, 9);
        if (count($info) >= 9) {
            $is_dir = ($info[0][0] === 'd');
            $filesize = (int)$info[4];
            $name = $info[8];
            if ($name === '.' || $name === '..') continue;
            $path = rtrim($dir, '/') . '/' . $name;
            if ($is_dir) { 
                $size += get_dir_size($ip, $port, $path); 
            } else { 
                $size += $filesize; 
            }
        }
    }
    return $size;
}

function delete_dir($ip, $port, $dir) {
    $files = curl_ftp_list($ip, $port, $dir, true);
    foreach ($files as $item) {
        $info = preg_split("/[\s]+/", $item, 9);
        if (count($info) >= 9) {
            $is_dir = ($info[0][0] === 'd');
            $name = $info[8];
            if ($name === '.' || $name === '..') continue;
            
            $path = rtrim($dir, '/') . '/' . $name;
            if ($is_dir) {
                delete_dir($ip, $port, $path);
            } else {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "ftp://$ip:$port/");
                curl_setopt($ch, CURLOPT_QUOTE, ["DELE $path"]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch); curl_close($ch);
            }
        }
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$ip:$port/");
    curl_setopt($ch, CURLOPT_QUOTE, ["RMD $dir"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch); curl_close($ch);
}

function find_sfo($ip, $port, $dir, $depth = 0) {
    if ($depth > 2) return false;
    $items = curl_ftp_list($ip, $port, $dir, false);
    foreach ($items as $base) {
        $path = rtrim($dir, '/') . '/' . $base;
        if (strtolower($base) === 'param.sfo') return $path;
        
        if (strpos($base, '.') === false || strtolower($base) === 'sce_sys') {
            $found = find_sfo($ip, $port, $path, $depth + 1);
            if ($found) return $found;
        }
    }
    return false;
}

function get_title_from_sfo($ip, $port, $sfo_path) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$ip:$port$sfo_path");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $sfo = curl_exec($ch);
    curl_close($ch);

    if ($sfo && substr($sfo, 0, 4) === "\0PSF") {
        $key_table_offset = unpack("V", substr($sfo, 0x08, 4))[1];
        $data_table_offset = unpack("V", substr($sfo, 0x0C, 4))[1];
        $entries = unpack("V", substr($sfo, 0x10, 4))[1];
        for ($i = 0; $i < $entries; $i++) {
            $entry_offset = 0x14 + ($i * 16);
            $key_offset = unpack("v", substr($sfo, $entry_offset, 2))[1];
            $val_len = unpack("V", substr($sfo, $entry_offset + 4, 4))[1];
            $val_offset = unpack("V", substr($sfo, $entry_offset + 12, 4))[1];
            $key = "";
            $k = $key_table_offset + $key_offset;
            while (isset($sfo[$k]) && $sfo[$k] !== "\0") { $key .= $sfo[$k]; $k++; }
            if ($key === "TITLE") return rtrim(substr($sfo, $data_table_offset + $val_offset, $val_len), "\0");
        }
    }
    return false;
}

// ==========================================
// ACCIONES
// ==========================================

if ($action === 'scan') {
    $updates = [];
    $dlcs_list = [];
    $bases = ['/user', '/mnt/ext0/user', '/mnt/ext1/user'];
    
    foreach ($bases as $base) {
        $patch_path = "$base/patch/$cusa";
        $patch_size = get_dir_size($host_ip, $port, $patch_path);
        if ($patch_size > 0) {
            $updates[] = [
                'type' => 'update', 'name' => 'Actualización (Parche Base)',
                'size_formatted' => format_size($patch_size), 'path' => $patch_path
            ];
        }

        $addcont_path = "$base/addcont/$cusa";
        $dlcs = curl_ftp_list($host_ip, $port, $addcont_path, false);
        foreach ($dlcs as $dlc_folder) {
            $dlc_full_path = "$addcont_path/$dlc_folder";
            $dlc_size = get_dir_size($host_ip, $port, $dlc_full_path);
            
            if ($dlc_size > 0) {
                $name = "";
                $sfo_path = find_sfo($host_ip, $port, $dlc_full_path);
                if ($sfo_path) {
                    $sfo_title = get_title_from_sfo($host_ip, $port, $sfo_path);
                    if ($sfo_title) $name = $sfo_title;
                }
                if (empty($name)) {
                    $clean_name = str_replace($cusa, '', $dlc_folder); 
                    $clean_name = trim($clean_name, '-_');
                    if (empty($clean_name)) $clean_name = $dlc_folder;
                    $name = "CÓDIGO: " . $clean_name;
                }
                $dlcs_list[] = [
                    'type' => 'dlc', 'name' => $name,
                    'size_formatted' => format_size($dlc_size), 'path' => $dlc_full_path
                ];
            }
        }
    }
    
    $items = array_merge($updates, $dlcs_list);
    echo json_encode(['status' => 'success', 'items' => $items]);
    exit;
}

elseif ($action === 'delete') {
    $path = $_POST['path'] ?? '';
    if ($path) { 
        delete_dir($host_ip, $port, $path); 
        echo json_encode(['status' => 'success']); 
    } else { 
        echo json_encode(['status' => 'error', 'message' => 'Ruta inválida.']); 
    }
    exit;
}
?>
