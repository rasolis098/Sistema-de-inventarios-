<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Cargar PHPMailer desde Composer
require '../vendor/autoload.php'; // Ajusta la ruta si es necesario

$mail = new PHPMailer(true);

try {
    // Configuración SMTP
    $mail->isSMTP();
    $mail->Host = 'mail.dialexander.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'pedidos@dialexander.com'; // Usuario SMTP válido
    $mail->Password = 'Dialexander228.';             // Cambia por tu contraseña real
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Usar SSL
    $mail->Port = 465;

    // Remitente y destinatario (usa solo correos existentes)
    $mail->setFrom('ventas@dialexander.com', 'Inventario Test');
    $mail->addAddress('ventas@dialexander.com'); // Prueba enviando a tu propio correo

    // Contenido
    $mail->isHTML(true);
    $mail->Subject = '✅ Prueba de envío de correo desde PHPMailer';
    $mail->Body    = 'Este es un mensaje de prueba enviado correctamente desde <strong>PHPMailer</strong>.';

    $mail->send();
    echo '✅ Correo enviado correctamente a localhost@dialexander.com';
} catch (Exception $e) {
    echo "❌ Error al enviar correo: {$mail->ErrorInfo}";
}
