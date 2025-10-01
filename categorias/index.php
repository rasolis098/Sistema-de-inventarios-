<?php
include '../config/db.php';
include '../includes/header.php';

$stmt = $pdo->query("SELECT * FROM categorias");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h2>Categorías</h2>
    <a href="agregar.php" class="btn btn-success mb-3">Agregar Categoría</a>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categorias as $categoria): ?>
            <tr>
                <td><?= $categoria['id'] ?></td>
                <td><?= htmlspecialchars($categoria['nombre']) ?></td>
                <td>
                    <a href="editar.php?id=<?= $categoria['id'] ?>" class="btn btn-primary btn-sm">Editar</a>
                    <a href="eliminar.php?id=<?= $categoria['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de eliminar esta categoría?');">Eliminar</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
