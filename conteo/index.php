<?php
include '../config/db.php';
include '../includes/header.php';
session_start();

// Listado de conteos con resumen
$sql = "SELECT c.id, c.descripcion, c.created_at,
               COUNT(i.id) AS items,
               COALESCE(SUM(i.diferencia),0) AS total_diferencia
        FROM conteos c
        LEFT JOIN inventario_fisico i ON i.conteo_id = c.id
        GROUP BY c.id
        ORDER BY c.created_at DESC";
$conteos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Conteos Anteriores</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Conteos Anteriores</h2>
    <a href="nuevo.php?action=reset" class="btn btn-primary">Nuevo Conteo</a>
  </div>

  <?php if (empty($conteos)): ?>
    <div class="alert alert-info">Aún no hay conteos registrados.</div>
  <?php else: ?>
  <table class="table table-bordered align-middle">
    <thead>
      <tr>
        <th>ID</th>
        <th>Descripción</th>
        <th>Fecha</th>
        <th>Ítems</th>
        <th>Δ Total</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($conteos as $c): ?>
        <tr>
          <td><?= $c['id'] ?></td>
          <td><?= htmlspecialchars($c['descripcion']) ?></td>
          <td><?= $c['created_at'] ?></td>
          <td><?= $c['items'] ?></td>
          <td><?= $c['total_diferencia'] ?></td>
          <td class="d-flex gap-2">
            <a class="btn btn-sm btn-secondary" href="ver.php?id=<?= $c['id'] ?>">Ver</a>
            <a class="btn btn-sm btn-danger" href="export_pdf.php?id=<?= $c['id'] ?>">PDF</a>
            <a class="btn btn-sm btn-success" href="export_excel.php?id=<?= $c['id'] ?>">Excel</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</body>
</html>
<?php include '../includes/footer.php'; ?>
