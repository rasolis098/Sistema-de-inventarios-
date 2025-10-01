<?php
session_start();

// Redirigir si el usuario no ha iniciado sesión en el sistema o en el correo
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}
if (!isset($_SESSION['imap_pass'])) {
    header('Location: conectar_correo.php');
    exit;
}

// Validar que la solicitud sea por método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Método no permitido.");
}

// Validar que se haya enviado un ID de mensaje
$msg_id = filter_input(INPUT_POST, 'msg_id', FILTER_VALIDATE_INT);
if (!$msg_id) {
    // Si no hay ID, redirigir de vuelta a la bandeja de entrada
    header('Location: index.php');
    exit;
}

// Usar las credenciales de la sesión (método consistente y seguro)
$imap_user = $_SESSION['imap_user'];
$imap_pass = $_SESSION['imap_pass'];
$imap_host = '{mail.dialexander.com:993/imap/ssl/novalidate-cert}INBOX';

$inbox = @imap_open($imap_host, $imap_user, $imap_pass);

if (!$inbox) {
    // Manejo de error en caso de que la conexión falle
    // Podríamos guardar un mensaje en la sesión para mostrarlo en index.php
    $_SESSION['error_message'] = "No se pudo conectar al servidor de correo para eliminar el mensaje. Error: " . imap_last_error();
    header("Location: index.php");
    exit;
}

// Marcar el mensaje para ser eliminado y purgar el buzón
imap_delete($inbox, $msg_id);
imap_expunge($inbox);
imap_close($inbox);

// Redirigir a la bandeja de entrada después de la eliminación exitosa
header("Location: index.php");
exit;
?>
