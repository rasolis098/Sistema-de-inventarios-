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
    
    // Obtener actividad reciente de la base de datos (últimos 10 minutos)
    $limite_tiempo = date('Y-m-d H:i:s', strtotime('-10 minutes'));
    
    $actividad_reciente = $pdo->query("
        (SELECT 'venta' as tipo, v.id as referencia, 
                CONCAT('Venta registrada #', v.id, ' - $', FORMAT(v.total, 2)) as descripcion, 
                c.nombre as usuario, v.fecha as fecha_creacion,
                DATE_FORMAT(v.fecha, '%H:%i') as hora
         FROM ventas v 
         INNER JOIN clientes c ON v.cliente_id = c.id 
         WHERE v.fecha >= '$limite_tiempo'
         ORDER BY v.fecha DESC LIMIT 5)
         
        UNION ALL
         
        (SELECT 'producto' as tipo, p.id as referencia, 
                CONCAT('Producto agregado: ', p.nombre) as descripcion,
                'Sistema' as usuario, p.created_at as fecha_creacion,
                DATE_FORMAT(p.created_at, '%H:%i') as hora
         FROM productos p 
         WHERE p.created_at >= '$limite_tiempo'
         ORDER BY p.created_at DESC LIMIT 5)
         
        UNION ALL
         
        (SELECT 'cliente' as tipo, c.id as referencia, 
                CONCAT('Cliente registrado: ', c.nombre) as descripcion,
                'Sistema' as usuario, c.created_at as fecha_creacion,
                DATE_FORMAT(c.created_at, '%H:%i') as hora
         FROM clientes c 
         WHERE c.created_at >= '$limite_tiempo'
         ORDER BY c.created_at DESC LIMIT 5)
         
        ORDER BY fecha_creacion DESC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Si no hay actividad reciente, obtener las últimas actividades generales
    if (empty($actividad_reciente)) {
        $actividad_reciente = $pdo->query("
            (SELECT 'venta' as tipo, v.id as referencia, 
                    CONCAT('Venta #', v.id, ' - $', FORMAT(v.total, 2)) as descripcion, 
                    c.nombre as usuario, v.fecha as fecha_creacion,
                    DATE_FORMAT(v.fecha, '%H:%i') as hora
             FROM ventas v 
             INNER JOIN clientes c ON v.cliente_id = c.id 
             ORDER BY v.fecha DESC LIMIT 3)
             
            UNION ALL
             
            (SELECT 'producto' as tipo, p.id as referencia, 
                    CONCAT('Producto: ', p.nombre) as descripcion,
                    'Sistema' as usuario, p.created_at as fecha_creacion,
                    DATE_FORMAT(p.created_at, '%H:%i') as hora
             FROM productos p 
             ORDER BY p.created_at DESC LIMIT 3)
             
            UNION ALL
             
            (SELECT 'cliente' as tipo, c.id as referencia, 
                    CONCAT('Cliente: ', c.nombre) as descripcion,
                    'Sistema' as usuario, c.created_at as fecha_creacion,
                    DATE_FORMAT(c.created_at, '%H:%i') as hora
             FROM clientes c 
             ORDER BY c.created_at DESC LIMIT 3)
             
            ORDER BY fecha_creacion DESC 
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $actividad_reciente,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>