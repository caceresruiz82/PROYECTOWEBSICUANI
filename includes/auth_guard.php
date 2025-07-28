<?php
/**
 * Auth Guard (Protector de Páginas)
 *
 * Este script verifica si un usuario ha iniciado sesión.
 * Si no hay una sesión activa, lo redirige a la página de login.
 * Debe ser incluido al principio de cualquier página que requiera autenticación.
 */

// Asegurarnos de que la sesión esté iniciada.
if (session_status() === PHP_SESSION_NONE) {
    // Especificar una ruta de guardado de sesiones personalizada y escribible
    ini_set('session.save_path', realpath(dirname(__FILE__) . '/sessions'));
    session_start();
}

// Comprobar si la variable de sesión del ID de usuario no está establecida.
if (!isset($_SESSION['user_id'])) {
    // Si no está establecida, el usuario no ha iniciado sesión.
    // Redirigir a la página de login.
    header('Location: login.php');
    // Detener la ejecución del script para asegurarse de que no se cargue más contenido.
    exit();
}
?>