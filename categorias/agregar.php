<?php
include '../config/db.php';
include '../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $stmt = $pdo->prepare("INSERT INTO categorias (nombre) VALUES (?)");
    $stmt->execute([$nombre]);
    header("Location: index.php");
    exit;
}
?>

<div class="container mt-4">
    <h2>Agregar Categoría</h2>
    <form method="POST">
        <div class="form-group">
            <label>Nombre</label>
            <input type="text" name="nombre" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-success mt-3">Guardar</button>
        <a href="index.php" class="btn btn-secondary mt-3">Cancelar</a>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
