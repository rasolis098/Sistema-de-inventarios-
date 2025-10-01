<?php
include '../config/db.php';
include 'funciones.php';

$max_intentos = 5;
$minutos_espera = 10;

$sql = "SELECT * FROM pedidos 
        WHERE estado = 'pendiente' 
        AND correo_enviado = 0 
        AND (fecha_ultimo_intento IS NULL OR fecha_ultimo_intento <= NOW() - INTERVAL ? MINUTE)
        AND intentos_envio < ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$minutos_espera, $max_intentos]);
$pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($pendientes as $pedido) {
    enviarCorreoPedidoPendiente($pdo, $pedido['id'], $pedido['cliente_id'], $pedido['fecha_entrega'], $pedido['total']);
}
