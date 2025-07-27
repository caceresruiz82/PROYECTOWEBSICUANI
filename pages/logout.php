<?php
/**
 * logout.php
 *
 * Script para cerrar la sesión del usuario.
 * Destruye todas las variables de sesión y redirige al usuario a la página de inicio de sesión.
 *
 * @package EssaludSicuaniWeb
 * @subpackage Pages
 * @author Yudelvis Caceres
 * @version 1.0.0
 * @date 2025-07-25
 */

// Iniciar la sesión si no está iniciada.
// Es importante para poder acceder a las variables de sesión y destruirlas.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destruir todas las variables de sesión
$_SESSION = array();

// Si se desea destruir la cookie de sesión, también es necesario eliminarla.
// Nota: Esto destruirá la sesión, y no solo los datos de sesión!
// Esto suele hacerse, por ejemplo, unset($_COOKIE[session_name()]);
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir la sesión.
session_destroy();

// Redirigir al usuario a la página de inicio de sesión o a la página principal
header("Location: login.php");
exit(); // Es crucial llamar a exit() después de una redirección para asegurar que el script se detenga.

?>