<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

header('Content-Type: application/json');

try {
    // Notificaciones de stock bajo
    $stock_bajo = $pdo->query("
        SELECT nombre, stock 
        FROM productos 
        WHERE stock <= 10 AND stock > 0 
        ORDER BY stock ASC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Productos agotados
    $agotados = $pdo->query("
        SELECT nombre 
        FROM productos 
        WHERE stock = 0 
        ORDER BY nombre 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'stock_bajo' => $stock_bajo,
            'agotados' => $agotados,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>