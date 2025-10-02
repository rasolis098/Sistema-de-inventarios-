<?php
require_once '../config/db.php';

// Función para obtener cliente por código o email
function obtenerCliente($valor) {
    global $pdo;
    $sql = "SELECT * FROM clientes WHERE codigo = ? OR email = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$valor, $valor]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Función para obtener pedidos del cliente
function obtenerPedidosPorCliente($cliente_id) {
    global $pdo;
    $sql = "SELECT * FROM pedidos WHERE cliente_id = ? ORDER BY fecha DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cliente_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener los detalles de un pedido
function obtenerDetallesPedido($pedido_id) {
    global $pdo;
    $sql = "SELECT pd.*, pr.nombre 
            FROM pedido_detalles pd
            JOIN productos pr ON pr.id = pd.producto_id
            WHERE pd.pedido_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$pedido_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para calcular totales del cliente
function calcularTotalesCliente($cliente_id) {
    global $pdo;
    $sql = "SELECT 
                SUM(total) AS total_compras,
                COUNT(*) AS total_pedidos,
                MAX(fecha) AS ultima_fecha,
                MAX(total) AS ultimo_monto
            FROM pedidos
            WHERE cliente_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cliente_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
