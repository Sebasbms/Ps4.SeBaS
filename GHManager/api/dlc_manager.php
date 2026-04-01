<?php
/**
 * ARCHIVO: api/dlc_manager.php
 * Gestor Final: Updates siempre arriba y Borrado corregido
 */
$action = $_POST['action'] ?? '';
$host_ip = $_POST['host_ip'] ?? '';
$cusa = $_POST['cusa_id'] ?? '';

header('Content-Type: application/json');

if (!$host_ip) { echo json_encode(['status' => 'error', 'message' => 'Falta IP.']); exit; }
if ($action === 'scan' && !$cusa) { echo json_encode(['status' => 'error', 'message' => 'Falta CUSA.']); exit; }

$conn = @ftp_connect($host_ip, 2121, 5);
if (!$conn) { echo json_encode(['status' => 'error', 'message' => 'No conecta a PS4.']); exit; }
@ftp_login($conn, "anonymous", "");
ftp_pasv($conn, true);

function format_size($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

function get_dir_size($conn, $dir) {
    $size = 0;
    $items = @ftp_rawlist($conn, $dir);
    if (is_array($items)) {
        foreach ($items as $item) {
            $info = preg_split("/[\s]+/", $item, 9);
            if (count($info) == 9) {
                $is_dir = ($info[0][0] === 'd');
                $filesize = (int)$info[4];
                $name = $info[8];
                if ($name === '.' || $name === '..') continue;
                $path = rtrim($dir, '/') . '/' . $name;
                if ($is_dir) { $size += get_dir_size($conn, $path); } else { $size += $filesize; }
            }
        }
    }
    return $size;
}

function delete_dir($conn, $dir) {
    $files = @ftp_nlist($conn, $dir);
    if (is_array($files)) {
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;
            $path = (strpos($file, '/') === 0) ? $file : rtrim($dir, '/') . '/' . basename($file);
            if (@ftp_delete($conn, $path)) continue;
            delete_dir($conn, $path);
        }
    }
    @ftp_rmdir($conn, $dir);
}

function find_sfo($conn, $dir, $depth = 0) {
    if ($depth > 2) return false;
    $items = @ftp_nlist($conn, $dir);
    if (is_array($items)) {
        foreach ($items as $item) {
            $base = basename($item);
            if ($base === '.' || $base === '..') continue;
            $path = (strpos($item, '/') === 0) ? $item : rtrim($dir, '/') . '/' . $base;
            
            if (strtolower($base) === 'param.sfo') return $path;
            
            if (strpos($base, '.') === false || strtolower($base) === 'sce_sys') {
                $found = find_sfo($conn, $path, $depth + 1);
                if ($found) return $found;
            }
        }
    }
    return false;
}

function get_title_from_sfo($conn, $sfo_path) {
    $temp = fopen('php://temp', 'r+');
    if (@ftp_fget($conn, $temp, $sfo_path, FTP_BINARY, 0)) {
        rewind($temp);
        $sfo = stream_get_contents($temp);
        fclose($temp);
        if (substr($sfo, 0, 4) === "\0PSF") {
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
    } else { if ($temp) fclose($temp); }
    return false;
}

if ($action === 'scan') {
    $updates = [];
    $dlcs_list = [];
    $bases = ['/user', '/mnt/ext0/user', '/mnt/ext1/user'];
    
    foreach ($bases as $base) {
        $patch_path = "$base/patch/$cusa";
        $patch_size = get_dir_size($conn, $patch_path);
        if ($patch_size > 0) {
            $updates[] = [
                'type' => 'update', 'name' => 'Actualización (Parche Base)',
                'size_formatted' => format_size($patch_size), 'path' => $patch_path
            ];
        }

        $addcont_path = "$base/addcont/$cusa";
        $dlcs = @ftp_nlist($conn, $addcont_path);
        if (is_array($dlcs)) {
            foreach ($dlcs as $dlc) {
                $dlc_folder = basename($dlc);
                if ($dlc_folder === '.' || $dlc_folder === '..') continue;
                
                $dlc_full_path = (strpos($dlc, '/') === 0) ? $dlc : "$addcont_path/$dlc_folder";
                $dlc_size = get_dir_size($conn, $dlc_full_path);
                
                if ($dlc_size > 0) {
                    $name = "";
                    $sfo_path = find_sfo($conn, $dlc_full_path);
                    if ($sfo_path) {
                        $sfo_title = get_title_from_sfo($conn, $sfo_path);
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
    }
    
    // MAGIA: Pegamos la lista de Updates primero, y la de DLCs después.
    $items = array_merge($updates, $dlcs_list);
    
    echo json_encode(['status' => 'success', 'items' => $items]);
}
elseif ($action === 'delete') {
    $path = $_POST['path'] ?? '';
    if ($path) { delete_dir($conn, $path); echo json_encode(['status' => 'success']); } 
    else { echo json_encode(['status' => 'error', 'message' => 'Ruta inválida.']); }
}
@ftp_close($conn);
?>
