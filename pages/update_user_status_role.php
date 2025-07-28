<?php
/**
 * Script para procesar la actualización del estado y rol de un usuario.
 * AHORA TAMBIÉN ENVÍA UN EMAIL CUANDO UNA CUENTA ES ACTIVADA.
 */

require_once '../includes/auth_guard.php';
require_once '../includes/db_connection.php';
require_once '../includes/mail_sender.php'; // Incluimos el enviador de correo

// Guardia de Rol: Solo los administradores pueden ejecutar esta acción.
if ($_SESSION['user_role'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$message_type = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $user_id_to_update = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $new_status = filter_input(INPUT_POST, 'new_status', FILTER_SANITIZE_STRING);
    $new_role_id = filter_input(INPUT_POST, 'new_role_id', FILTER_VALIDATE_INT);

    $valid_statuses = ['activo', 'inactivo', 'pendiente'];

    if (!$user_id_to_update || !$new_status || !$new_role_id || !in_array($new_status, $valid_statuses)) {
        $message = 'Datos inválidos o faltantes.';
    } elseif ($user_id_to_update == $_SESSION['user_id']) {
        $message = 'No puede modificar su propio estado o rol desde esta interfaz.';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. OBTENER EL ESTADO ACTUAL Y DATOS DEL USUARIO ANTES DE ACTUALIZAR
            $sql_fetch = "SELECT email, full_name, status FROM users WHERE user_id = :user_id";
            $stmt_fetch = $pdo->prepare($sql_fetch);
            $stmt_fetch->execute([':user_id' => $user_id_to_update]);
            $user_data = $stmt_fetch->fetch();
            $current_status = $user_data['status'] ?? null;

            // 2. ACTUALIZAR EL USUARIO EN LA BASE DE DATOS
            $sql_update = "UPDATE users SET status = :status, role_id = :role_id WHERE user_id = :user_id";
            $stmt_update = $pdo->prepare($sql_update);
            
            $stmt_update->execute([
                ':status' => $new_status,
                ':role_id' => $new_role_id,
                ':user_id' => $user_id_to_update
            ]);

            // 3. ENVIAR CORREO SI EL ESTADO CAMBIÓ DE 'PENDIENTE' A 'ACTIVO'
            if ($current_status === 'pendiente' && $new_status === 'activo') {
                $subject = "¡Tu cuenta en " . APP_NAME . " ha sido activada!";
                $body = "<h1>¡Hola, " . htmlspecialchars($user_data['full_name']) . "!</h1>
                         <p>Te informamos que tu cuenta en el portal de citas de EsSalud Sicuani ha sido revisada y <strong>activada</strong> por nuestro personal.</p>
                         <p>Ya puedes iniciar sesión y comenzar a solicitar tus citas.</p>
                         <p><a href='" . APP_URL . "/pages/login.php' style='padding: 10px 15px; background-color: #0056A0; color: white; text-decoration: none; border-radius: 5px;'>Iniciar Sesión Ahora</a></p>
                         <p>Atentamente,<br>El equipo de " . APP_NAME . "</p>";
                
                // Usamos nuestra función para enviar el correo
                send_email($user_data['email'], $user_data['full_name'], $subject, $body);
            }

            $pdo->commit();
            $message = 'El usuario ha sido actualizado correctamente.';
            $message_type = 'success';

        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = 'Error al actualizar el usuario en la base de datos.';
            // error_log($e->getMessage());
        }
    }
} else {
    $message = 'Acceso no permitido.';
}

// Redirigir de vuelta a la página de gestión con un mensaje de estado
header('Location: manage_users.php?message=' . urlencode($message) . '&type=' . $message_type);
exit;