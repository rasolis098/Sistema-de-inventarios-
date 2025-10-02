<?php
require_once '../config/db.php';
require_once 'funciones.php';

// Verificamos que se pasen datos necesarios
$identificador = $_GET['email'] ?? $_GET['codigo'] ?? $_GET['token'] ?? null;
$pedido_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$identificador || !$pedido_id) {
    die("Acceso inválido.");
}

// Obtener cliente (por email, código o token)
$cliente = obtenerCliente($identificador);

if (!$cliente) {
    die("Cliente no encontrado.");
}

// Verificar que el pedido pertenezca al cliente
$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ? AND cliente_id = ?");
$stmt->execute([$pedido_id, $cliente['id']]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    die("Pedido no encontrado o no pertenece al cliente.");
}

// Obtener los detalles del pedido
$detalles = obtenerDetallesPedido($pedido_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Pedido #<?= $pedido_id ?> - <?= htmlspecialchars($cliente['nombre']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
<div class="container py-4">
    <h2 class="mb-4">Pedido #<?= $pedido_id ?></h2>

    <div class="card mb-4">
        <div class="card-header bg-dark text-white">Datos del Cliente</div>
        <div class="card-body">
            <p><strong>Nombre:</strong> <?= htmlspecialchars($cliente['nombre']) ?></p>
            <p><strong>RFC:</strong> <?= htmlspecialchars($cliente['rfc']) ?></p>
            <p><strong>Dirección:</strong> <?= htmlspecialchars($cliente['direccion']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($cliente['email']) ?></p>
            <p><strong>Código:</strong> <?= htmlspecialchars($cliente['codigo']) ?></p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">Datos del Pedido</div>
        <div class="card-body">
            <p><strong>Fecha del pedido:</strong> <?= date("d/m/Y H:i", strtotime($pedido['fecha'])) ?></p>
            <p><strong>Fecha de entrega:</strong> <?= htmlspecialchars(substr($pedido['fecha_entrega'], 0, 10)) ?></p>
            <p><strong>Estado:</strong> <?= ucfirst($pedido['estado']) ?></p>
            <p><strong>Observaciones:</strong> <?= nl2br(htmlspecialchars($pedido['observaciones'])) ?></p>
            <p><strong>Total:</strong> $<?= number_format($pedido['total'], 2) ?></p>
        </div>
    </div>

    <h4 class="mb-3">Productos</h4>
    <table class="table table-bordered table-sm">
        <thead class="table-secondary">
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
        <?php foreach ($detalles as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['nombre']) ?></td>
                <td><?= $item['cantidad'] ?></td>
                <td>$<?= number_format($item['precio_unitario'], 2) ?></td>
                <td><?= $item['iva'] ?>%</td>
                <td><?= $item['descuento'] ?>%</td>
                <td>$<?= number_format($item['importe'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <a href="index.php?<?= is_numeric($identificador) ? 'codigo' : 'email' ?>=<?= urlencode($identificador) ?>" class="btn btn-secondary mt-3">Volver</a>

    <a href="ver_pedido_pdf.php?id=<?= $pedido_id ?>&identificador=<?= urlencode($identificador) ?>" class="btn btn-success mt-3" target="_blank">
        Descargar PDF
    </a>
</div>
</body>
</html>
