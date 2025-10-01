<?php
require '../config/db.php';
require '../vendor/autoload.php';

use Dompdf\Dompdf;

$conteo_id = $_GET['id'] ?? null;
$almacen_id = $_GET['almacen_id'] ?? '';
if (!$conteo_id) die("Falta el ID del conteo");

if ($almacen_id === '') {
  $sql = "SELECT p.codigo, p.nombre,
                 SUM(i.stock_sistema) AS stock_sistema,
                 SUM(i.stock_fisico)  AS stock_fisico,
                 SUM(i.diferencia)    AS diferencia
          FROM inventario_fisico i
          JOIN productos p ON i.producto_id=p.id
          WHERE i.conteo_id=?
          GROUP BY p.id, p.codigo, p.nombre
          ORDER BY p.nombre";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$conteo_id]);
} else {
  $sql = "SELECT p.codigo, p.nombre, a.nombre AS almacen,
                 i.stock_sistema, i.stock_fisico, i.diferencia
          FROM inventario_fisico i
          JOIN productos p ON i.producto_id=p.id
          JOIN almacenes a ON i.almacen_id=a.id
          WHERE i.conteo_id=? AND i.almacen_id=?
          ORDER BY p.nombre";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$conteo_id, $almacen_id]);
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Encabezado
$h = "<h2 style='text-align:center;margin:0 0 10px;'>Reporte Conteo #{$conteo_id}</h2>";
$h .= ($almacen_id==='') ? "<p style='text-align:center;margin:0 0 15px;'>Conteo General (todos los almacenes)</p>"
                         : "<p style='text-align:center;margin:0 0 15px;'>Almacén filtrado</p>";

$html = $h;
$html .= "<table border='1' cellspacing='0' cellpadding='5' width='100%'>
<thead><tr>
<th>Código</th><th>Producto</th>";
if ($almacen_id!=='') $html.="<th>Almacén</th>";
$html.="<th>Stock Sistema</th><th>Stock Físico</th><th>Diferencia</th>
</tr></thead><tbody>";

foreach ($rows as $r) {
  $html .= "<tr>
    <td>{$r['codigo']}</td>
    <td>{$r['nombre']}</td>";
  if ($almacen_id!=='') $html.="<td>{$r['almacen']}</td>";
  $html .= "<td>{$r['stock_sistema']}</td>
            <td>{$r['stock_fisico']}</td>
            <td>{$r['diferencia']}</td>
  </tr>";
}
$html .= "</tbody></table>";

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4','landscape');
$dompdf->render();
$dompdf->stream("conteo_{$conteo_id}.pdf", ["Attachment"=>true]);
