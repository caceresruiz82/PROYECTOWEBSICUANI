<?php
/**
 * Script para que el médico moderador marque una cita como completada y añada notas.
 */

require_once '../includes/auth_guard.php';
require_once '../includes/db_connection.php';

// Guardia de Rol
if ($_SESSION['user_role'] !== 'Medico') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$message_type = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
    $notes = trim(filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING));
    $doctor_id = $_SESSION['user_id'];

    if (!$appointment_id) {
        $message = 'ID de cita no válido.';
    } else {
        try {
            $pdo->beginTransaction();

            // Verificación de seguridad: Asegurarse de que la cita le pertenece a este médico
            $sql_check = "SELECT appointment_id FROM appointments WHERE appointment_id = :appointment_id AND doctor_id_moderator = :doctor_id AND status = 'confirmada'";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([':appointment_id' => $appointment_id, ':doctor_id' => $doctor_id]);
            
            if ($stmt_check->fetch()) {
                // Actualizar la cita
                $sql_update = "UPDATE appointments SET status = 'completada', notes = :notes WHERE appointment_id = :appointment_id";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([':notes' => $notes,':appointment_id' => $appointment_id]);

                $pdo->commit();
                $message = 'La cita ha sido marcada como completada exitosamente.';
                $message_type = 'success';
            } else {
                $pdo->rollBack();
                $message = 'Acción no permitida. La cita no está asignada a usted o no está en estado "confirmada".';
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = 'Ocurrió un error en la base de datos.';
        }
    }
} else {
    $message = 'Acceso no permitido.';
}

header('Location: doctor_dashboard.php?message=' . urlencode($message) . '&type=' . $message_type);
exit;