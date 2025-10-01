<?php
require '../config/db.php';

$conteo_id = $_GET['id'] ?? null;
if (!$conteo_id) {
  die("Falta el ID del conteo");
}

$almacen_id = $_GET['almacen_id'] ?? '';

// Datos del conteo
$stmt = $pdo->prepare("SELECT * FROM conteos WHERE id=?");
$stmt->execute([$conteo_id]);
$conteo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$conteo) die("Conteo no encontrado");

// Almacenes para el filtro
$almacenes = $pdo->query("SELECT id, nombre FROM almacenes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Query resultados
if ($almacen_id === '') {
  // Conteo general (sumado por producto)
  $sql = "SELECT p.codigo, p.nombre,
                 SUM(i.stock_sistema) AS stock_sistema,
                 SUM(i.stock_fisico)  AS stock_fisico,
                 SUM(i.diferencia)    AS diferencia
          FROM inventario_fisico i
          JOIN productos p ON i.producto_id=p.id
          WHERE i.conteo_id=?
          GROUP BY p.id, p.codigo, p.nombre
          ORDER BY p.nombre";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$conteo_id]);
} else {
  // Por almacén
  $sql = "SELECT p.codigo, p.nombre, a.nombre AS almacen,
                 i.stock_sistema, i.stock_fisico, i.diferencia
          FROM inventario_fisico i
          JOIN productos p ON i.producto_id=p.id
          JOIN almacenes a ON i.almacen_id=a.id
          WHERE i.conteo_id=? AND i.almacen_id=?
          ORDER BY p.nombre";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$conteo_id, $almacen_id]);
}
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Ver Conteo</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
  <h2 class="mb-1"><?= htmlspecialchars($conteo['descripcion']) ?></h2>
  <p class="text-muted">ID: <?= $conteo_id ?> · Fecha: <?= $conteo['created_at'] ?></p>

  <form method="get" class="mb-3">
    <input type="hidden" name="id" value="<?= $conteo_id ?>">
    <label class="form-label">Vista:</label>
    <select name="almacen_id" class="form-select" onchange="this.form.submit()">
      <option value="">Conteo General (todos los almacenes)</option>
      <?php foreach($almacenes as $a): ?>
        <option value="<?= $a['id'] ?>" <?= ($almacen_id==$a['id'])?'selected':'' ?>>
          <?= $a['nombre'] ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>

  <table class="table table-bordered">
    <thead>
      <tr>
        <th>Código</th>
        <th>Producto</th>
        <?php if ($almacen_id!==''): ?><th>Almacén</th><?php endif; ?>
        <th>Stock Sistema</th>
        <th>Stock Físico</th>
        <th>Diferencia</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($registros as $r): ?>
      <tr>
        <td><?= $r['codigo'] ?></td>
        <td><?= $r['nombre'] ?></td>
        <?php if ($almacen_id!==''): ?><td><?= $r['almacen'] ?></td><?php endif; ?>
        <td><?= $r['stock_sistema'] ?></td>
        <td><?= $r['stock_fisico'] ?></td>
        <td><?= $r['diferencia'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="mt-3 d-flex gap-2">
    <a class="btn btn-danger" href="export_pdf.php?id=<?= $conteo_id ?>&almacen_id=<?= urlencode($almacen_id) ?>">Descargar PDF</a>
    <a class="btn btn-success" href="export_excel.php?id=<?= $conteo_id ?>&almacen_id=<?= urlencode($almacen_id) ?>">Descargar Excel</a>
    <a class="btn btn-secondary" href="index.php">Volver</a>
  </div>
</body>
</html>

