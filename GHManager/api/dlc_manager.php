<?php
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';
$host = $_POST['host_ip'] ?? '';
$cusa = $_POST['cusa_id'] ?? '';

if ($action === 'scan') {
    $ch = curl_init();
    // Escaneamos la carpeta de patches/DLCs de la PS4
    curl_setopt($ch, CURLOPT_URL, "ftp://$host:2121/user/addcont/$cusa/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "LIST");
    $res = curl_exec($ch);
    curl_close($ch);

    $items = [];
    if ($res) {
        $lines = explode("\n", trim($res));
        foreach($lines as $l) {
            $parts = preg_split('/\s+/', trim($l), 9);
            if(count($parts) >= 9) {
                $name = $parts[8];
                $items[] = ['name' => $name, 'type' => 'dlc', 'path' => "/user/addcont/$cusa/$name", 'size_formatted' => '-- MB'];
            }
        }
    }
    echo json_encode(['status' => 'success', 'items' => $items]);
    exit;
}

if ($action === 'delete') {
    $path = $_POST['path'] ?? '';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "ftp://$host:2121/");
    curl_setopt($ch, CURLOPT_QUOTE, ["RMD $path", "DELE $path"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    curl_close($ch);
    echo json_encode(['status' => 'success']);
    exit;
}
?>
