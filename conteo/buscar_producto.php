<?php
require '../config/db.php';

if(isset($_POST['query'])){
    $q = "%".$_POST['query']."%";

    $stmt = $pdo->prepare("SELECT id, codigo, nombre 
                           FROM productos 
                           WHERE codigo LIKE ? OR nombre LIKE ? 
                           LIMIT 10");
    $stmt->execute([$q,$q]);

    while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
        echo "<div data-id='{$row['id']}'>[{$row['codigo']}] {$row['nombre']}</div>";
    }
}
