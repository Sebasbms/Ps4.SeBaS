<?php
/**
 * ARCHIVO: api/ps4_screenshots.php
 * Buscador de Capturas: Soporta Bóveda Global (scan_all), Contador (count_only) y motor ftp_rawlist.
 */
$action = $_REQUEST['action'] ?? '';
$host_ip = $_REQUEST['host_ip'] ?? '';
$cusa = $_REQUEST['cusa_id'] ?? '';

// MODO STREAMING: Muestra la foto directo en tu celular
if ($action === 'stream') {
    $path = $_GET['path'] ?? '';
    if (!$host_ip || !$path) exit;
    
    set_time_limit(0); 
    
    $conn = @ftp_connect($host_ip, 2121, 5);
    if ($conn) {
        @ftp_login($conn, "anonymous", "");
        ftp_pasv($conn, true);
        
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'png') {
            header('Content-Type: image/png');
        } else {
            header('Content-Type: image/jpeg');
        }
        
        $temp = fopen('php://output', 'w');
        @ftp_fget($conn, $temp, $path, FTP_BINARY, 0);
        @ftp_close($conn);
    }
    exit;
}

header('Content-Type: application/json');
set_time_limit(60); // Aumentamos un poco el tiempo para la Bóveda Global

if (!$host_ip) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos de conexión.']);
    exit;
}

$conn = @ftp_connect($host_ip, 2121, 5);
if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'No conecta a PS4.']);
    exit;
}
@ftp_login($conn, "anonymous", "");
ftp_pasv($conn, true);

$capturas = [];
$base_photo = "/user/av_contents/photo";

// Función Taladro (Segura para memoria usando ftp_rawlist)
function excavar_fotos($conn, $dir, &$capturas, $profundidad = 0) {
    if ($profundidad > 5) return; 
    
    $raw_list = @ftp_rawlist($conn, $dir);
    if (is_array($raw_list)) {
        foreach ($raw_list as $line) {
            $parts = preg_split('/\s+/', $line, 9);
            if (count($parts) >= 9) {
                $name = $parts[8];
                if ($name === '.' || $name === '..') continue;
                
                $ruta_completa = rtrim($dir, '/') . '/' . $name;
                $is_dir = (substr($parts[0], 0, 1) === 'd');
                
                if ($is_dir) {
                    excavar_fotos($conn, $ruta_completa, $capturas, $profundidad + 1);
                } else {
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if ($ext === 'jpg' || $ext === 'jpeg' || $ext === 'png') {
                        $capturas[] = $ruta_completa;
                    }
                }
            }
        }
    }
}

// MODO BÓVEDA GLOBAL (Escanea TODO sin importar el CUSA)
if ($action === 'scan_all') {
    $raw_nivel1 = @ftp_rawlist($conn, $base_photo);
    if (is_array($raw_nivel1)) {
        foreach ($raw_nivel1 as $line1) {
            $parts1 = preg_split('/\s+/', $line1, 9);
            if (count($parts1) >= 9) {
                $name1 = $parts1[8];
                if ($name1 === '.' || $name1 === '..') continue;
                
                $is_dir1 = (substr($parts1[0], 0, 1) === 'd');
                if ($is_dir1) {
                    excavar_fotos($conn, "$base_photo/$name1", $capturas);
                }
            }
        }
    }
    
    @ftp_close($conn);
    
    if (empty($capturas)) {
        echo json_encode(['status' => 'error', 'message' => "No se encontraron fotos en la consola."]);
    } else {
        sort($capturas);
        echo json_encode(['status' => 'success', 'data' => array_reverse($capturas)]);
    }
    exit;
}

// MODO ESPECÍFICO DE JUEGO (O CONTADOR)
if (!$cusa) {
    echo json_encode(['status' => 'error', 'message' => 'Falta el ID del juego.']);
    exit;
}

$rutas_cusa = [];
$raw_nivel1 = @ftp_rawlist($conn, $base_photo);
if (is_array($raw_nivel1)) {
    foreach ($raw_nivel1 as $line1) {
        $parts1 = preg_split('/\s+/', $line1, 9);
        if (count($parts1) >= 9) {
            $name1 = $parts1[8];
            if ($name1 === '.' || $name1 === '..') continue;
            
            $is_dir1 = (substr($parts1[0], 0, 1) === 'd');
            
            if ($is_dir1) {
                $ruta1 = "$base_photo/$name1";
                if (stripos($name1, $cusa) !== false) {
                    $rutas_cusa[] = $ruta1; 
                } else {
                    $raw_nivel2 = @ftp_rawlist($conn, $ruta1);
                    if (is_array($raw_nivel2)) {
                        foreach ($raw_nivel2 as $line2) {
                            $parts2 = preg_split('/\s+/', $line2, 9);
                            if (count($parts2) >= 9) {
                                $name2 = $parts2[8];
                                if ($name2 === '.' || $name2 === '..') continue;
                                
                                $is_dir2 = (substr($parts2[0], 0, 1) === 'd');
                                if ($is_dir2 && stripos($name2, $cusa) !== false) {
                                    $rutas_cusa[] = "$ruta1/$name2";
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

foreach ($rutas_cusa as $ruta) {
    excavar_fotos($conn, $ruta, $capturas);
}

@ftp_close($conn);

// MODO SOLO CONTADOR (Para el indicador en el menú de opciones)
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
