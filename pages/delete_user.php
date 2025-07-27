<?php
/**
 * delete_user.php
 *
 * Script para eliminar un usuario de la base de datos.
 * Esta página solo es accesible y ejecutable por usuarios con rol de Administrador.
 * Recibe el ID del usuario a eliminar y lo procesa.
 *
 * @package EssaludSicuaniWeb
 * @subpackage Pages
 * @author Yudelvis Caceres
 * @version 1.0.0
 * @date 2025-07-25
 */

session_start();

require_once '../includes/db_connection.php';

// --- Protección de la Página ---
// 1. Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Verificar si el usuario tiene el rol de Administrador (role_id = 1)
if ($_SESSION['role_id'] != 1) {
    // Si no es administrador, redirigir a un dashboard genérico o de acceso denegado
    header("Location: dashboard.php");
    exit();
}

$message = '';

// Verificar si se recibió un ID de usuario para eliminar
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id_to_delete = (int)$_GET['id'];

    // Opcional: Impedir que el administrador se elimine a sí mismo
    if ($user_id_to_delete === $_SESSION['user_id']) {
        $_SESSION['delete_message'] = '<div class="alert alert-danger">No puedes eliminar tu propia cuenta de administrador desde aquí.</div>';
        header("Location: manage_users.php");
        exit();
    }

    // Preparar la consulta SQL para eliminar el usuario
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id_to_delete);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['delete_message'] = '<div class="alert alert-success">Usuario eliminado exitosamente.</div>';
        } else {
            $_SESSION['delete_message'] = '<div class="alert alert-warning">No se encontró el usuario con el ID especificado.</div>';
        }
    } else {
        $_SESSION['delete_message'] = '<div class="alert alert-danger">Error al eliminar el usuario: ' . $stmt->error . '</div>';
    }
    $stmt->close();
} else {
    $_SESSION['delete_message'] = '<div class="alert alert-danger">ID de usuario no especificado o inválido.</div>';
}

// Redirigir de vuelta a la página de gestión de usuarios
header("Location: manage_users.php");
exit();

?>