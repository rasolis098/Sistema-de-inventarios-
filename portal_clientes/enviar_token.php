<?php
require '../config/db.php';
require 'funciones.php'; // Aquí deberás tener PHPMailer para enviar correo

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_o_codigo = $_POST['email_o_codigo'] ?? '';
    if (!$email_o_codigo) {
        die("Por favor ingresa email o código.");
    }

    // Buscar cliente
    $stmt = $pdo->prepare("SELECT id, nombre, email FROM clientes WHERE email = ? OR codigo = ? LIMIT 1");
    $stmt->execute([$email_o_codigo, $email_o_codigo]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        die("Cliente no encontrado.");
    }

    // Generar token aleatorio
    $token = bin2hex(random_bytes(32));
    $ahora = new DateTime();
    $expira = new DateTime('+15 minutes');

    // Guardar token en BD
    $stmt = $pdo->prepare("INSERT INTO cliente_tokens (cliente_id, token, creado_en, expiracion) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $cliente['id'], 
        $token,
        $ahora->format('Y-m-d H:i:s'),
        $expira->format('Y-m-d H:i:s')
    ]);

    // Enviar correo con link de acceso
    $url = "https://inv.dialexanader.com/portal_clientes/ver_pedido.php?token=$token";

    $asunto = "Acceso a tus pedidos - Token temporal";
    $mensaje = "Hola " . htmlspecialchars($cliente['nombre']) . ",<br><br>" .
               "Haz clic en el siguiente enlace para acceder a tus pedidos. Este enlace es válido por 15 minutos:<br>" .
               "<a href='$url'>$url</a><br><br>Si no solicitaste este acceso, ignora este correo.";

    enviarCorreoSimple($cliente['email'], $asunto, $mensaje); // función que envía correo simple (usa PHPMailer)

    echo "Te hemos enviado un enlace de acceso a tu correo electrónico.";
    exit;
}
?>

<!-- Formulario sencillo para que ingrese email o código -->
<form method="POST">
    <label>Ingresa tu email o código:</label><br>
    <input type="text" name="email_o_codigo" required><br><br>
    <button type="submit">Enviar enlace de acceso</button>
</form>
