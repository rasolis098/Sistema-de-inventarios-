<?php
session_start();
include '../config/db.php';
include '../includes/header.php';

// Lógica para obtener todos los usuarios
try {
    $stmt = $pdo->query("SELECT id, nombre, email, activo, rol, id_empleado FROM usuarios ORDER BY nombre");
    $usuarios = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error al obtener usuarios: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd; --light-bg: #f8f9fa; --border-color: #dee2e6;
            --card-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        body { background-color: var(--light-bg); }
        .main-card { background-color: white; border-radius: 0.75rem; border: 1px solid var(--border-color); box-shadow: var(--card-shadow); overflow: hidden; }
        .card-header-custom { background-color: #fff; padding: 1.5rem; border-bottom: 1px solid var(--border-color); }
        .page-title { font-weight: 700; font-size: 1.75rem; }
    </style>
</head>
<body>
<div class="container my-4">
    <div class="main-card">
        <div class="card-header-custom">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="page-title mb-0"><i class="fas fa-users-cog me-2"></i>Gestión de Usuarios</h1>
                    <p class="text-muted mb-0">Añade, edita y gestiona los roles de acceso al sistema.</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAgregarUsuario">
                    <i class="fas fa-user-plus me-1"></i>Nuevo Usuario
                </button>
            </div>
        </div>

        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID Empleado</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($usuario['id_empleado'] ?? 'N/A') ?></span></td>
                            <td class="fw-bold"><?= htmlspecialchars($usuario['nombre']) ?></td>
                            <td><?= htmlspecialchars($usuario['email']) ?></td>
                            <td>
                                <?php if ($usuario['rol'] == 'administrador'): ?>
                                    <span class="badge bg-success">Administrador</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark">Empleado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($usuario['activo']): ?>
                                    <span class="badge bg-light text-success"><i class="fas fa-circle me-1"></i>Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-danger"><i class="fas fa-circle me-1"></i>Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-warning btn-sm edit-btn" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#modalEditarUsuario"
                                        data-id="<?= $usuario['id'] ?>"
                                        data-nombre="<?= htmlspecialchars($usuario['nombre']) ?>"
                                        data-email="<?= htmlspecialchars($usuario['email']) ?>"
                                        data-id_empleado="<?= htmlspecialchars($usuario['id_empleado']) ?>"
                                        data-rol="<?= $usuario['rol'] ?>"
                                        data-activo="<?= $usuario['activo'] ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm delete-btn"
                                        data-id="<?= $usuario['id'] ?>"
                                        data-nombre="<?= htmlspecialchars($usuario['nombre']) ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAgregarUsuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="agregar_usuario.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Añadir Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nombre Completo</label><input type="text" name="nombre" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">ID de Empleado</label><input type="text" name="id_empleado" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Contraseña</label><input type="password" name="password" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Rol</label><select name="rol" class="form-select"><option value="empleado">Empleado</option><option value="administrador">Administrador</option></select></div>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="activo" value="1" checked><label class="form-check-label">Usuario Activo</label></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarUsuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="editar_usuario.php" method="POST">
                <input type="hidden" name="id" id="edit-id">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nombre Completo</label><input type="text" name="nombre" id="edit-nombre" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">ID de Empleado</label><input type="text" name="id_empleado" id="edit-id_empleado" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" id="edit-email" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Nueva Contraseña (dejar en blanco para no cambiar)</label><input type="password" name="password" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Rol</label><select name="rol" id="edit-rol" class="form-select"><option value="empleado">Empleado</option><option value="administrador">Administrador</option></select></div>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="activo" value="1" id="edit-activo"><label class="form-check-label">Usuario Activo</label></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Actualizar Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Script para llenar el modal de edición
    const modalEditar = document.getElementById('modalEditarUsuario');
    modalEditar.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        document.getElementById('edit-id').value = button.dataset.id;
        document.getElementById('edit-nombre').value = button.dataset.nombre;
        document.getElementById('edit-email').value = button.dataset.email;
        document.getElementById('edit-id_empleado').value = button.dataset.id_empleado;
        document.getElementById('edit-rol').value = button.dataset.rol;
        document.getElementById('edit-activo').checked = button.dataset.activo == '1';
    });

    // Script para confirmar eliminación
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const nombre = this.dataset.nombre;
            if (confirm(`¿Estás seguro de que deseas eliminar al usuario "${nombre}"?`)) {
                window.location.href = `eliminar_usuario.php?id=${id}`;
            }
        });
    });
});
</script>
</body>
</html>