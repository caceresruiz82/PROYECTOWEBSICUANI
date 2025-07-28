<?php
/**
 * Script para procesar la reasignación de un médico moderador a una cita.
 */

require_once '../includes/auth_guard.php';
require_once '../includes/db_connection.php';
// require_once '../includes/mail_sender.php'; // Opcional: para notificar al nuevo médico

// Guardia de Rol
if ($_SESSION['user_role'] !== 'Admision' && $_SESSION['user_role'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$message_type = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
    $new_doctor_id = filter_input(INPUT_POST, 'new_doctor_id', FILTER_VALIDATE_INT);

    if (!$appointment_id || !$new_doctor_id) {
        $message = 'Datos inválidos para la reasignación.';
    } else {
        try {
            // Consulta para reasignar el médico
            $sql = "UPDATE appointments SET doctor_id_moderator = :new_doctor_id WHERE appointment_id = :appointment_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':new_doctor_id' => $new_doctor_id,
                ':appointment_id' => $appointment_id
            ]);

            // Idea de mejora: Aquí podríamos enviar un email de notificación al nuevo médico.

            $message = 'El médico ha sido reasignado exitosamente.';
            $message_type = 'success';

        } catch (PDOException $e) {
            $message = 'Ocurrió un error en la base de datos al reasignar el médico.';
            // error_log($e->getMessage());
        }
    }
} else {
    $message = 'Acceso no permitido.';
}

header('Location: manage_patient_appointments.php?message=' . urlencode($message) . '&type=' . $message_type);
exit;