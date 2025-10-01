<?php
include '../config/db.php';
include '../includes/header.php';

// Procesar el formulario
$mensaje_exito = '';
$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $ubicacion = trim($_POST['ubicacion'] ?? '');
    $responsable = trim($_POST['responsable'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $capacidad = trim($_POST['capacidad'] ?? '');
    $tipo = trim($_POST['tipo'] ?? 'general');
    $estado = trim($_POST['estado'] ?? 'activo');
    $observaciones = trim($_POST['observaciones'] ?? '');

    // Validaciones
    if (empty($nombre)) {
        $mensaje_error = "El nombre del almacén es obligatorio.";
    } else {
        try {
            // Verificar si el almacén ya existe
            $stmt = $pdo->prepare("SELECT id FROM almacenes WHERE nombre = ?");
            $stmt->execute([$nombre]);
            
            if ($stmt->rowCount() > 0) {
                $mensaje_error = "Ya existe un almacén con ese nombre.";
            } else {
                $sql = "INSERT INTO almacenes (nombre, ubicacion, responsable, telefono, email, capacidad, tipo, estado, observaciones) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $ubicacion, $responsable, $telefono, $email, $capacidad, $tipo, $estado, $observaciones]);

                $mensaje_exito = "Almacén agregado correctamente.";
                
                // Limpiar el formulario después de guardar exitosamente
                if ($mensaje_exito) {
                    $_POST = array();
                }
            }
        } catch (PDOException $e) {
            error_log("Error al agregar almacén: " . $e->getMessage());
            $mensaje_error = "Error al agregar el almacén: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Almacén - Sistema de Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
        
        .alert-fixed {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 350px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            border: none;
        }
        
        .character-count {
            font-size: 0.8rem;
            color: #94a3b8;
            text-align: right;
            margin-top: 5px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #eaeef2;
        }
        
        .badge {
            font-weight: 500;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .badge-success {
            background-color: rgba(39, 174, 96, 0.15);
            color: #27ae60;
        }
        
        .badge-warning {
            background-color: rgba(243, 156, 18, 0.15);
            color: #f39c12;
        }
        
        .badge-secondary {
            background-color: rgba(108, 117, 125, 0.15);
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .form-container {
                padding: 20px 15px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Mostrar mensajes -->
        <?php if (!empty($mensaje_exito)): ?>
        <div class="alert alert-success alert-dismissible fade show alert-fixed" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle me-2 fa-lg"></i>
                <div class="flex-grow-1"><?php echo $mensaje_exito; ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($mensaje_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show alert-fixed" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-circle me-2 fa-lg"></i>
                <div class="flex-grow-1"><?php echo $mensaje_error; ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h3 fw-bold"><i class="fas fa-warehouse me-2 text-primary"></i>Agregar Nuevo Almacén</h1>
                    <p class="mb-0 text-muted">Complete la información para registrar un nuevo almacén en el sistema</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver a Almacenes
                    </a>
                </div>
            </div>
        </div>

        <form method="post" action="" id="formAlmacen">
            <div class="form-container">
                <!-- Información Básica -->
                <div class="form-section">
                    <h3 class="section-title"><i class="fas fa-info-circle"></i>Información Básica</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label required-field">Nombre del Almacén</label>
                            <div class="input-group-icon">
                                <i class="fas fa-warehouse"></i>
                                <input type="text" name="nombre" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" 
                                       placeholder="Ej: Almacén Principal, Bodega Norte, etc." required
                                       maxlength="100">
                            </div>
                            <div class="character-count" id="nombre-counter">
                                <?= strlen($_POST['nombre'] ?? '') ?>/100 caracteres
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Tipo de Almacén</label>
                            <select name="tipo" class="form-select">
                                <option value="general" <?= ($_POST['tipo'] ?? 'general') === 'general' ? 'selected' : '' ?>>General</option>
                                <option value="principal" <?= ($_POST['tipo'] ?? '') === 'principal' ? 'selected' : '' ?>>Principal</option>
                                <option value="secundario" <?= ($_POST['tipo'] ?? '') === 'secundario' ? 'selected' : '' ?>>Secundario</option>
                                <option value="temporal" <?= ($_POST['tipo'] ?? '') === 'temporal' ? 'selected' : '' ?>>Temporal</option>
                                <option value="refrigerado" <?= ($_POST['tipo'] ?? '') === 'refrigerado' ? 'selected' : '' ?>>Refrigerado</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <label class="form-label">Ubicación</label>
                            <div class="input-group-icon">
                                <i class="fas fa-map-marker-alt"></i>
                                <textarea name="ubicacion" class="form-control" rows="3" 
                                          placeholder="Dirección completa del almacén..."
                                          maxlength="255"><?= htmlspecialchars($_POST['ubicacion'] ?? '') ?></textarea>
                            </div>
                            <div class="character-count" id="ubicacion-counter">
                                <?= strlen($_POST['ubicacion'] ?? '') ?>/255 caracteres
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información de Contacto -->
                <div class="form-section">
                    <h3 class="section-title"><i class="fas fa-address-card"></i>Información de Contacto</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label required-field">Responsable</label>
                            <div class="input-group-icon">
                                <i class="fas fa-user"></i>
                                <input type="text" name="responsable" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['responsable'] ?? '') ?>" 
                                       placeholder="Nombre del responsable del almacén" required
                                       maxlength="100">
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Teléfono de Contacto</label>
                            <div class="input-group-icon">
                                <i class="fas fa-phone"></i>
                                <input type="tel" name="telefono" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>" 
                                       placeholder="+1 (555) 123-4567"
                                       maxlength="20">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Correo Electrónico</label>
                            <div class="input-group-icon">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="email" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                       placeholder="responsable@empresa.com"
                                       maxlength="100">
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select">
                                <option value="activo" <?= ($_POST['estado'] ?? 'activo') === 'activo' ? 'selected' : '' ?>>Activo</option>
                                <option value="inactivo" <?= ($_POST['estado'] ?? '') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                                <option value="mantenimiento" <?= ($_POST['estado'] ?? '') === 'mantenimiento' ? 'selected' : '' ?>>En Mantenimiento</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Capacidad y Observaciones -->
                <div class="form-section">
                    <h3 class="section-title"><i class="fas fa-chart-line"></i>Capacidad y Observaciones</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Capacidad (m²)</label>
                            <div class="input-group-icon">
                                <i class="fas fa-arrows-alt"></i>
                                <input type="number" name="capacidad" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['capacidad'] ?? '') ?>" 
                                       placeholder="Ej: 1000" min="0" step="0.01">
                            </div>
                            <div class="form-text">Área total en metros cuadrados</div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Observaciones</label>
                            <textarea name="observaciones" class="form-control" rows="3" 
                                      placeholder="Notas adicionales sobre el almacén..."
                                      maxlength="500"><?= htmlspecialchars($_POST['observaciones'] ?? '') ?></textarea>
                            <div class="character-count" id="observaciones-counter">
                                <?= strlen($_POST['observaciones'] ?? '') ?>/500 caracteres
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="action-buttons">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Guardar Almacén
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Contador de caracteres
    const nombreInput = document.querySelector('input[name="nombre"]');
    const nombreCounter = document.getElementById('nombre-counter');
    
    const ubicacionInput = document.querySelector('textarea[name="ubicacion"]');
    const ubicacionCounter = document.getElementById('ubicacion-counter');
    
    const observacionesInput = document.querySelector('textarea[name="observaciones"]');
    const observacionesCounter = document.getElementById('observaciones-counter');
    
    if (nombreInput && nombreCounter) {
        nombreInput.addEventListener('input', function() {
            nombreCounter.textContent = `${this.value.length}/100 caracteres`;
        });
    }
    
    if (ubicacionInput && ubicacionCounter) {
        ubicacionInput.addEventListener('input', function() {
            ubicacionCounter.textContent = `${this.value.length}/255 caracteres`;
        });
    }
    
    if (observacionesInput && observacionesCounter) {
        observacionesInput.addEventListener('input', function() {
            observacionesCounter.textContent = `${this.value.length}/500 caracteres`;
        });
    }
    
    // Validación del formulario
    document.getElementById('formAlmacen').addEventListener('submit', function(e) {
        const nombre = document.querySelector('input[name="nombre"]').value.trim();
        const responsable = document.querySelector('input[name="responsable"]').value.trim();
        
        if (!nombre) {
            e.preventDefault();
            alert('El nombre del almacén es obligatorio.');
            document.querySelector('input[name="nombre"]').focus();
            return false;
        }
        
        if (!responsable) {
            e.preventDefault();
            alert('El nombre del responsable es obligatorio.');
            document.querySelector('input[name="responsable"]').focus();
            return false;
        }
        
        return true;
    });
    
    // Auto-ocultar alertas después de 5 segundos
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (alert.classList.contains('show')) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        });
    }, 5000);
    </script>
</body>
</html>

<?php include '../includes/footer.php'; ?>