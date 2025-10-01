<?php
include '../config/db.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id = $_GET['id'];

$stmt = $pdo->prepare("DELETE FROM almacenes WHERE id = ?");
$stmt->execute([$id]);

header('Location: index.php');
exit;
