<?php
session_start();

// Destruir todas las variables de la sesión
session_unset();

// Destruir la sesión
session_destroy();

// Redirigir al login después de cerrar sesión
header('Location: login.php');
exit;
?>
