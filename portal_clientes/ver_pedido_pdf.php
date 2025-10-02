<?php 
require '../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

include '../config/db.php';

$id = $_GET['id'] ?? 0;
$identificador = $_GET['identificador'] ?? null;

if (!$id || !$identificador) {
    die('Acceso inválido: faltan parámetros');
}

// Buscar cliente por email o código
if (filter_var($identificador, FILTER_VALIDATE_EMAIL)) {
    $sql = "SELECT * FROM clientes WHERE email = ?";
} else {
    $sql = "SELECT * FROM clientes WHERE codigo = ?";
}
$stmt = $pdo->prepare($sql);
$stmt->execute([$identificador]);
$cliente = $stmt->fetch();

if (!$cliente) {
    die('Cliente no encontrado');
}

// Validar que el pedido pertenece al cliente
$sql = "SELECT * FROM pedidos WHERE id = ? AND cliente_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id, $cliente['id']]);
$pedido = $stmt->fetch();

if (!$pedido) {
    die('Acceso inválido: este pedido no pertenece a este cliente');
}

// Obtener detalles del pedido (productos)
$sql = "SELECT pd.*, p.nombre, p.codigo, p.descripcion FROM pedido_detalles pd 
        JOIN productos p ON pd.producto_id = p.id 
        WHERE pd.pedido_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$detalles = $stmt->fetchAll();

// Fechas formateadas
$fecha = date('d/m/Y', strtotime($pedido['fecha']));
$fecha_entrega = date('d/m/Y', strtotime($pedido['fecha_entrega']));
$logo = 'https://inv.dialexander.com/img/logodia.png';

// Estado con colores (ajusta según tus estados)
$estado_texto = strtoupper($pedido['estado']);
$estado_color = match (strtolower($pedido['estado'])) {
    'aprobado' => '#28a745',
    'pendiente' => '#ffc107',
    'rechazado', 'cancelado' => '#dc3545',
    default => '#6c757d',
};

$html = "
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; margin: 0; padding: 0; }
.wrapper { padding: 20px 30px; position: relative; min-height: 100vh; }
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
            Código Cliente: " . htmlspecialchars($cliente['codigo']) . "<br>
            Fecha: $fecha<br>
            Fecha Entrega: $fecha_entrega<br>
            Estado: <span style='background-color: $estado_color; color: white; padding: 2px 5px; border-radius: 4px;'>$estado_texto</span>
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
if (!empty($cliente['nombre'])) $html .= htmlspecialchars($cliente['nombre']) . "<br>";
if (!empty($cliente['rfc']))     $html .= "RFC: " . htmlspecialchars($cliente['rfc']) . "<br>";
if (!empty($cliente['direccion'])) $html .= nl2br(htmlspecialchars($cliente['direccion'])) . "<br>";
$html .= "
        </div>
    </div>
    <div style='clear: both;'></div>";

// Observaciones
$obs = !empty($pedido['observaciones']) ? nl2br(htmlspecialchars($pedido['observaciones'])) : 'Sin observaciones.';
$html .= "
<div class='observaciones'>
    <strong>Observaciones:</strong><br>$obs
</div>
<div class='etiqueta'>
    <strong>Importes visualizados en PESOS MEXICANOS</strong>
</div>";

// Tabla productos
$html .= "
<table class='table'>
<thead>
<tr>
    <th>Producto</th>
    <th>Cantidad</th>
    <th>Precio Unitario</th>
    <th>IVA (%)</th>
    <th>Descuento (%)</th>
    <th>Importe</th>
</tr>
</thead>
<tbody>";

$total_base = 0;
$total_iva = 0;
$total_descuento = 0;
$total_importe = 0;

foreach ($detalles as $d) {
    $descripcion = htmlspecialchars($d['codigo'] . ' - ' . $d['nombre']);
    if (!empty($d['descripcion'])) {
        $descripcion .= "<br>" . nl2br(htmlspecialchars($d['descripcion']));
    }
    $cantidad = $d['cantidad'];
    $precio_unitario = $d['precio_unitario'];
    $iva_rate = floatval($d['iva']);
    // Ajustar IVA si está en decimal (ej. 0.16)
    if ($iva_rate < 1) $iva_rate = $iva_rate * 100;

    $descuento = floatval($d['descuento']);
    
    // Base sin IVA y sin descuento
    $base = $cantidad * $precio_unitario;
    // Descuento en cantidad monetaria
    $monto_descuento = ($base * $descuento) / 100;
    // Base menos descuento
    $base_neto = $base - $monto_descuento;
    // IVA aplicado sobre base neta
    $iva_monto = $base_neto * ($iva_rate / 100);
    // Importe total
    $importe = $base_neto + $iva_monto;

    $total_base += $base_neto;
    $total_iva += $iva_monto;
    $total_descuento += $monto_descuento;
    $total_importe += $importe;

    $html .= "<tr>
        <td>$descripcion</td>
        <td align='center'>$cantidad</td>
        <td align='right'>$" . number_format($precio_unitario, 2, '.', ',') . "</td>
        <td align='right'>" . number_format($iva_rate, 2) . "%</td>
        <td align='right'>" . number_format($descuento, 2) . "%</td>
        <td align='right'>$" . number_format($importe, 2, '.', ',') . "</td>
    </tr>";
}

$html .= "</tbody></table>";

// Totales
$html .= "
<div class='totales'>
    <strong>Total base (sin IVA):</strong> $ " . number_format($total_base, 2, '.', ',') . "<br>
    <strong>Total descuento:</strong> $ " . number_format($total_descuento, 2, '.', ',') . "<br>
    <strong>Total IVA:</strong> $ " . number_format($total_iva, 2, '.', ',') . "<br>
    <div class='total-box'>
        Total $ " . number_format($total_importe, 2, '.', ',') . "
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
ESTA COTIZACION ESTA SUJETA A VARIACIÓN, SIN PREVIO AVISO<br>
FORMA DE PAGO: CONTADO<br>
TODA CANCELACION GENERA UNA PENALIZACION DEL 20%<br>
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
$dompdf->stream("pedido_{$id}.pdf", ['Attachment' => true]);
exit;
