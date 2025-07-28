<?php
/**
 * Script para manejar la cancelación de una cita por parte de un paciente.
 */

require_once '../includes/auth_guard.php';
require_once '../includes/db_connection.php';

// Asegurarse de que el usuario sea un paciente
if ($_SESSION['user_role'] !== 'Paciente') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$message_type = 'error'; // Tipo de mensaje por defecto

// Verificar que se haya proporcionado un ID de cita
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $message = 'ID de cita no válido.';
    header('Location: my_appointments.php?message=' . urlencode($message) . '&type=' . $message_type);
    exit;
}

$appointment_id = intval($_GET['id']);
$patient_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    // 1. Verificar que la cita pertenezca al usuario y esté en estado 'pendiente'
    $sql_check = "SELECT slot_id FROM appointments WHERE appointment_id = :appointment_id AND patient_id = :patient_id AND status = 'pendiente'";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([
        ':appointment_id' => $appointment_id,
        ':patient_id' => $patient_id
    ]);
    $appointment = $stmt_check->fetch();

    if ($appointment) {
        $slot_id = $appointment['slot_id'];

        // 2. Actualizar el estado de la cita a 'cancelada'
        $sql_update = "UPDATE appointments SET status = 'cancelada' WHERE appointment_id = :appointment_id";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([':appointment_id' => $appointment_id]);

        // 3. Devolver el cupo a la lista de disponibles
        $sql_slot = "UPDATE appointment_slots SET available_slots = available_slots + 1 WHERE slot_id = :slot_id";
        $stmt_slot = $pdo->prepare($sql_slot);
        $stmt_slot->execute([':slot_id' => $slot_id]);

        // Si todo fue bien, confirmar los cambios
        $pdo->commit();
        $message = 'Su cita ha sido cancelada exitosamente.';
        $message_type = 'success';
    } else {
        // Si no se encuentra la cita o no está pendiente, no hacer nada y notificar
        $pdo->rollBack();
        $message = 'No se pudo cancelar la cita. Es posible que ya haya sido procesada o no le pertenezca.';
    }

} catch (PDOException $e) {
    // Si hay un error de base de datos, revertir todo
    $pdo->rollBack();
    $message = 'Ocurrió un error al procesar su solicitud.';
    // error_log($e->getMessage()); // Registrar el error
}

// Redirigir de vuelta a la página "Mis Citas" con el mensaje
header('Location: my_appointments.php?message=' . urlencode($message) . '&type=' . $message_type);
exit;