<?php
require_once '../config/db.php';
header('Content-Type: application/json; charset=utf-8');

$term = isset($_GET['term']) ? trim($_GET['term']) : '';

if ($term !== '') {
    try {
        $stmt = $pdo->prepare("
            SELECT id, codigo, nombre, precio_venta 
            FROM productos 
            WHERE codigo LIKE ? OR nombre LIKE ?
            ORDER BY nombre 
            LIMIT 10
        ");
        $like = "%$term%";
        $stmt->execute([$like, $like]);
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];

        foreach ($productos as $p) {
            $result[] = [
                'label' => $p['codigo'] . ' - ' . $p['nombre'],
                'value' => $p['nombre'],
                'id' => $p['id'],
                'precio' => $p['precio_venta']
            ];
        }

        // Si no hay coincidencias, enviar producto de prueba
        if (empty($result)) {
            $result[] = [
                'label' => 'TEST001 - Producto de prueba',
                'value' => 'Producto de prueba',
                'id' => 9999,
                'precio' => 123.45
            ];
        }

        echo json_encode($result);

    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
