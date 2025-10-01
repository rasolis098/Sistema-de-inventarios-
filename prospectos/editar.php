<?php
include '../config/db.php';
include '../includes/header.php';

// Verificar si hay mensajes
$mensaje_exito = '';
if (isset($_SESSION['mensaje_exito'])) {
    $mensaje_exito = $_SESSION['mensaje_exito'];
    unset($_SESSION['mensaje_exito']);
}

$mensaje_error = '';
if (isset($_SESSION['mensaje_error'])) {
    $mensaje_error = $_SESSION['mensaje_error'];
    unset($_SESSION['mensaje_error']);
}

if (!isset($_GET['id'])) {
    $_SESSION['mensaje_error'] = "ID de prospecto no proporcionado.";
    header('Location: index.php');
    exit;
}

$id = $_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM prospectos WHERE id = ?");
    $stmt->execute([$id]);
    $prospecto = $stmt->fetch();

    if (!$prospecto) {
        $_SESSION['mensaje_error'] = "Prospecto no encontrado.";
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Error al cargar prospecto: " . $e->getMessage());
    $_SESSION['mensaje_error'] = "Error al cargar los datos del prospecto.";
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $email = $_POST['email'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $estado = $_POST['estado'] ?? 'nuevo';
    $notas = $_POST['notas'] ?? '';

    if (trim($nombre) === '') {
        $mensaje_error = "El nombre es obligatorio.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE prospectos SET nombre=?, email=?, telefono=?, estado=?, notas=? WHERE id=?");
            $stmt->execute([$nombre, $email, $telefono, $estado, $notas, $id]);
            
            $_SESSION['mensaje_exito'] = "Prospecto actualizado correctamente.";
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            error_log("Error al actualizar prospecto: " . $e->getMessage());
            $mensaje_error = "Error al actualizar el prospecto: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Prospecto - Sistema de Inventario</title>
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
            display: flex;
            align-items: center;
        }
        
        .required-field::after {
            content: " *";
            color: var(--danger-color);
            margin-left: 3px;
        }
        
        .form-control, .form-select, .form-textarea {
            border: 1px solid #d1d9e6;
            border-radius: 8px;
            padding: 12px 16px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        
        .form-control:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .form-control:disabled {
            background-color: #f8f9fa;
            color: #6c757d;
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
        
        .form-text {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 6px;
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
        
        .state-badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.85rem;
            margin-right: 8px;
            margin-bottom: 8px;
        }
        
        .state-badge i {
            margin-right: 6px;
            font-size: 0.75rem;
        }
        
        .badge-nuevo {
            background-color: rgba(108, 117, 125, 0.15);
            color: #6c757d;
        }
        
        .badge-contactado {
            background-color: rgba(23, 162, 184, 0.15);
            color: #17a2b8;
        }
        
        .badge-interesado {
            background-color: rgba(39, 174, 96, 0.15);
            color: #27ae60;
        }
        
        .badge-no-interesado {
            background-color: rgba(220, 53, 69, 0.15);
            color: #dc3545;
        }
        
        .character-count {
            font-size: 0.8rem;
            color: #94a3b8;
            text-align: right;
            margin-top: 5px;
        }
        
        .form-actions {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            border: var(--card-border);
        }
        
        .info-badge {
            background: rgba(52, 152, 219, 0.1);
            color: var(--primary-color);
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .info-badge i {
            margin-right: 8px;
        }
        
        @media (max-width: 768px) {
            .form-container {
                padding: 20px 15px;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .form-actions .d-flex {
                flex-direction: column;
            }
            
            .alert-fixed {
                left: 20px;
                right: 20px;
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Mostrar mensajes de éxito/error -->
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
                    <h1 class="h3 fw-bold"><i class="fas fa-user-edit me-2 text-warning"></i>Editar Prospecto</h1>
                    <p class="mb-0 text-muted">Actualizando información del cliente potencial</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver al Listado
                    </a>
                </div>
            </div>
        </div>

        <form method="post" action="" id="prospectoForm">
            <div class="form-container">
                <!-- Información de Identificación -->
                <div class="info-badge">
                    <i class="fas fa-info-circle"></i>
                    Editando prospecto ID: <strong>#<?= $prospecto['id'] ?></strong> · 
                    Registrado el: <strong><?= date('d/m/Y H:i', strtotime($prospecto['fecha_registro'])) ?></strong>
                </div>

                <!-- Información Básica -->
                <div class="form-section">
                    <h3 class="section-title"><i class="fas fa-user"></i>Información Básica</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label required-field">Nombre completo</label>
                            <div class="input-group-icon">
                                <i class="fas fa-user"></i>
                                <input type="text" name="nombre" class="form-control" required 
                                       value="<?= htmlspecialchars($prospecto['nombre']) ?>"
                                       placeholder="Ingrese el nombre completo">
                            </div>
                            <div class="form-text">Nombre completo del prospecto</div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Estado actual</label>
                            <div class="mb-3">
                                <span class="state-badge badge-<?php 
                                    switch($prospecto['estado']) {
                                        case 'nuevo': echo 'nuevo'; break;
                                        case 'contactado': echo 'contactado'; break;
                                        case 'interesado': echo 'interesado'; break;
                                        case 'no interesado': echo 'no-interesado'; break;
                                        default: echo 'nuevo';
                                    }
                                ?>">
                                    <i class="fas fa-circle"></i><?= ucfirst($prospecto['estado']) ?>
                                </span>
                            </div>
                            
                            <label class="form-label">Cambiar estado</label>
                            <select name="estado" class="form-select">
                                <option value="nuevo" <?= $prospecto['estado']=='nuevo' ? 'selected' : '' ?>>Nuevo</option>
                                <option value="contactado" <?= $prospecto['estado']=='contactado' ? 'selected' : '' ?>>Contactado</option>
                                <option value="interesado" <?= $prospecto['estado']=='interesado' ? 'selected' : '' ?>>Interesado</option>
                                <option value="no interesado" <?= $prospecto['estado']=='no interesado' ? 'selected' : '' ?>>No interesado</option>
                            </select>
                            <div class="form-text">Seleccione el nuevo estado del prospecto</div>
                        </div>
                    </div>
                </div>

                <!-- Información de Contacto -->
                <div class="form-section">
                    <h3 class="section-title"><i class="fas fa-address-card"></i>Información de Contacto</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Correo electrónico</label>
                            <div class="input-group-icon">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="email" class="form-control" 
                                       value="<?= htmlspecialchars($prospecto['email']) ?>"
                                       placeholder="ejemplo@correo.com">
                            </div>
                            <div class="form-text">Dirección de correo electrónico válida</div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Número de teléfono</label>
                            <div class="input-group-icon">
                                <i class="fas fa-phone"></i>
                                <input type="text" name="telefono" class="form-control" 
                                       value="<?= htmlspecialchars($prospecto['telefono']) ?>"
                                       placeholder="+1 (555) 123-4567">
                            </div>
                            <div class="form-text">Número de contacto opcional</div>
                        </div>
                    </div>
                </div>

                <!-- Información Adicional -->
                <div class="form-section">
                    <h3 class="section-title"><i class="fas fa-calendar"></i>Información Adicional</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Fecha de registro</label>
                            <div class="input-group-icon">
                                <i class="fas fa-calendar-alt"></i>
                                <input type="text" class="form-control" 
                                       value="<?= date('d/m/Y H:i', strtotime($prospecto['fecha_registro'])) ?>" 
                                       disabled>
                            </div>
                            <div class="form-text">Fecha y hora de registro del prospecto</div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Última actualización</label>
                            <div class="input-group-icon">
                                <i class="fas fa-sync-alt"></i>
                                <input type="text" class="form-control" 
                                       value="<?= date('d/m/Y H:i') ?>" 
                                       disabled>
                            </div>
                            <div class="form-text">Se actualizará al guardar los cambios</div>
                        </div>
                    </div>
                </div>

                <!-- Notas Adicionales -->
                <div class="form-section">
                    <h3 class="section-title"><i class="fas fa-sticky-note"></i>Notas Adicionales</h3>
                    
                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <label class="form-label">Información adicional</label>
                            <textarea name="notas" class="form-control form-textarea" rows="5" 
                                      placeholder="Agregue cualquier información relevante sobre el prospecto, como intereses, comentarios o observaciones..."><?= htmlspecialchars($prospecto['notas']) ?></textarea>
                            <div class="form-text">Información adicional que pueda ser útil para el seguimiento</div>
                            <div class="character-count" id="notes-counter"><?= strlen($prospecto['notas']) ?> caracteres</div>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="form-actions">
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Actualizar Prospecto
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Auto-ocultar alertas después de 5 segundos
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);

    // Contador de caracteres para notas
    const notesTextarea = document.querySelector('textarea[name="notas"]');
    const notesCounter = document.getElementById('notes-counter');
    
    if (notesTextarea && notesCounter) {
        notesTextarea.addEventListener('input', function() {
            const length = this.value.length;
            notesCounter.textContent = `${length} caracteres`;
        });
    }

    // Validación del formulario
    document.getElementById('prospectoForm').addEventListener('submit', function(e) {
        const nombre = document.querySelector('input[name="nombre"]').value.trim();
        
        if (nombre === '') {
            e.preventDefault();
            
            // Mostrar mensaje de error
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show alert-fixed';
            alertDiv.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-circle me-2 fa-lg"></i>
                    <div class="flex-grow-1">El nombre es obligatorio.</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            document.body.appendChild(alertDiv);
            
            // Enfocar el campo nombre
            document.querySelector('input[name="nombre"]').focus();
            
            // Auto-ocultar la alerta después de 5 segundos
            setTimeout(function() {
                alertDiv.remove();
            }, 5000);
        }
    });

    // Efecto de focus en los campos
    const formControls = document.querySelectorAll('.form-control, .form-select, .form-textarea');
    formControls.forEach(control => {
        control.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        control.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
    });
    </script>
</body>
</html>

<?php include '../includes/footer.php'; ?>