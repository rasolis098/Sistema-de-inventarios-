<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
require_once '../config/db.php';
session_start();

$para = $_POST['para'] ?? '';
$asunto = $_POST['asunto'] ?? '';
$mensaje = $_POST['mensaje'] ?? '';

$usuario_id = $_SESSION['usuario_id'];
$stmt = $pdo->prepare("SELECT correo_usuario, correo_password FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Enviar correo
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.serverneubox.com.mx';
    $mail->SMTPAuth   = true;
    $mail->Username   = $usuario['correo_usuario'];
    $mail->Password   = $usuario['correo_password'];
    $mail->SMTPSecure = 'ssl';
    $mail->Port       = 465;

    $mail->setFrom($usuario['correo_usuario'], 'Respuesta');
    $mail->addAddress($para);
    $mail->Subject = $asunto;
    $mail->isHTML(false);
    $mail->Body    = $mensaje;

    $mail->send();
    header("Location: index.php?msg=enviado");
} catch (Exception $e) {
    echo "No se pudo enviar el mensaje. Error: {$mail->ErrorInfo}";
}
