<?php
/**
 * ARCHIVO: api/tech_info.php
 * Calcula el peso total del juego (Base + Updates + DLCs) y detecta la ubicación
 * (Fusión: Lógica original SeBaS + Motor cURL Moderno)
 */
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
set_time_limit(60); // Le damos tiempo para que calcule todo el peso tranquilo

$host_ip = $_POST['host_ip'] ?? '';
$cusa = $_POST['cusa_id'] ?? '';
$port = 2121;

if (!$host_ip || !$cusa) {
    echo json_encode(['status' => 'error', 'size' => 'Error', 'location' => 'Error']);
    exit;
}

// ==========================================
// MOTOR cURL (Reemplazo de ftp_rawlist)
// ==========================================
function curl_ftp_list_details($ip, $port, $path) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$ip:$port" . rtrim($path, '/') . '/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "LIST");
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Corto para saltar rápido si la carpeta no existe
    $res = curl_exec($ch);
    curl_close($ch);
    
    $items = [];
    if ($res) {
        $lines = explode("\n", trim($res));
        foreach ($lines as $line) {
            if(empty(trim($line))) continue;
            $info = preg_split("/[\s]+/", trim($line), 9);
            if(count($info) >= 9) {
                $is_dir = ($info[0][0] === 'd');
                $filesize = (int)$info[4];
                $name = $info[8];
                if ($name === '.' || $name === '..') continue;
                $items[] = ['name' => $name, 'is_dir' => $is_dir, 'size' => $filesize];
            }
        }
    }
    return $items;
}

// Función RECURSIVA para sumar el peso de una carpeta con cURL
function get_dir_size_curl($ip, $port, $dir) {
    $size = 0;
    $items = curl_ftp_list_details($ip, $port, $dir);
    foreach ($items as $item) {
        $path = rtrim($dir, '/') . '/' . $item['name'];
        if ($item['is_dir']) { 
            $size += get_dir_size_curl($ip, $port, $path); 
        } else { 
            $size += $item['size']; 
        }
    }
    return $size;
}

// Tu función original intacta para convertir a GB/MB
function format_size($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

$location = 'No encontrado';
$total_size = 0;

// 1. Buscamos el Juego Base en Interno y Externo para definir la etiqueta
$path_internal = "/user/app/$cusa";
$size_internal = get_dir_size_curl($host_ip, $port, $path_internal);

if ($size_internal > 0) {
    $total_size += $size_internal;
    $location = 'Alm. Interno';
} else {
    $path_ext0 = "/mnt/ext0/user/app/$cusa";
    $size_ext0 = get_dir_size_curl($host_ip, $port, $path_ext0);
    if ($size_ext0 > 0) {
        $total_size += $size_ext0;
        $location = 'Alm. Ampliado';
    } else {
        $path_ext1 = "/mnt/ext1/user/app/$cusa";
        $size_ext1 = get_dir_size_curl($host_ip, $port, $path_ext1);
        if ($size_ext1 > 0) {
            $total_size += $size_ext1;
            $location = 'Alm. Ampliado';
        }
    }
}

// 2. Sumamos el peso de los Parches y DLCs (si los tiene) para dar el peso total real
$carpetas_extra = [
    "/user/patch/$cusa", "/mnt/ext0/user/patch/$cusa", "/mnt/ext1/user/patch/$cusa",
    "/user/addcont/$cusa", "/mnt/ext0/user/addcont/$cusa", "/mnt/ext1/user/addcont/$cusa"
];

foreach ($carpetas_extra as $ruta) {
    $total_size += get_dir_size_curl($host_ip, $port, $ruta);
}

if ($total_size > 0) {
    echo json_encode(['status' => 'success', 'size' => format_size($total_size), 'location' => $location]);
} else {
    echo json_encode(['status' => 'error', 'size' => '--', 'location' => '--']);
}
?>
