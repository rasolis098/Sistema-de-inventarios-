<?php
// Iniciar sesión para manejar mensajes de feedback
session_start();

include '../config/db.php';

// Validar que la solicitud sea por POST y que el ID exista
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['cotizacion_id'])) {
    die("Acceso inválido.");
}

$cotizacionId = $_POST['cotizacion_id'];

// ¡Usar transacciones es VITAL para la integridad de los datos!
$pdo->beginTransaction();

try {
    // 1. Obtener la cotización original y sus detalles
    $stmt = $pdo->prepare("SELECT * FROM cotizaciones WHERE id = ? AND estado = 'aceptada'");
    $stmt->execute([$cotizacionId]);
    $cotizacion = $stmt->fetch();

    if (!$cotizacion) {
        throw new Exception("La cotización no existe o no está en estado 'aceptada'.");
    }

    $stmtDetalles = $pdo->prepare("SELECT * FROM cotizacion_detalles WHERE cotizacion_id = ?");
    $stmtDetalles->execute([$cotizacionId]);
    $detalles = $stmtDetalles->fetchAll();

    // 2. Crear el nuevo Pedido en la base de datos
    $stmtPedido = $pdo->prepare("
        INSERT INTO pedidos (cliente_id, fecha, total, estado, cotizacion_origen_id) 
        VALUES (?, CURDATE(), ?, 'Confirmado', ?)
    ");
    $stmtPedido->execute([
        $cotizacion['cliente_id'],
        $cotizacion['total'],
        $cotizacion['id']
    ]);

    // Obtener el ID del pedido recién creado
    $nuevoPedidoId = $pdo->lastInsertId();

    // 3. Crear los detalles para el nuevo pedido
    $stmtDetallePedido = $pdo->prepare("
        INSERT INTO pedido_detalles (pedido_id, producto_id, cantidad, precio_unitario) 
        VALUES (?, ?, ?, ?)
    ");

    foreach ($detalles as $detalle) {
        $stmtDetallePedido->execute([
            $nuevoPedidoId,
            $detalle['producto_id'],
            $detalle['cantidad'],
            $detalle['precio_unitario']
        ]);
        // Aquí podrías agregar la lógica para reservar el inventario si lo manejas
    }

    // 4. Actualizar el estado de la cotización original
    $stmtUpdateCot = $pdo->prepare("UPDATE cotizaciones SET estado = 'Convertida' WHERE id = ?");
    $stmtUpdateCot->execute([$cotizacionId]);

    // 5. Si todo salió bien, confirmar los cambios
    $pdo->commit();

    // Guardar mensaje de éxito y redirigir
    $_SESSION['mensaje'] = "Pedido #$nuevoPedidoId creado exitosamente desde la cotización #$cotizacionId.";
    header("Location: ../pedidos/ver_pedido.php?id=" . $nuevoPedidoId);
    exit();

} catch (Exception $e) {
    // 6. Si algo falla, revertir todos los cambios para no dejar datos corruptos
    $pdo->rollBack();

    // Guardar mensaje de error y redirigir de vuelta a la cotización
    $_SESSION['mensaje_error'] = "Error al crear el pedido: " . $e->getMessage();
    header("Location: ../cotizaciones/ver.php?id=" . $cotizacionId);
    exit();
}
?>