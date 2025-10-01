<?php
/*
 * Archivo de configuración principal para la aplicación.
 * Define constantes para la conexión a la base de datos y otras configuraciones generales.
 */

// ------------------- CONFIGURACIÓN DE LA BASE DE DATOS ------------------- //
// Define las credenciales para la conexión a la base de datos MySQL.

/**
 * @const DB_HOST El servidor donde se encuentra la base de datos (generalmente 'localhost').
 */
define('DB_HOST', 'localhost');

/**
 * @const DB_USER El nombre de usuario para acceder a la base de datos.
 */
define('DB_USER', 'dialexan');

/**
 * @const DB_PASS La contraseña del usuario de la base de datos.
 */
define('DB_PASS', '././2_2/8-$/');

/**
 * @const DB_NAME El nombre de la base de datos a la que te quieres conectar.
 */
define('DB_NAME', 'dialexan_inventariosystem');

/**
 * @const DB_CHARSET El juego de caracteres para la conexión (recomendado: 'utf8mb4').
 */
define('DB_CHARSET', 'utf8mb4');


// ------------------- CONFIGURACIÓN GENERAL DEL SITIO ------------------- //
// Define rutas y URLs base para la aplicación.

/**
 * @const APP_URL La URL base de tu aplicación. ¡No olvides la barra al final!
 * Ejemplo: http://localhost/mi-proyecto/
 */
define('APP_URL', 'http://localhost/tu_proyecto/');

/**
 * @const APP_NAME El nombre de tu aplicación o sitio web.
 */
define('APP_NAME', 'Mi Increíble Aplicación');


// ------------------- OTRAS CONFIGURACIONES ------------------- //

/**
 * @const MODO_DEBUG Activa o desactiva el modo de depuración.
 * Poner en `true` para mostrar errores detallados durante el desarrollo.
 * Poner en `false` en un entorno de producción por seguridad.
 */
define('MODO_DEBUG', true);

// Configuración de la zona horaria
date_default_timezone_set('America/Mexico_City');

?>