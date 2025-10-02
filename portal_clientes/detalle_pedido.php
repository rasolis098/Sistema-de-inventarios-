<?php
require '../config/db.php';

if (!isset($_GET['id'], $_GET['token'])) {
    die("Acceso no autorizado.");
}

$pedido_id = (int)$_GET['id'];
$token = $_GET['token'];

// Validar token y cliente
$stmt = $pdo->prepare("SELECT cliente_id, expiracion, usado FROM cliente_tokens WHERE token = ? LIMIT 1");
$stmt->execute([$token]);
$tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tokenData) {
    die("Token inválido.");
}

$ahora = new DateTime();
$expiracion = new DateTime($tokenData['expiracion']);

if ($ahora > $expiracion) {
    die("El enlace ha expirado.");
}

// Verificar que el pedido pertenece al cliente del token
$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ? AND cliente_id = ?");
$stmt->execute([$pedido_id, $tokenData['cliente_id']]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    die("Pedido no encontrado o no autorizado.");
}

// Obtener cliente
$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$tokenData['cliente_id']]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener detalles del pedido y productos
$stmt = $pdo->prepare("
    SELECT pd.*, p.nombre 
    FROM pedido_detalles pd 
    JOIN productos p ON pd.producto_id = p.id 
    WHERE pd.pedido_id = ?");
$stmt->execute([$pedido_id]);
$detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Detalle Pedido #<?= $pedido_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
<div class="container py-4">
    <h2>Detalle del Pedido #<?= $pedido_id ?></h2>

    <h4>Cliente</h4>
    <p><strong>Nombre:</strong> <?= htmlspecialchars($cliente['nombre']) ?></p>
    <p><strong>RFC:</strong> <?= htmlspecialchars($cliente['rfc']) ?></p>
    <p><strong>Teléfono:</strong> <?= htmlspecialchars($cliente['telefono']) ?></p>
    <p><strong>Dirección:</strong> <?= htmlspecialchars($cliente['direccion']) ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($cliente['email']) ?></p>

    <h4>Pedido</h4>
    <p><strong>Fecha pedido:</strong> <?= date("d/m/Y H:i", strtotime($pedido['fecha'])) ?></p>
    <p><strong>Fecha entrega:</strong> <?= date("d/m/Y", strtotime($pedido['fecha_entrega'])) ?></p>
    <p><strong>Estado:</strong> <?= ucfirst($pedido['estado']) ?></p>
    <p><strong>Observaciones:</strong> <?= nl2br(htmlspecialchars($pedido['observaciones'])) ?></p>

    <h4>Productos</h4>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio Unitario</th>
                <th>IVA (%)</th>
                <th>Descuento (%)</th>
                <th>Importe</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($detalles as $d): ?>
            <tr>
                <td><?= htmlspecialchars($d['nombre']) ?></td>
                <td><?= $d['cantidad'] ?></td>
                <td>$<?= number_format($d['precio_unitario'], 2) ?></td>
                <td><?= number_format($d['iva'], 2) ?>%</td>
                <td><?= number_format($d['descuento'], 2) ?>%</td>
                <td>$<?= number_format($d['importe'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <a href="ver_pedido.php?token=<?= urlencode($token) ?>" class="btn btn-secondary">Volver a lista de pedidos</a>
    <a href="pedido_pdf.php?id=<?= $pedido_id ?>&token=<?= urlencode($token) ?>" target="_blank" class="btn btn-success">Descargar PDF</a>
</div>
</body>
</html>
