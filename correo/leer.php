<?php
session_start();
require_once '../config/db.php';

$usuario_id = $_SESSION['usuario_id'] ?? 1;
$msg_id = $_GET['id'] ?? null;

if (!$msg_id) {
    die("Mensaje no especificado.");
}

$stmt = $pdo->prepare("SELECT correo_usuario, correo_password FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch();

$imap_user = $usuario['correo_usuario'];
$imap_pass = $usuario['correo_password'];
$imap_host = '{mail.tudominio.com:993/imap/ssl}INBOX';

$inbox = @imap_open($imap_host, $imap_user, $imap_pass);

if (!$inbox) {
    die("No se pudo conectar: " . imap_last_error());
}

$cabecera = imap_headerinfo($inbox, $msg_id);
$remitente = $cabecera->from[0]->mailbox . '@' . $cabecera->from[0]->host;
$asunto = $cabecera->subject ?? '(Sin asunto)';
$fecha = $cabecera->date;
$mensaje = imap_body($inbox, $msg_id);
imap_close($inbox);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Leer correo</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">
  <h2><?= htmlspecialchars($asunto) ?></h2>
  <p><strong>De:</strong> <?= htmlspecialchars($remitente) ?></p>
  <p><strong>Fecha:</strong> <?= $fecha ?></p>
  <hr>
  <div class="border p-3 bg-light">
    <?= nl2br(htmlspecialchars($mensaje)) ?>
  </div>
  <a href="index.php" class="btn btn-secondary mt-3">Volver</a>
</body>
</html>
