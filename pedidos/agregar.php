<?php
session_start(); // <-- AÑADIR ESTA LÍNEA AL INICIO
require_once '../config/db.php';
include '../includes/header.php';

// Obtener clientes
$clientes = $pdo->query("SELECT id, nombre FROM clientes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Pedido - Sistema de Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #17a2b8;
            --light-bg: #f8f9fa;
            --card-border: 1px solid #e2e8f0;
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px 0;
            color: #444;
        }
        
        .page-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 25px;
            border: var(--card-border);
        }
        
        .form-container {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 30px;
            border: var(--card-border);
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 1px solid #eaeef2;
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .section-title {
            color: var(--secondary-color);
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 25px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--primary-color);
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 12px;
            color: var(--primary-color);
            background: rgba(52, 152, 219, 0.1);
            width: 40px;
            height: 40px;
            line-height: 40px;
            text-align: center;
            border-radius: 10px;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 8px;
        }
        
        .required-field::after {
            content: " *";
            color: var(--danger-color);
        }
        
        .form-control, .form-select, .form-textarea {
            border: 1px solid #d1d9e6;
            border-radius: 8px;
            padding: 12px 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .input-group-icon {
            position: relative;
        }
        
        .input-group-icon .form-control {
            padding-left: 45px;
        }
        
        .input-group-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            z-index: 5;
        }
        
        .producto-row {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border: var(--card-border);
            transition: all 0.3s ease;
        }
        
        .producto-row:hover {
            background: #f1f3f5;
            transform: translateY(-2px);
        }
        
        .remove-producto {
            color: var(--danger-color);
            background: rgba(231, 76, 60, 0.1);
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .remove-producto:hover {
            background: rgba(231, 76, 60, 0.2);
            transform: scale(1.1);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 8px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            border-radius: 8px;
            padding: 12px 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
        }
        
        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
        }
        
        .btn-success:hover {
            background-color: #219653;
            border-color: #219653;
        }
        
        .summary-card {
            background: rgba(52, 152, 219, 0.05);
            border: 1px dashed var(--primary-color);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eaeef2;
        }
        
        .summary-item:last-child {
            border-bottom: none;
        }
        
        .summary-total {
            font-weight: 700;
            color: var(--secondary-color);
            font-size: 1.2rem;
        }
        
        .importe-display {
            font-weight: 600;
            color: var(--success-color);
            font-size: 1.1rem;
            padding: 10px;
            background: rgba(39, 174, 96, 0.1);
            border-radius: 6px;
            text-align: center;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #eaeef2;
        }
        
        .ui-autocomplete {
            max-height: 200px;
            overflow-y: auto;
            overflow-x: hidden;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: var(--card-border);
        }
        
        .ui-menu-item {
            padding: 10px 15px;
            border-bottom: 1px solid #eaeef2;
            font-size: 0.9rem;
        }
        
        .ui-menu-item:last-child {
            border-bottom: none;
        }
        
        .ui-state-active {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }
        
        @media (max-width: 768px) {
            .form-container {
                padding: 20px 15px;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .producto-row {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h3 fw-bold"><i class="fas fa-plus-circle me-2 text-primary"></i>Nuevo Pedido</h1>
                    <p class="mb-0 text-muted">Complete la información para crear un nuevo pedido</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver a Pedidos
                    </a>
                </div>
            </div>
        </div>

        <form action="guardar_pedido.php" method="POST" id="formPedido">
            <div class="form-container">
                <!-- Información del Cliente -->
                <div class="form-section">
                    <h3 class="section-title"><i class="fas fa-user"></i>Información del Cliente</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label required-field">Cliente</label>
                            <div class="input-group-icon">
                                <i class="fas fa-users"></i>
                                <select name="cliente_id" class="form-select" required>
                                    <option value="">Seleccione un cliente</option>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <option value="<?= $cliente['id'] ?>"><?= htmlspecialchars($cliente['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label class="form-label required-field">Fecha de entrega</label>
                            <div class="input-group-icon">
                                <i class="fas fa-calendar"></i>
                                <input type="date" name="fecha_entrega" class="form-control" required />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Productos del Pedido -->
                <div class="form-section">
                    <h3 class="section-title"><i class="fas fa-boxes"></i>Productos del Pedido</h3>
                    
                    <div id="productos-container">
                        <div class="producto-row" data-index="0">
                            <div class="row align-items-end">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label required-field">Producto</label>
                                    <input type="hidden" name="productos[0][id]" class="producto-id">
                                    <input type="text" name="productos[0][nombre]" class="form-control producto-autocomplete" 
                                           placeholder="Escribe código o nombre del producto" required>
                                </div>
                                
                                <div class="col-md-1 mb-3">
                                    <label class="form-label required-field">Cant.</label>
                                    <input type="number" name="productos[0][cantidad]" class="form-control cantidad" 
                                           min="1" value="1" onchange="calcularImporte(this)" required>
                                </div>
                                
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Precio Unitario</label>
                                    <input type="number" step="0.01" name="productos[0][precio]" class="form-control precio" 
                                           onchange="calcularImporte(this)" readonly>
                                </div>
                                
                                <div class="col-md-1 mb-3">
                                    <label class="form-label">IVA (%)</label>
                                    <input type="number" name="productos[0][iva]" class="form-control iva" 
                                           value="16" onchange="calcularImporte(this)" min="0" max="100">
                                </div>
                                
                                <div class="col-md-1 mb-3">
                                    <label class="form-label">Desc. (%)</label>
                                    <input type="number" name="productos[0][descuento]" class="form-control descuento" 
                                           value="0" onchange="calcularImporte(this)" min="0" max="100">
                                </div>
                                
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Importe</label>
                                    <div class="importe-display" id="importe-0">$0.00</div>
                                    <input type="hidden" name="productos[0][importe]" class="importe-hidden">
                                </div>
                                
                                <div class="col-md-1 mb-3 text-end">
                                    <div class="remove-producto" onclick="eliminarProducto(0)" title="Eliminar producto">
                                        <i class="fas fa-times"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-success mt-3" onclick="agregarProducto()">
                        <i class="fas fa-plus me-2"></i>Agregar Producto
                    </button>
                </div>

                <!-- Resumen del Pedido -->
                <div class="form-section">
                    <h3 class="section-title"><i class="fas fa-calculator"></i>Resumen del Pedido</h3>
                    
                    <div class="summary-card">
                        <div class="summary-item">
                            <span>Subtotal:</span>
                            <span id="subtotal-display">$0.00</span>
                        </div>
                        <div class="summary-item">
                            <span>IVA Total:</span>
                            <span id="iva-display">$0.00</span>
                        </div>
                        <div class="summary-item">
                            <span>Descuento Total:</span>
                            <span id="descuento-display" class="text-success">-$0.00</span>
                        </div>
                        <div class="summary-item summary-total">
                            <span>TOTAL DEL PEDIDO:</span>
                            <span id="total-display">$0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="form-section">
                    <h3 class="section-title"><i class="fas fa-sticky-note"></i>Observaciones</h3>
                    
                    <div class="row">
                        <div class="col-12 mb-4">
                            <label class="form-label">Información adicional</label>
                            <textarea name="observaciones" class="form-control" rows="4" 
                                      placeholder="Agregue cualquier observación o instrucción especial para este pedido..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="action-buttons">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Guardar Pedido
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let contadorProductos = 1;
    let productosData = {0: {cantidad: 1, precio: 0, iva: 16, descuento: 0, importe: 0}};

    function inicializarAutocomplete() {
        $(".producto-autocomplete").autocomplete({
            source: 'buscar_productos.php',
            minLength: 2,
            select: function(event, ui) {
                const row = $(this).closest(".producto-row");
                const index = row.data("index");
                
                row.find(".producto-id").val(ui.item.id);
                row.find(".precio").val(parseFloat(ui.item.precio).toFixed(2));
                
                // Actualizar datos
                productosData[index].precio = parseFloat(ui.item.precio);
                calcularImporte(row);
            }
        });
    }

    function agregarProducto() {
        const container = $("#productos-container");
        const newIndex = contadorProductos++;
        
        const productRow = document.createElement("div");
        productRow.className = "producto-row";
        productRow.setAttribute("data-index", newIndex);
        productRow.innerHTML = `
            <div class="row align-items-end">
                <div class="col-md-4 mb-3">
                    <label class="form-label required-field">Producto</label>
                    <input type="hidden" name="productos[${newIndex}][id]" class="producto-id">
                    <input type="text" name="productos[${newIndex}][nombre]" class="form-control producto-autocomplete" 
                           placeholder="Escribe código o nombre del producto" required>
                </div>
                
                <div class="col-md-1 mb-3">
                    <label class="form-label required-field">Cant.</label>
                    <input type="number" name="productos[${newIndex}][cantidad]" class="form-control cantidad" 
                           min="1" value="1" onchange="calcularImporte(this)" required>
                </div>
                
                <div class="col-md-2 mb-3">
                    <label class="form-label">Precio Unitario</label>
                    <input type="number" step="0.01" name="productos[${newIndex}][precio]" class="form-control precio" 
                           onchange="calcularImporte(this)" readonly>
                </div>
                
                <div class="col-md-1 mb-3">
                    <label class="form-label">IVA (%)</label>
                    <input type="number" name="productos[${newIndex}][iva]" class="form-control iva" 
                           value="16" onchange="calcularImporte(this)" min="0" max="100">
                </div>
                
                <div class="col-md-1 mb-3">
                    <label class="form-label">Desc. (%)</label>
                    <input type="number" name="productos[${newIndex}][descuento]" class="form-control descuento" 
                           value="0" onchange="calcularImporte(this)" min="0" max="100">
                </div>
                
                <div class="col-md-2 mb-3">
                    <label class="form-label">Importe</label>
                    <div class="importe-display" id="importe-${newIndex}">$0.00</div>
                    <input type="hidden" name="productos[${newIndex}][importe]" class="importe-hidden">
                </div>
                
                <div class="col-md-1 mb-3 text-end">
                    <div class="remove-producto" onclick="eliminarProducto(${newIndex})" title="Eliminar producto">
                        <i class="fas fa-times"></i>
                    </div>
                </div>
            </div>
        `;

        container.append(productRow);
        productosData[newIndex] = {cantidad: 1, precio: 0, iva: 16, descuento: 0, importe: 0};
        inicializarAutocomplete();
    }

    function eliminarProducto(index) {
        const row = $(`.producto-row[data-index="${index}"]`);
        if (row && Object.keys(productosData).length > 1) {
            row.remove();
            delete productosData[index];
            calcularTotales();
        } else {
            alert('Debe haber al menos un producto en el pedido.');
        }
    }

    function calcularImporte(element) {
        const row = $(element).closest(".producto-row");
        const index = row.data("index");
        
        const cantidad = parseFloat(row.find(".cantidad").val()) || 0;
        const precio = parseFloat(row.find(".precio").val()) || 0;
        const iva = parseFloat(row.find(".iva").val()) || 0;
        const descuento = parseFloat(row.find(".descuento").val()) || 0;

        // Actualizar datos
        productosData[index].cantidad = cantidad;
        productosData[index].precio = precio;
        productosData[index].iva = iva;
        productosData[index].descuento = descuento;

        if (cantidad <= 0 || precio <= 0) {
            productosData[index].importe = 0;
            updateImporteDisplay(index, 0);
            calcularTotales();
            return;
        }

        let subtotal = precio * cantidad;
        let ivaTotal = subtotal * (iva / 100);
        let descuentoTotal = subtotal * (descuento / 100);
        let total = subtotal + ivaTotal - descuentoTotal;

        productosData[index].importe = total;
        updateImporteDisplay(index, total);
        calcularTotales();
    }

    function updateImporteDisplay(index, importe) {
        const display = $(`#importe-${index}`);
        const hiddenInput = $(`.producto-row[data-index="${index}"] .importe-hidden`);
        
        if (display.length) display.text(`$${importe.toFixed(2)}`);
        if (hiddenInput.length) hiddenInput.val(importe.toFixed(2));
    }

    function calcularTotales() {
        let subtotal = 0;
        let ivaTotal = 0;
        let descuentoTotal = 0;
        let total = 0;

        Object.values(productosData).forEach(producto => {
            const productSubtotal = producto.precio * producto.cantidad;
            const productIva = productSubtotal * (producto.iva / 100);
            const productDescuento = productSubtotal * (producto.descuento / 100);
            
            subtotal += productSubtotal;
            ivaTotal += productIva;
            descuentoTotal += productDescuento;
            total += productSubtotal + productIva - productDescuento;
        });

        $("#subtotal-display").text(`$${subtotal.toFixed(2)}`);
        $("#iva-display").text(`$${ivaTotal.toFixed(2)}`);
        $("#descuento-display").text(`-$${descuentoTotal.toFixed(2)}`);
        $("#total-display").text(`$${total.toFixed(2)}`);
    }

    // Inicializar
    $(document).ready(function() {
        inicializarAutocomplete();
        calcularTotales();
        
        // Validación del formulario
        $("#formPedido").on("submit", function(e) {
            let hasValidProducts = false;
            
            Object.values(productosData).forEach(producto => {
                if (producto.cantidad > 0 && producto.precio > 0) {
                    hasValidProducts = true;
                }
            });
            
            if (!hasValidProducts) {
                e.preventDefault();
                alert("Debe agregar al menos un producto válido al pedido.");
                return false;
            }
            
            // Validar cliente seleccionado
            const clienteId = $("select[name='cliente_id']").val();
            if (!clienteId) {
                e.preventDefault();
                alert("Debe seleccionar un cliente.");
                return false;
            }
            
            return true;
        });
    });
    </script>
</body>
</html>