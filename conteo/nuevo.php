<?php
require '../config/db.php';
session_start();

// Crear nuevo conteo si no existe
if (!isset($_SESSION['conteo_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['descripcion'])) {
        $stmt = $pdo->prepare("INSERT INTO conteos (descripcion) VALUES (?)");
        $stmt->execute([$_POST['descripcion']]);
        $_SESSION['conteo_id'] = $pdo->lastInsertId();
        header("Location: nuevo.php");
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="UTF-8">
      <title>Nuevo Conteo</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="container py-4">
      <h2>Crear Nuevo Conteo</h2>
      <form method="post" class="card p-4">
        <div class="mb-3">
          <label class="form-label">Descripción del conteo</label>
          <input type="text" name="descripcion" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Iniciar Conteo</button>
      </form>
    </body>
    </html>
    <?php
    exit;
}

// Si ya existe conteo activo
$conteo_id = $_SESSION['conteo_id'];

// Guardar producto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['producto_id'])) {
    $producto_id  = $_POST['producto_id'];
    $almacen_id   = $_POST['almacen_id'];
    $stock_fisico = $_POST['stock_fisico'];

    // Stock sistema
    $stmt = $pdo->prepare("SELECT stock FROM stock_almacenes WHERE producto_id=? AND almacen_id=?");
    $stmt->execute([$producto_id, $almacen_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stock_sistema = $row ? $row['stock'] : 0;
    $diferencia    = $stock_fisico - $stock_sistema;

    // Guardar conteo detalle
    $insert = $pdo->prepare("INSERT INTO inventario_fisico 
        (conteo_id, producto_id, almacen_id, stock_sistema, stock_fisico, diferencia) 
        VALUES (?,?,?,?,?,?)");
    $insert->execute([$conteo_id, $producto_id, $almacen_id, $stock_sistema, $stock_fisico, $diferencia]);
}

// Almacenes
$almacenes = $pdo->query("SELECT id, nombre FROM almacenes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Productos del conteo actual
$stmt = $pdo->prepare("SELECT p.codigo, p.nombre, a.nombre AS almacen, i.stock_sistema, i.stock_fisico, i.diferencia
                       FROM inventario_fisico i
                       JOIN productos p ON i.producto_id=p.id
                       JOIN almacenes a ON i.almacen_id=a.id
                       WHERE i.conteo_id=?");
$stmt->execute([$conteo_id]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Nuevo Conteo</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    #sugerencias {border:1px solid #ccc;max-height:200px;overflow-y:auto;position:absolute;background:#fff;width:100%;}
    #sugerencias div {padding:5px;cursor:pointer;}
    #sugerencias div:hover {background:#f0f0f0;}
  </style>
</head>
<body class="container py-4">
  <h2>Conteo Activo</h2>
  <p><strong>ID Conteo:</strong> <?= $conteo_id ?></p>

  <!-- Formulario captura -->
  <form method="post" class="card p-4 mb-4">
    <div class="mb-3 position-relative">
      <label for="buscar" class="form-label">Buscar producto</label>
      <input type="text" id="buscar" class="form-control" placeholder="Escribe código o nombre">
      <div id="sugerencias"></div>
      <input type="hidden" name="producto_id" id="producto_id" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Almacén</label>
      <select name="almacen_id" class="form-select" required>
        <option value="">Seleccione</option>
        <?php foreach($almacenes as $a): ?>
          <option value="<?= $a['id'] ?>"><?= $a['nombre'] ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Stock Físico</label>
      <input type="number" name="stock_fisico" class="form-control" required>
    </div>

    <button type="submit" class="btn btn-primary">Agregar Producto</button>
  </form>

  <!-- Lista productos ya capturados -->
  <h4>Productos Capturados</h4>
  <table class="table table-bordered">
    <thead>
      <tr>
        <th>Código</th><th>Producto</th><th>Almacén</th><th>Sistema</th><th>Físico</th><th>Diferencia</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($productos as $p): ?>
        <tr>
          <td><?= $p['codigo'] ?></td>
          <td><?= $p['nombre'] ?></td>
          <td><?= $p['almacen'] ?></td>
          <td><?= $p['stock_sistema'] ?></td>
          <td><?= $p['stock_fisico'] ?></td>
          <td><?= $p['diferencia'] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <a href="index.php" class="btn btn-secondary">Finalizar Conteo</a>

<script>
$(function(){
    $("#buscar").keyup(function(){
        var q = $(this).val();
        if(q.length > 1){
            $.post("buscar_producto.php",{query:q},function(data){
                $("#sugerencias").html(data).show();
            });
        } else {
            $("#sugerencias").hide();
        }
    });
    $(document).on("click","#sugerencias div",function(){
        $("#producto_id").val($(this).data("id"));
        $("#buscar").val($(this).text());
        $("#sugerencias").hide();
    });
});
</script>
</body>
</html>
