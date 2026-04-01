<?php
/**
 * ARCHIVO: api/gallery.php
 * Descarga imágenes de internet, lee la galería local, lee los backups y elimina archivos.
 */
header('Content-Type: application/json; charset=utf-8');

// --- IMPORTAR DESDE URL O GITHUB ---
if (isset($_POST['action']) && $_POST['action'] == 'import_url') {
    $url = trim($_POST['url'] ?? '');
    if (!$url) { echo json_encode(['status'=>'error', 'message'=>'URL vacía']); exit; }
    
    $directorio_destino = '../iconos';
    if (!file_exists($directorio_destino)) { @mkdir($directorio_destino, 0777, true); }
    $imported_count = 0;
    
    try {
        $opts = ['http' => ['method' => 'GET', 'header' => ['User-Agent: GoldHen-Manager']]];
        $context = stream_context_create($opts);

        if (preg_match('/^https:\/\/github\.com\/([^\/]+)\/([^\/]+)\/tree\/([^\/]+)\/(.*)$/', $url, $matches)) {
            $user = $matches[1]; $repo = $matches[2]; $branch = $matches[3]; $path = $matches[4];
            $api_url = "https://api.github.com/repos/$user/$repo/contents/$path?ref=$branch";
            
            $json = @file_get_contents($api_url, false, $context);
            if ($json) {
                $files = json_decode($json, true);
                foreach ($files as $file) {
                    if (isset($file['download_url']) && preg_match('/\.(png|jpe?g)$/i', $file['name'])) {
                        $img_data = @file_get_contents($file['download_url'], false, $context);
                        if ($img_data) { 
                            file_put_contents($directorio_destino . '/' . basename($file['name']), $img_data); 
                            $imported_count++; 
                        }
                    }
                }
            } else { 
                // DETECCIÓN INTELIGENTE DE LÍMITE DE GITHUB
                $headers = $http_response_header ?? [];
                if (strpos(implode(' ', $headers), '403 Forbidden') !== false) {
                    throw new Exception("Límite de GitHub alcanzado (60 descargas/hora). Intenta más tarde.");
                }
                throw new Exception("No se pudo leer la carpeta. Verifica que el repositorio sea público."); 
            }
        } else {
            $img_data = @file_get_contents($url, false, $context);
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
            echo json_encode(['status'=>'error', 'message'=>'No se encontraron imágenes válidas en ese enlace.']); 
        }
    } catch (Exception $e) { 
        echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]); 
    }
    exit;
}

// --- ELIMINAR IMAGEN ---
if (isset($_POST['action']) && $_POST['action'] == 'delete_image') {
    $filename = basename($_POST['file_name'] ?? '');
    $folder = $_POST['folder'] ?? ''; 
    
    $dir = ($folder === 'backup_icons') ? '../backup_icons' : '../iconos';
    $path = $dir . '/' . $filename;
    
    if ($filename && file_exists($path) && @unlink($path)) {
        echo json_encode(['status' => 'success', 'message' => 'Imagen eliminada correctamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar la imagen.']);
    }
    exit;
}

// --- LEER GALERÍA LOCAL ---
if (isset($_GET['action']) && $_GET['action'] == 'get_gallery') {
    $iconos = [];
    $directorio = '../iconos';
    if (file_exists($directorio) && is_dir($directorio)) {
        $archivos = glob($directorio . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
        if(is_array($archivos)) { 
            foreach($archivos as $archivo) { 
                $iconos[] = ['nombre' => basename($archivo), 'url' => 'iconos/' . basename($archivo)]; 
            } 
        }
    }
    echo json_encode(['status'=>'success', 'data'=>$iconos]);
    exit;
}

// --- LEER GALERÍA DE BACKUPS ---
if (isset($_GET['action']) && $_GET['action'] == 'get_backups') {
    $backups = [];
    $directorio = '../backup_icons';
    if (file_exists($directorio) && is_dir($directorio)) {
        $archivos = glob($directorio . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
        if(is_array($archivos)) {
            foreach($archivos as $archivo) {
                $backups[] = ['nombre' => basename($archivo), 'url' => 'backup_icons/' . basename($archivo)];
            }
        }
    }
    echo json_encode(['status'=>'success', 'data'=>$backups]);
    exit;
}
?>
