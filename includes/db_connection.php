<?php
/**
 * Conexión a la Base de Datos con PDO
 *
 * Este script establece una conexión segura a la base de datos utilizando PDO,
 * que previene inyecciones SQL mediante el uso de sentencias preparadas.
 */

// 1. Incluir el archivo de configuración
// require_once se asegura de que el archivo se incluya una sola vez y detiene el script si no lo encuentra.
require_once 'config.php';

// 2. Definir el DSN (Data Source Name)
// El DSN le dice a PDO a qué driver conectarse y la información de la conexión.
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

// 3. Configurar las opciones de PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanza excepciones en caso de error, lo que permite un mejor manejo.
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Devuelve los resultados como un array asociativo.
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Desactiva la emulación de sentencias preparadas para usar las nativas del motor de BD.
];

// 4. Crear la instancia de PDO (la conexión)
try {
    // Intentamos crear la conexión
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Si la conexión falla, se captura la excepción y se muestra un mensaje de error genérico.
    // En un entorno de producción, podrías registrar este error en un archivo en lugar de mostrarlo.
    // La directiva 'die()' detiene la ejecución del script por completo.
    die("Error: No se pudo conectar a la base de datos. Por favor, contacte al administrador.");
}

// La variable $pdo ahora está disponible para ser usada en cualquier script que incluya este archivo.
?>