<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Si las credenciales de correo no están en la sesión, se redirige a la página de conexión.
if (!isset($_SESSION['imap_user']) || !isset($_SESSION['imap_pass'])) {
    header('Location: conectar_correo.php');
    exit;
}

require_once '../config/db.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$mensaje_feedback = '';
$correo_remitente = $_SESSION['imap_user'];
$password_correo = $_SESSION['imap_pass']; // La contraseña normal del correo

// Obtener el nombre del remitente desde la BD
$stmt = $pdo->prepare("SELECT nombre FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
$nombre_remitente = $usuario['nombre'] ?? 'Usuario del Sistema';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = trim($_POST['para']);
    $subject = trim($_POST['asunto']);
    $body = $_POST['mensaje'];

    $mail = new PHPMailer(true);

    try {
        // --- CONFIGURACIÓN CORREGIDA DEL SERVIDOR SMTP ---
        // Descomenta la siguiente línea para obtener un registro detallado del error de conexión
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        
        $mail->isSMTP();
        // Se corrige el Host para usar tu servidor de correo.
        $mail->Host = 'mail.dialexander.com'; 
        $mail->SMTPAuth = true;
        $mail->Username = $correo_remitente; // De la sesión
        $mail->Password = $password_correo;  // De la sesión (contraseña normal)
        // Se especifica el método de encriptación SMTPS (antes SSL), común para el puerto 465.
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';

        // Configuración de los correos
        $mail->setFrom($correo_remitente, $nombre_remitente);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = nl2br(htmlspecialchars($body)); // Sanitizar el cuerpo del mensaje
        $mail->AltBody = htmlspecialchars($body);

        $mail->send();
        $mensaje_feedback = '<div class="alert alert-success mt-3">Correo enviado correctamente.</div>';

    } catch (Exception $e) {
        // Se muestra un error más detallado.
        $mensaje_feedback = '<div class="alert alert-danger mt-3"><strong>Error al enviar el correo:</strong> ' . $mail->ErrorInfo . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Redactar Correo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .compose-card { max-width: 800px; margin: 40px auto; }
    </style>
</head>
<body>
    <div class="container compose-card">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white fs-5">
                <i class="bi bi-pencil-square"></i> Redactar Correo
            </div>
            <div class="card-body p-4">
                <?= $mensaje_feedback ?>
                <form method="post" action="redactar.php">
                    <div class="mb-3">
                        <label for="para" class="form-label">Para:</label>
                        <input type="email" name="para" id="para" class="form-control" required placeholder="destinatario@ejemplo.com">
                    </div>
                    <div class="mb-3">
                        <label for="asunto" class="form-label">Asunto:</label>
                        <input type="text" name="asunto" id="asunto" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="mensaje" class="form-label">Mensaje:</label>
                        <textarea name="mensaje" id="mensaje" class="form-control" rows="10" required></textarea>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Volver a Bandeja</a>
                        <button type="submit" class="btn btn-success"><i class="bi bi-send"></i> Enviar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

