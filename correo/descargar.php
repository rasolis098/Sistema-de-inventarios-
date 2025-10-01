<?php
session_start();
require_once '../config/db.php';

$usuario_id = $_SESSION['usuario_id'] ?? 1;
$msg_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$part_num = $_GET['part'] ?? null;
$filename = $_GET['filename'] ?? null;

if (!$msg_id || !$part_num || !$filename) {
    http_response_code(400);
    die("Parámetros insuficientes para descargar adjunto.");
}

$stmt = $pdo->prepare("SELECT correo_usuario, correo_password FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    http_response_code(401);
    die("Usuario no encontrado.");
}

$imap_user = $usuario['correo_usuario'];
$imap_pass = $usuario['correo_password'];
$imap_host = '{svgs441.serverneubox.com.mx:993/imap/ssl/novalidate-cert}INBOX';

$inbox = @imap_open($imap_host, $imap_user, $imap_pass);
if (!$inbox) {
    http_response_code(500);
    die("No se pudo conectar: " . imap_last_error());
}

// Obtener el contenido del adjunto
$part = imap_fetchbody($inbox, $msg_id, $part_num);
$structure = imap_fetchstructure($inbox, $msg_id);
$encoding = null;

// Encontrar encoding del adjunto en la estructura
if (isset($structure->parts[$part_num - 1])) {
    $encoding = $structure->parts[$part_num - 1]->encoding;
} else {
    // Para partes anidadas, se necesitaría lógica extra, se puede mejorar luego
    $encoding = 3; // Por defecto base64
}

// Decodificar
switch ($encoding) {
    case 3:
        $content = base64_decode($part);
        break;
    case 4:
        $content = quoted_printable_decode($part);
        break;
    default:
        $content = $part;
        break;
}

imap_close($inbox);

// Forzar descarga
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($content));
echo $content;
exit;
