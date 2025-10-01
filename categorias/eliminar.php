<?php
include '../config/db.php';

if (!isset($_GET['id'])) {
    die("ID no proporcionado.");
}

$id = $_GET['id'];

$stmt = $pdo->prepare("DELETE FROM categorias WHERE id = ?");
$stmt->execute([$id]);

header("Location: index.php");
exit;
