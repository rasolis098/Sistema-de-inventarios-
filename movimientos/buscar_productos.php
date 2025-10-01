<?php
require '../config/db.php';

$term = $_GET['term'] ?? '';

$stmt = $pdo->prepare("SELECT id, nombre, codigo FROM productos WHERE activo = 1 AND (nombre LIKE ? OR codigo LIKE ?)");
$stmt->execute(["%$term%", "%$term%"]);
$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sugerencias = [];
foreach ($resultados as $producto) {
    $sugerencias[] = [
        'label' => $producto['codigo'] . " - " . $producto['nombre'],
        'value' => $producto['codigo'] . " - " . $producto['nombre'],
        'id' => $producto['id']
    ];
}

echo json_encode($sugerencias);
