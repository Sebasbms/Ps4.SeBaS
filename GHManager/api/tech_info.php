<?php
/**
 * ARCHIVO: api/tech_info.php
 * Calcula el peso total del juego (Base + Updates + DLCs) y detecta la ubicación
 */
$host_ip = $_POST['host_ip'] ?? '';
$cusa = $_POST['cusa_id'] ?? '';

header('Content-Type: application/json');

if (!$host_ip || !$cusa) {
    echo json_encode(['status' => 'error', 'size' => 'Error', 'location' => 'Error']);
    exit;
}

$conn = @ftp_connect($host_ip, 2121, 5);
if (!$conn) {
    echo json_encode(['status' => 'error', 'size' => 'Error', 'location' => 'Error']);
    exit;
}
@ftp_login($conn, "anonymous", "");
ftp_pasv($conn, true);

// Función para sumar el peso de una carpeta
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

// Función para convertir a GB/MB
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
$size_internal = get_dir_size($conn, $path_internal);

if ($size_internal > 0) {
    $total_size += $size_internal;
    $location = 'Alm. Interno';
} else {
    $path_ext0 = "/mnt/ext0/user/app/$cusa";
    $size_ext0 = get_dir_size($conn, $path_ext0);
    if ($size_ext0 > 0) {
        $total_size += $size_ext0;
        $location = 'Alm. Ampliado';
    } else {
        $path_ext1 = "/mnt/ext1/user/app/$cusa";
        $size_ext1 = get_dir_size($conn, $path_ext1);
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
    $total_size += get_dir_size($conn, $ruta);
}

@ftp_close($conn);

if ($total_size > 0) {
    echo json_encode(['status' => 'success', 'size' => format_size($total_size), 'location' => $location]);
} else {
    echo json_encode(['status' => 'error', 'size' => '--', 'location' => '--']);
}
?>
