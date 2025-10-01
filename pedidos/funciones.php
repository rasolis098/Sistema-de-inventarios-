<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

function enviarCorreoPedidoPendiente($pdo, $pedido_id, $cliente_id, $fecha_entrega, $total) {
    // Obtener datos del cliente y pedido
    $stmt = $pdo->prepare("SELECT nombre, email FROM clientes WHERE id = ?");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch();
    $nombre_cliente = $cliente ? $cliente['nombre'] : 'Desconocido';
    $email_cliente = $cliente ? $cliente['email'] : null;

    if (!$email_cliente) {
        error_log("El cliente #$cliente_id no tiene email válido para enviar el pedido #$pedido_id.");
        // Puedes decidir si quieres continuar y enviar sólo a ventas o cancelar aquí
        // return false; 
    }

    $stmt = $pdo->prepare("SELECT fecha, observaciones FROM pedidos WHERE id = ?");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch();
    $fecha_creacion = $pedido ? $pedido['fecha'] : 'Desconocida';
    $observaciones = $pedido ? $pedido['observaciones'] : '';

    // Obtener detalle de productos
    // Obtener detalle de productos con precio, iva, descuento, importe
$stmt = $pdo->prepare("
    SELECT 
        pd.cantidad, 
        pd.precio_unitario,
        pd.iva,
        pd.descuento,
        pd.importe,
        p.nombre
    FROM pedido_detalles pd
    JOIN productos p ON pd.producto_id = p.id
    WHERE pd.pedido_id = ?
");
$stmt->execute([$pedido_id]);
$productos = $stmt->fetchAll();

// Construir tabla completa HTML
$tabla_productos = '<table style="width:100%; border-collapse:collapse; margin-top:10px; font-size:13px;">
    <thead>
        <tr style="background-color:#f1f1f1;">
            <th style="padding:8px; border:1px solid #ccc;">Producto</th>
            <th style="padding:8px; border:1px solid #ccc;">Cantidad</th>
            <th style="padding:8px; border:1px solid #ccc;">Precio Unitario</th>
            <th style="padding:8px; border:1px solid #ccc;">IVA (%)</th>
            <th style="padding:8px; border:1px solid #ccc;">Descuento (%)</th>
            <th style="padding:8px; border:1px solid #ccc;">Importe</th>
        </tr>
    </thead>
    <tbody>';
foreach ($productos as $prod) {
    $tabla_productos .= '<tr>
        <td style="padding:8px; border:1px solid #ccc;">' . htmlspecialchars($prod['nombre']) . '</td>
        <td style="padding:8px; border:1px solid #ccc; text-align:center;">' . $prod['cantidad'] . '</td>
        <td style="padding:8px; border:1px solid #ccc; text-align:right;">$' . number_format($prod['precio_unitario'], 2) . '</td>
        <td style="padding:8px; border:1px solid #ccc; text-align:right;">' . number_format($prod['iva'], 2) . '%</td>
        <td style="padding:8px; border:1px solid #ccc; text-align:right;">' . number_format($prod['descuento'], 2) . '%</td>
        <td style="padding:8px; border:1px solid #ccc; text-align:right;">$' . number_format($prod['importe'], 2) . '</td>
    </tr>';
}
$tabla_productos .= '</tbody></table>';

    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';

    try {
        // Configurar servidor SMTP
        $mail->isSMTP();
        $mail->Host = 'mail.dialexander.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'pedidos@dialexander.com';
        $mail->Password = 'Dialexander228.';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        // Remitente
        $mail->setFrom('ventas@dialexander.com', 'Distribuidora Industrial Alexander');

        // Destinatarios
        if ($email_cliente) {
            $mail->addAddress($email_cliente);       // Cliente recibe confirmación
        }
        $mail->addAddress('ventas@dialexander.com');  // Tú recibes copia interna

        // Asunto y contenido
        $mail->isHTML(true);
        $mail->Subject = "🔔 Pedido pendiente #$pedido_id";

        $mail->Body = '
<div style="font-family:Arial, sans-serif; max-width:700px; margin:0 auto; border:1px solid #ddd; border-radius:8px; overflow:hidden; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
    <div style="background-color:#0d6efd; color:white; padding:20px;">
        <h2 style="margin:0;">🔔 Nuevo Pedido Pendiente</h2>
    </div>
    <div style="padding:20px;">
        <p><strong>Número de pedido:</strong> ' . $pedido_id . '</p>
        <p><strong>Cliente:</strong> [' . $cliente_id . '] ' . htmlspecialchars($nombre_cliente) . '</p>
        <p><strong>Fecha de creación:</strong> ' . $fecha_creacion . '</p>
        <p><strong>Fecha de entrega:</strong> ' . $fecha_entrega . '</p>
        <p><strong>Total:</strong> $' . number_format($total, 2) . '</p>
        <p><strong>Observaciones:</strong> ' . (!empty($observaciones) ? nl2br(htmlspecialchars($observaciones)) : 'Sin comentarios') . '</p>

        <h3 style="margin-top:30px;">📦 Productos del pedido:</h3>
        ' . $tabla_productos . '

        <div style="margin-top:30px; text-align:center;">
            <a href="http://tusitio.com/pedidos/ver.php?id=' . $pedido_id . '" 
               style="background:#198754; color:white; padding:12px 20px; text-decoration:none; border-radius:6px; font-weight:bold;">
                👉 Ver pedido en sistema
            </a>
        </div>
    </div>
    <div style="background-color:#f8f9fa; color:#666; font-size:12px; padding:15px; text-align:center;">
        Este correo fue generado automáticamente por el sistema de pedidos de Distribuidora Industrial ALexander, no es necesario una respuesta.
    </div>
</div>
';

        // ➕ Adjuntar PDF generado desde pedido_pdf.php
        $pdf_url = "http://tusitio.com/pedidos/pedido_pdf.php?id=" . $pedido_id;
        $pdf_temp = sys_get_temp_dir() . "/pedido_{$pedido_id}.pdf";

        $pdf_content = @file_get_contents($pdf_url);
        if ($pdf_content !== false) {
            file_put_contents($pdf_temp, $pdf_content);
            $mail->addAttachment($pdf_temp, "Pedido_{$pedido_id}.pdf");
        } else {
            error_log("No se pudo descargar el PDF para pedido #$pedido_id desde $pdf_url");
        }

        // Debug SMTP para depuración
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            error_log("SMTP Debug level $level; message: $str");
        };

        // Enviar correo
        $mail->send();

        // Eliminar PDF temporal si fue creado
        if (file_exists($pdf_temp)) {
            unlink($pdf_temp);
        }

        // Registrar intento exitoso
        $sql = "UPDATE pedidos 
                SET correo_enviado = 1, 
                    fecha_ultimo_intento = NOW(), 
                    intentos_envio = intentos_envio + 1 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$pedido_id]);

        return true;
    } catch (Exception $e) {
        // Registrar fallo de intento
        $sql = "UPDATE pedidos 
                SET fecha_ultimo_intento = NOW(), 
                    intentos_envio = intentos_envio + 1 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$pedido_id]);

        error_log("❌ Error al enviar correo del pedido #$pedido_id: " . $mail->ErrorInfo);
        error_log("Exception message: " . $e->getMessage());
        return false;
    }
}
