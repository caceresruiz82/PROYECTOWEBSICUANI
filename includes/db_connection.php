<?php
/**
 * db_connection.php
 *
 * Archivo encargado de establecer la conexión segura a la base de datos MySQL.
 * Utiliza la extensión MySQLi para una conexión orientada a objetos.
 * Incluye configuración de zona horaria PHP y buffering de salida para limpieza.
 *
 * @package EssaludSicuaniWeb
 * @subpackage Includes
 * @author Yudelvis Caceres
 * @version 1.0.2 // Añadido ob_start() y ob_end_clean() para manejar posibles caracteres BOM/salida no deseada.
 * @date 2025-07-27
 */

// Iniciar el buffering de salida para capturar cualquier caracter antes de la etiqueta PHP o después de ella en includes.
ob_start();

// Definición de la zona horaria para PHP
date_default_timezone_set('America/Lima');


// Definición de constantes para los parámetros de conexión a la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'essaluds_essaluds');
define('DB_PASS', 'Ycr82061714622*');
define('DB_NAME', 'essaluds_sicuani_db');

// Intentar establecer la conexión a la base de datos
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verificar si la conexión fue exitosa
if ($conn->connect_error) {
    die("Error de conexión a la base de datos: " . $conn->connect_error);
}

// Establecer el conjunto de caracteres a UTF-8
$conn->set_charset("utf8mb4");

// Finalizar y limpiar el buffer de salida si hay algo.
ob_end_clean();

?>