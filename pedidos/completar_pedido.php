<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';

// Log para depuración
error_log("=== INICIANDO completar_pedido.php ===");
error_log("GET id: " . ($_GET['id'] ?? 'No recibido'));

// Verificar que se recibe el ID del pedido
$pedido_id = $_GET['id'] ?? null;

if (!$pedido_id) {
    $_SESSION['error'] = "ID de pedido no válido";
    error_log("ERROR: ID de pedido no válido");
    header("Location: index.php");
    exit;
}

error_log("Procesando pedido ID: $pedido_id");

try {
    // 1. Verificar conexión a la base de datos
    if (!$pdo) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    // 2. Obtener datos básicos del pedido (versión simplificada primero)
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("Datos del pedido: " . print_r($pedido, true));
    
    if (!$pedido) {
        $_SESSION['error'] = "Pedido no encontrado";
        error_log("ERROR: Pedido no encontrado");
        header("Location: index.php");
        exit;
    }
    
    // 3. Verificar que el pedido no esté ya completado
    if ($pedido['estado'] === 'completado') {
        $_SESSION['error'] = "El pedido ya ha sido completado anteriormente";
        error_log("ERROR: Pedido ya completado");
        header("Location: ver_pedido.php?id=" . $pedido_id);
        exit;
    }
    
    // 4. Obtener usuario_id de sesión (versión simplificada)
    $usuario_id = $_SESSION['usuario_id'] ?? 1;
    $empleado_id = $_SESSION['empleado_id'] ?? 1;
    
    error_log("Usuario ID: $usuario_id, Empleado ID: $empleado_id");
    
    // 5. Iniciar transacción
    $pdo->beginTransaction();
    error_log("Transacción iniciada");
    
    // 6. Generar número de factura simple
    $numero_factura = 'F-' . date('Ymd') . '-' . str_pad($pedido_id, 4, '0', STR_PAD_LEFT);
    error_log("Número de factura: $numero_factura");
    
    // 7. Insertar venta (versión simplificada - solo campos esenciales)
    $stmt_venta = $pdo->prepare("
        INSERT INTO ventas (
            cliente_id,
            almacen_origen_id,
            fecha,
            total,
            fecha_venta,
            facturacion,
            flete_pagado,
            tipo_pago,
            numero_factura,
            empleado_id,
            usuario_id
        ) VALUES (?, ?, NOW(), ?, NOW(), 'Facturado', 0, 'Efectivo', ?, ?, ?)
    ");
    
    $result_venta = $stmt_venta->execute([
        $pedido['cliente_id'],
        1, // almacen_origen_id por defecto
        $pedido['total'],
        $numero_factura,
        $empleado_id,
        $usuario_id
    ]);
    
    if (!$result_venta) {
        throw new Exception("Error al insertar venta");
    }
    
    $venta_id = $pdo->lastInsertId();
    error_log("Venta creada ID: $venta_id");
    
    // 8. Obtener items del pedido (si existe la tabla)
    try {
        $stmt_items = $pdo->prepare("SELECT * FROM pedidos_items WHERE pedido_id = ?");
        $stmt_items->execute([$pedido_id]);
        $items_pedido = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Items encontrados: " . count($items_pedido));
        
        // 9. Insertar en ventas_detalles si hay items
        if (count($items_pedido) > 0) {
            $stmt_detalle = $pdo->prepare("
                INSERT INTO ventas_detalles (
                    venta_id,
                    producto_id,
                    cantidad,
                    precio_unitario,
                    costo_unitario,
                    costo_total,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            foreach ($items_pedido as $item) {
                $costo_unitario = $item['precio'] * 0.7; // Costo estimado
                $costo_total = $item['cantidad'] * $costo_unitario;
                
                $result_detalle = $stmt_detalle->execute([
                    $venta_id,
                    $item['producto_id'],
                    $item['cantidad'],
                    $item['precio'],
                    $costo_unitario,
                    $costo_total
                ]);
                
                if (!$result_detalle) {
                    throw new Exception("Error al insertar detalle de venta");
                }
                
                // 10. Actualizar stock (si existe la tabla productos)
                try {
                    $stmt_stock = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
                    $stmt_stock->execute([$item['cantidad'], $item['producto_id']]);
                    error_log("Stock actualizado para producto ID: " . $item['producto_id']);
                } catch (Exception $e) {
                    error_log("Advertencia: No se pudo actualizar stock - " . $e->getMessage());
                    // Continuar aunque falle la actualización de stock
                }
            }
        }
    } catch (Exception $e) {
        error_log("Advertencia en items: " . $e->getMessage());
        // Continuar aunque falle la parte de items
    }
    
    // 11. Actualizar estado del pedido
    $stmt_actualizar = $pdo->prepare("UPDATE pedidos SET estado = 'completado' WHERE id = ?");
    $result_actualizar = $stmt_actualizar->execute([$pedido_id]);
    
    if (!$result_actualizar) {
        throw new Exception("Error al actualizar estado del pedido");
    }
    
    error_log("Estado del pedido actualizado a 'completado'");
    
    // 12. Confirmar transacción
    $pdo->commit();
    error_log("Transacción confirmada");
    
    $_SESSION['mensaje'] = "✅ Pedido #$pedido_id convertido a venta exitosamente. Venta #$venta_id creada.";
    error_log("Proceso completado exitosamente");
    
    // 13. Redirigir
    header("Location: ../ventas/index.php");
    exit;
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        error_log("Transacción revertida por error");
    }
    
    $error_message = "❌ Error al completar el pedido: " . $e->getMessage();
    $_SESSION['error'] = $error_message;
    error_log("ERROR: " . $error_message);
    error_log("Trace: " . $e->getTraceAsString());
    
    header("Location: ver_pedido.php?id=" . $pedido_id);
    exit;
}
?>