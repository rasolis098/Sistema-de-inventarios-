<?php
include '../config/db.php';

$identificador = $_GET['email'] ?? $_GET['codigo'] ?? $_GET['token'] ?? '';

if (!$identificador) {
    die("Acceso no autorizado.");
}

// Obtener cliente según el identificador
$sql = "SELECT * FROM clientes WHERE email = :identificador OR codigo = :identificador";
$stmt = $pdo->prepare($sql);
$stmt->execute(['identificador' => $identificador]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    die("Cliente no encontrado.");
}

$cliente_id = $cliente['id'];

// Obtener pedidos del cliente
$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE cliente_id = ? ORDER BY fecha DESC");
$stmt->execute([$cliente_id]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cálculos
$total_compras = 0;
$ultimo_pedido = 0;
if ($pedidos) {
    $total_compras = array_sum(array_column($pedidos, 'total'));
    $ultimo_pedido = $pedidos[0]['total'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pedidos de <?= htmlspecialchars($cliente['nombre']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        .navbar-custom {
            background-color: #f8f9fa;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .nav-link-custom {
            color: #495057;
            font-weight: 500;
            margin: 0 5px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .nav-link-custom:hover {
            background-color: #e9ecef;
            color: #007bff;
        }
        .btn-cotizacion {
            background-color: #28a745;
            color: white;
            font-weight: 500;
        }
        .btn-cotizacion:hover {
            background-color: #218838;
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Barra de navegación -->
    <nav class="navbar navbar-expand-lg navbar-custom mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">MiCuenta</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom" href="#pedidos">Rastrear Pedido</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom" href="#" data-bs-toggle="modal" data-bs-target="#soporteModal">Soporte Técnico</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom" href="#" data-bs-toggle="modal" data-bs-target="#contactoModal">Contacto</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <button class="btn btn-cotizacion" data-bs-toggle="modal" data-bs-target="#cotizacionModal">
                        Solicitar Cotización
                    </button>
                </div>
            </div>
        </div>
    </nav>

<div class="container py-4">
    <h2>Bienvenido, <?= htmlspecialchars($cliente['nombre']) ?></h2>
    
    <div class="mb-4">
        <h5>Datos de Facturación:</h5>
        <p><strong>RFC:</strong> <?= htmlspecialchars($cliente['rfc']) ?><br>
           <strong>Dirección:</strong> <?= htmlspecialchars($cliente['direccion']) ?><br>
           <strong>Teléfono:</strong> <?= htmlspecialchars($cliente['telefono']) ?><br>
           <strong>Email:</strong> <?= htmlspecialchars($cliente['email']) ?></p>
    </div>

    <div class="mb-4">
        <h5>Resumen:</h5>
        <p><strong>Total de pedidos:</strong> <?= count($pedidos) ?><br>
           <strong>Total en compras:</strong> $<?= number_format($total_compras, 2) ?><br>
           <strong>Último pedido:</strong> $<?= number_format($ultimo_pedido, 2) ?></p>
    </div>

    <h4 id="pedidos">Pedidos</h4>
    <table class="table table-bordered bg-white">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Total</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($pedidos) > 0): ?>
            <?php foreach ($pedidos as $pedido): ?>
                <tr>
                    <td><?= $pedido['id'] ?></td>
                    <td><?= date("d/m/Y", strtotime($pedido['fecha'])) ?></td>
                    <td>$<?= number_format($pedido['total'], 2) ?></td>
                    <td><?= ucfirst($pedido['estado']) ?></td>
                    <td>
                        <a href="ver_pedido.php?id=<?= $pedido['id'] ?>&<?= is_numeric($identificador) ? 'codigo' : (strlen($identificador) == 32 ? 'token' : 'email') ?>=<?= urlencode($identificador) ?>" class="btn btn-sm btn-primary">
                            Ver detalles
                        </a>
                        <a href="ver_pedido_pdf.php?id=<?= $pedido['id'] ?>&<?= is_numeric($identificador) ? 'codigo' : (strlen($identificador) == 32 ? 'token' : 'email') ?>=<?= urlencode($identificador) ?>" class="btn btn-sm btn-danger" target="_blank">
                            PDF
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="5">No hay pedidos registrados.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <a href="index.php" class="btn btn-secondary mt-3">Cerrar sesión</a>
</div>

<!-- Modal Cotización -->
<div class="modal fade" id="cotizacionModal" tabindex="-1" aria-labelledby="cotizacionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cotizacionModalLabel">Solicitar Cotización</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Complete los detalles de su solicitud de cotización:</p>
                <form id="formCotizacion">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="cotizacionNombre" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="cotizacionNombre" value="<?= htmlspecialchars($cliente['nombre']) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="cotizacionEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="cotizacionEmail" value="<?= htmlspecialchars($cliente['email']) ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cotizacionProducto" class="form-label">Producto o Servicio de Interés</label>
                        <input type="text" class="form-control" id="cotizacionProducto" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cotizacionCantidad" class="form-label">Cantidad Estimada</label>
                        <input type="number" class="form-control" id="cotizacionCantidad" min="1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cotizacionDetalles" class="form-label">Detalles Adicionales</label>
                        <textarea class="form-control" id="cotizacionDetalles" rows="3" placeholder="Especificaciones técnicas, requisitos especiales, etc."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cotizacionFecha" class="form-label">Fecha Requerida</label>
                        <input type="date" class="form-control" id="cotizacionFecha" required>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="cotizacionTerminos" required>
                        <label class="form-check-label" for="cotizacionTerminos">Acepto los términos y condiciones</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="enviarCotizacion()">Enviar Solicitud</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Soporte Técnico -->
<div class="modal fade" id="soporteModal" tabindex="-1" aria-labelledby="soporteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="soporteModalLabel">Soporte Técnico</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Para asistencia técnica, por favor contacte a nuestro equipo de soporte:</p>
                <ul>
                    <li>Email: soporte@miempresa.com</li>
                    <li>Teléfono: +1 (123) 456-7890</li>
                    <li>Horario: Lunes a Viernes, 9:00 - 18:00</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Contacto -->
<div class="modal fade" id="contactoModal" tabindex="-1" aria-labelledby="contactoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contactoModalLabel">Contacto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Estamos aquí para ayudarte. Contáctanos a través de:</p>
                <ul>
                    <li>Email: info@miempresa.com</li>
                    <li>Teléfono: +1 (800) 123-4567</li>
                    <li>Dirección: Calle Principal #123, Ciudad</li>
                </ul>
                <p>También puedes completar el siguiente formulario y nos pondremos en contacto contigo:</p>
                <form>
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="nombre" value="<?= htmlspecialchars($cliente['nombre']) ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" value="<?= htmlspecialchars($cliente['email']) ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="mensaje" class="form-label">Mensaje</label>
                        <textarea class="form-control" id="mensaje" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary">Enviar mensaje</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function enviarCotizacion() {
        // Validar formulario
        const producto = document.getElementById('cotizacionProducto').value;
        const cantidad = document.getElementById('cotizacionCantidad').value;
        const fecha = document.getElementById('cotizacionFecha').value;
        const terminos = document.getElementById('cotizacionTerminos').checked;
        
        if (!producto || !cantidad || !fecha || !terminos) {
            alert('Por favor, complete todos los campos obligatorios y acepte los términos.');
            return;
        }
        
        // Aquí iría la lógica para enviar la cotización
        alert('Solicitud de cotización enviada con éxito. Nos pondremos en contacto pronto.');
        
        // Cerrar el modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('cotizacionModal'));
        modal.hide();
        
        // Limpiar formulario
        document.getElementById('formCotizacion').reset();
    }
    
    // Establecer fecha mínima como hoy
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('cotizacionFecha').setAttribute('min', today);
    });
</script>
</body>
</html>