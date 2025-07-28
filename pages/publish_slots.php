<?php
/**
 * Script para procesar y guardar en la base de datos los turnos de disponibilidad
 * seleccionados por el administrador.
 */

require_once '../includes/auth_guard.php';
require_once '../includes/db_connection.php';

// Guardia de Rol
if ($_SESSION['user_role'] !== 'Admision' && $_SESSION['user_role'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$message_type = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recibir los datos principales del formulario (enviados como campos ocultos)
    $specialty_id = filter_input(INPUT_POST, 'specialty_id', FILTER_VALIDATE_INT);
    $slot_date = filter_input(INPUT_POST, 'slot_date', FILTER_SANITIZE_STRING);
    $duration_minutes = filter_input(INPUT_POST, 'duration_minutes', FILTER_VALIDATE_INT);
    
    // Recibir el array de turnos
    $slots = $_POST['slots'] ?? [];
    
    $created_by_user_id = $_SESSION['user_id'];
    $slots_published_count = 0;

    if (!$specialty_id || !$slot_date || !$duration_minutes || empty($slots)) {
        $message = 'Faltan datos para publicar la disponibilidad. Por favor, genere los turnos primero.';
    } else {
        try {
            $pdo->beginTransaction();

            $sql = "INSERT INTO appointment_slots 
                        (specialty_id, slot_date, start_time, end_time, duration_minutes, 
                         total_slots, available_slots, created_by_user_id, status, 
                         assigned_doctor_id, approval_date, approved_by_user_id)
                    VALUES 
                        (:specialty_id, :slot_date, :start_time, :end_time, :duration_minutes, 
                         1, 1, :created_by_user_id, 1, 
                         :assigned_doctor_id, NOW(), :approved_by_user_id)";
            
            $stmt = $pdo->prepare($sql);

            foreach ($slots as $slot) {
                // Solo procesar los turnos que fueron marcados con el checkbox
                if (isset($slot['enabled']) && $slot['enabled'] == '1') {
                    // Si no se asignó un médico, el valor será NULL
                    $assigned_doctor_id = !empty($slot['doctor_id']) ? $slot['doctor_id'] : null;

                    $stmt->execute([
                        ':specialty_id' => $specialty_id,
                        ':slot_date' => $slot_date,
                        ':start_time' => $slot['start_time'],
                        ':end_time' => $slot['end_time'],
                        ':duration_minutes' => $duration_minutes,
                        ':created_by_user_id' => $created_by_user_id,
                        ':assigned_doctor_id' => $assigned_doctor_id,
                        ':approved_by_user_id' => $created_by_user_id // La misma persona que crea, aprueba.
                    ]);
                    $slots_published_count++;
                }
            }

            $pdo->commit();
            
            if ($slots_published_count > 0) {
                $message = "Se han publicado exitosamente {$slots_published_count} nuevos turnos de atención.";
                $message_type = 'success';
            } else {
                $message = "No se seleccionó ningún turno para publicar.";
                $message_type = 'info';
            }

        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Error en la base de datos al publicar los turnos. Es posible que un turno en ese horario ya exista.";
            // Para depuración: $message .= " " . $e->getMessage();
        }
    }
} else {
    $message = 'Acceso no permitido.';
}

// Redirigir de vuelta a la página de programación con un mensaje de estado
header('Location: schedule_availability.php?message=' . urlencode($message) . '&type=' . $message_type);
exit;