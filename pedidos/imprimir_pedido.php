<?php
require '../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

include '../config/db.php';

$id = $_GET['id'] ?? 0;
if (!$id || !is_numeric($id)) {
    die("ID de pedido inválido.");
}

// Obtener datos del pedido + cliente
$stmt = $pdo->prepare("
    SELECT p.*, c.nombre AS cliente_nombre, c.rfc, c.direccion, c.codigo
    FROM pedidos p
    JOIN clientes c ON p.cliente_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$pedido) {
    die("Pedido no encontrado.");
}

// Obtener detalles del pedido + productos
$stmt = $pdo->prepare("
    SELECT pd.*, pr.nombre, pr.codigo, pr.descripcion
    FROM pedido_detalles pd
    JOIN productos pr ON pd.producto_id = pr.id
    WHERE pd.pedido_id = ?
");
$stmt->execute([$id]);
$detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Variables para totales
$total_base = 0;
$total_iva = 0;
$total_imp = 0;
$iva_default = 0.16;

$fecha = date('d/m/Y', strtotime($pedido['fecha']));
$logo = 'https://inv.dialexander.com/img/logodia.png'; // Ajusta el logo si quieres

$html = "
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; margin: 0; padding: 0; }
.wrapper { padding: 20px 30px; }
.col { display: inline-block; width: 49%; vertical-align: top; margin-bottom: 10px; }
.box { padding: 10px; min-height: 110px; box-sizing: border-box; }
.box-title { font-weight: bold; margin-bottom: 5px; color: #000; font-size: 13px; }
img.logo { height: 60px; }
.table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 11px; }
.table th { background-color: #f5f5f5; color: #000; padding: 6px; text-align: left; border-bottom: 1px solid #ddd; }
.table td { padding: 6px; border-bottom: 1px solid #ddd; vertical-align: top; }
.observaciones { margin-top: 15px; border: 1px dashed #ccc; padding: 10px; font-size: 11px; }
.etiqueta { text-align: right; font-size: 10px; margin-top: 5px; color: #333; }
.condiciones { font-size: 10px; color: #555; position: absolute; bottom: 30px; left: 30px; right: 30px; line-height: 1.4; }
.firma { position: absolute; right: 30px; bottom: 100px; width: 200px; border: 1px solid #000; padding: 10px; font-size: 11px; text-align: center; }
.totales { text-align: right; margin-top: 15px; font-size: 12px; line-height: 1.6; margin-bottom: 120px; }
.total-box { background-color: #444; color: white; padding: 5px 10px; border-radius: 3px; display: inline-block; font-weight: bold; margin-top: 5px; }
.page-break { page-break-after: always; }
</style>

<div class='wrapper'>
    <div class='col' style='float: left;'>
        <div class='box' style='border: none; text-align: left;'>
            <img src='$logo' class='logo'>
        </div>
    </div>
    <div class='col' style='float: right;'>
        <div class='box' style='border: none; text-align: left;'>
            <div class='box-title' style='color: #800000;'>PEDIDO</div>
            No. {$pedido['id']}<br>
            Código Cliente: " . htmlspecialchars($pedido['codigo']) . "<br>
            Fecha: $fecha<br>
            Estado: " . ucfirst($pedido['estado']) . "
        </div>
    </div>
    <div style='clear: both;'></div>

    <div class='col'>
        <div class='box' style='border: 1px solid #ccc;'>
            <div class='box-title'>Emisor</div>
            Distribuidora Industrial Alexander<br>
            Anáhuac 228, Col. Chapultepec<br>
            San Nicolás de los Garza, N.L.<br>
            Tel: 8181796144<br>
            Email: ventas@dialexander.com
        </div>
    </div>
    <div class='col'>
        <div class='box' style='border: 1px solid #ccc;'>
            <div class='box-title'>Cliente</div>";

if (!empty($pedido['cliente_nombre'])) $html .= htmlspecialchars($pedido['cliente_nombre']) . "<br>";
if (!empty($pedido['rfc']))          $html .= "RFC: " . htmlspecialchars($pedido['rfc']) . "<br>";
if (!empty($pedido['direccion']))    $html .= nl2br(htmlspecialchars($pedido['direccion'])) . "<br>";

$html .= "
        </div>
    </div>
    <div style='clear: both;'></div>";

// Observaciones
$obs = !empty($pedido['observaciones']) ? nl2br(htmlspecialchars($pedido['observaciones'])) : 'Sin observaciones.';
$html .= "
<div class='observaciones'>
    <strong>Observaciones:</strong><br>$obs
</div>";

// Tabla productos
$html .= "
<table class='table'>
<thead>
<tr>
    <th>Descripción</th>
    <th>Cantidad</th>
    <th>Precio Unitario</th>
    <th>IVA (%)</th>
    <th>Importe Base</th>
</tr>
</thead>
<tbody>";

foreach ($detalles as $d) {
    $descripcion = htmlspecialchars($d['codigo'] . ' - ' . $d['nombre']);
    if (!empty($d['descripcion'])) {
        $descripcion .= "<br>" . nl2br(htmlspecialchars($d['descripcion']));
    }
    $cantidad = $d['cantidad'];
    $pu = $d['precio_unitario'];
    $iva_rate = isset($d['iva']) ? floatval($d['iva']) : $iva_default;

    // Detectar formato IVA para mostrar
    if ($iva_rate > 1) {
        $iva_display = $iva_rate;
        $iva_decimal = $iva_rate / 100;
    } else {
        $iva_display = $iva_rate * 100;
        $iva_decimal = $iva_rate;
    }

    $base = $cantidad * $pu;
    $iva_total = $base * $iva_decimal;
    $importe = $base + $iva_total;

    $total_base += $base;
    $total_iva += $iva_total;
    $total_imp += $importe;

    $html .= "<tr>
        <td>$descripcion</td>
        <td>$cantidad</td>
        <td>$" . number_format($pu, 2, '.', ',') . "</td>
        <td>" . number_format($iva_display, 0) . "%</td>
        <td>$" . number_format($base, 2, '.', ',') . "</td>
    </tr>";
}


$html .= "</tbody></table>";

// Totales
$html .= "
<div class='totales'>
    <strong>Total (Base Imponible):</strong> $ " . number_format($total_base, 2, '.', ',') . "<br>
    <strong>Total IVA:</strong> $ " . number_format($total_iva, 2, '.', ',') . "<br>
    <div class='total-box'>
        Total $ " . number_format($total_imp, 2, '.', ',') . "
    </div>
</div>

<div class='firma'>
    Aceptación por escrito, sello de la empresa, fecha y firma
</div>

<div class='condiciones'>
<strong>TÉRMINOS Y CONDICIONES.</strong><br>
NO INCLUYE SERVICIO DE TRANSPORTE NI MANIOBRAS<br>
FAVOR DE CONSULTAR TIEMPO DE ENTREGA CON SU VENDEDOR<br>
CONFIRMAR PEDIDO CON ORDEN DE COMPRA<br>
ESTE PEDIDO ESTÁ SUJETO A VARIACIÓN, SIN PREVIO AVISO<br>
FORMA DE PAGO: CONTADO<br>
TODA CANCELACIÓN GENERA UNA PENALIZACIÓN DEL 20%<br>
<em>CUALQUIER CONSULTA, COORDINAR CON SU AGENTE DE VENTAS</em>
</div>

</div>
";

// Generar PDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Descargar el PDF con nombre "pedido_X.pdf"
$dompdf->stream("pedido_{$id}.pdf", ["Attachment" => true]);
