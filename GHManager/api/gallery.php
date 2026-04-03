<?php
/**
 * ARCHIVO: api/gallery.php
 * Descarga imágenes (Motor cURL Termux), lee galería local y backups.
 */
header('Content-Type: application/json; charset=utf-8');

function curl_descargar($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'GoldHen-Manager-Termux');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Evita errores de certificados en Termux
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code >= 200 && $code < 300) ? $data : false;
}

if (isset($_POST['action']) && $_POST['action'] == 'import_url') {
    $url = trim($_POST['url'] ?? '');
    if (!$url) { echo json_encode(['status'=>'error', 'message'=>'URL vacía']); exit; }
    
    $directorio_destino = '../iconos';
    if (!file_exists($directorio_destino)) { @mkdir($directorio_destino, 0777, true); }
    $imported_count = 0;
    
    try {
        if (preg_match('/^https:\/\/github\.com\/([^\/]+)\/([^\/]+)\/tree\/([^\/]+)\/(.*)$/', $url, $matches)) {
            $user = $matches[1]; $repo = $matches[2]; $branch = $matches[3]; $path = $matches[4];
            $api_url = "https://api.github.com/repos/$user/$repo/contents/$path?ref=$branch";
            
            $json = curl_descargar($api_url);
            if ($json) {
                $files = json_decode($json, true);
                if(is_array($files)) {
                    foreach ($files as $file) {
                        if (isset($file['download_url']) && preg_match('/\.(png|jpe?g)$/i', $file['name'])) {
                            $img_data = curl_descargar($file['download_url']);
                            if ($img_data) { 
                                file_put_contents($directorio_destino . '/' . basename($file['name']), $img_data); 
                                $imported_count++; 
                            }
                        }
                    }
                }
            } else { 
                throw new Exception("Error de GitHub. Verifica que el enlace sea público o intenta más tarde."); 
            }
        } else {
            $img_data = curl_descargar($url);
            if ($img_data) {
                $filename = basename(parse_url($url, PHP_URL_PATH));
                if (!preg_match('/\.(png|jpe?g)$/i', $filename)) $filename = uniqid('cover_') . '.png';
                file_put_contents($directorio_destino . '/' . $filename, $img_data);
                $imported_count++;
            } else { 
                throw new Exception("No se pudo descargar la imagen. Enlace roto o bloqueado."); 
            }
        }
        
        if ($imported_count > 0) { 
            echo json_encode(['status'=>'success', 'message'=>"$imported_count portada(s) guardada(s)."]); 
        } else { 
            echo json_encode(['status'=>'error', 'message'=>'No se encontraron imágenes válidas.']); 
        }
    } catch (Exception $e) { 
        echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]); 
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'delete_image') {
    $filename = basename($_POST['file_name'] ?? '');
    $folder = $_POST['folder'] ?? ''; 
    $dir = ($folder === 'backup_icons') ? '../backup_icons' : '../iconos';
    $path = $dir . '/' . $filename;
    
    if ($filename && file_exists($path) && @unlink($path)) {
        echo json_encode(['status' => 'success', 'message' => 'Imagen eliminada correctamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar la imagen en Termux.']);
    }
    exit;
}

if (isset($_GET['action']) && ($_GET['action'] == 'get_gallery' || $_GET['action'] == 'get_backups')) {
    $lista = [];
    $directorio = ($_GET['action'] == 'get_gallery') ? '../iconos' : '../backup_icons';
    $url_base = ($_GET['action'] == 'get_gallery') ? 'iconos/' : 'backup_icons/';
    
    if (file_exists($directorio) && is_dir($directorio)) {
        $archivos = glob($directorio . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
        if(is_array($archivos)) { 
            foreach($archivos as $archivo) { 
                $lista[] = ['nombre' => basename($archivo), 'url' => $url_base . basename($archivo)]; 
            } 
        }
    }
    echo json_encode(['status'=>'success', 'data'=>$lista]);
    exit;
}
?>
