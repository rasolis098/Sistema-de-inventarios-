<?php
include '../config/db.php';
include '../includes/header.php';

if (!isset($_GET['id'])) {
    echo "<div class='alert alert-danger'>ID no proporcionado.</div>";
    exit;
}

$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM categorias WHERE id = ?");
$stmt->execute([$id]);
$categoria = $stmt->fetch();

if (!$categoria) {
    echo "<div class='alert alert-danger'>Categoría no encontrada.</div>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $stmt = $pdo->prepare("UPDATE categorias SET nombre = ? WHERE id = ?");
    $stmt->execute([$nombre, $id]);
    header("Location: index.php");
    exit;
}
?>

<div class="container mt-4">
    <h2>Editar Categoría</h2>
    <form method="POST">
        <div class="form-group">
            <label>Nombre</label>
            <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($categoria['nombre']) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary mt-3">Actualizar</button>
        <a href="index.php" class="btn btn-secondary mt-3">Cancelar</a>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
