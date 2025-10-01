<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

header('Content-Type: application/json');

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $action = $_GET['action'] ?? 'all';
    
    $response = [];
    
    if ($action === 'all' || $action === 'stats') {
        $response['stats'] = [
            'total_productos' => $pdo->query("SELECT COUNT(*) AS total FROM productos")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0,
            'total_ventas' => $pdo->query("SELECT COUNT(*) AS total FROM ventas")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0,
            'total_clientes' => $pdo->query("SELECT COUNT(*) AS total FROM clientes")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0,
            'total_categorias' => $pdo->query("SELECT COUNT(*) AS total FROM categorias")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0
        ];
    }
    
    if ($action === 'all' || $action === 'top_products') {
        $sql = "SELECT p.nombre, SUM(vd.cantidad) AS total_vendido
                FROM venta_detalles vd
                INNER JOIN productos p ON p.id = vd.producto_id
                GROUP BY vd.producto_id
                ORDER BY total_vendido DESC
                LIMIT 5";
        $response['top_products'] = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if ($action === 'all' || $action === 'sales_chart') {
        $sql = "SELECT MONTH(fecha) AS mes, SUM(total) AS total_ventas
                FROM ventas 
                WHERE YEAR(fecha) = YEAR(CURDATE())
                GROUP BY MONTH(fecha)
                ORDER BY mes";
        $ventas_mes = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        $ventas_data = array_fill(1, 12, 0);
        foreach ($ventas_mes as $venta) {
            $ventas_data[(int)$venta['mes']] = (float)$venta['total_ventas'];
        }
        $response['sales_chart'] = array_values($ventas_data);
    }
    
    echo json_encode(['success' => true, 'data' => $response]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>