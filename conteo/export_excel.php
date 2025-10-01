<?php
require '../config/db.php';
require '../vendor/autoload.php'; // Asegúrate de tener PhpSpreadsheet instalado

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Obtener parámetros
$conteo_id = isset($_GET['id']) ? intval($_GET['id']) : null;
$almacen_id = isset($_GET['almacen_id']) ? intval($_GET['almacen_id']) : null;

if (!$conteo_id) {
    die("Falta el ID del conteo");
}

// Crear nuevo spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Conteo $conteo_id");

$row_num = 1; // Fila inicial

if (!$almacen_id) {
    // Cabecera
    $sheet->setCellValue("A$row_num", 'Código')
          ->setCellValue("B$row_num", 'Producto')
          ->setCellValue("C$row_num", 'Stock Sistema')
          ->setCellValue("D$row_num", 'Stock Físico')
          ->setCellValue("E$row_num", 'Diferencia');
    $row_num++;

    $sql = "SELECT 
                p.codigo, 
                p.nombre,
                SUM(i.stock_sistema) AS stock_sistema,
                SUM(i.stock_fisico) AS stock_fisico,
                SUM(i.diferencia) AS diferencia
            FROM inventario_fisico i
            JOIN productos p ON i.producto_id = p.id
            WHERE i.conteo_id = ?
            GROUP BY p.id, p.codigo, p.nombre
            ORDER BY p.nombre";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$conteo_id]);
} else {
    // Cabecera con almacén
    $sheet->setCellValue("A$row_num", 'Código')
          ->setCellValue("B$row_num", 'Producto')
          ->setCellValue("C$row_num", 'Almacén')
          ->setCellValue("D$row_num", 'Stock Sistema')
          ->setCellValue("E$row_num", 'Stock Físico')
          ->setCellValue("F$row_num", 'Diferencia');
    $row_num++;

    $sql = "SELECT 
                p.codigo, 
                p.nombre, 
                a.nombre AS almacen,
                i.stock_sistema, 
                i.stock_fisico, 
                i.diferencia
            FROM inventario_fisico i
            JOIN productos p ON i.producto_id = p.id
            JOIN almacenes a ON i.almacen_id = a.id
            WHERE i.conteo_id = ? AND i.almacen_id = ?
            ORDER BY p.nombre";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$conteo_id, $almacen_id]);
}

// Llenar datos
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $col = 'A';
    foreach ($row as $cell) {
        $sheet->setCellValue($col . $row_num, $cell);
        $col++;
    }
    $row_num++;
}

// Generar archivo Excel
$filename = "conteo_{$conteo_id}.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
