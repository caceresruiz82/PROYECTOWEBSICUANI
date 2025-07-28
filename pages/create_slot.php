<?php
/**
 * Script para procesar la creación de un nuevo bloque de horarios.
 */

require_once '../includes/auth_guard.php';
require_once '../includes/db_connection.php';

// Guardia de Rol
if ($_SESSION['user_role'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$message_type = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger y validar datos del formulario
    $specialty_id = filter_input(INPUT_POST, 'specialty_id', FILTER_VALIDATE_INT);
    $slot_date = filter_input(INPUT_POST, 'slot_date', FILTER_SANITIZE_STRING);
    $start_time_str = filter_input(INPUT_POST, 'start_time', FILTER_SANITIZE_STRING);
    $end_time_str = filter_input(INPUT_POST, 'end_time', FILTER_SANITIZE_STRING);
    $duration_minutes = filter_input(INPUT_POST, 'duration_minutes', FILTER_VALIDATE_INT);
    $created_by_user_id = $_SESSION['user_id'];

    // Validaciones básicas
    if (!$specialty_id || !$slot_date || !$start_time_str || !$end_time_str || !$duration_minutes || $duration_minutes <= 0) {
        $message = 'Todos los campos son obligatorios y la duración debe ser positiva.';
    } else {
        try {
            // Convertir strings de tiempo a objetos DateTime para calcular la diferencia
            $start_time = new DateTime($start_time_str);
            $end_time = new DateTime($end_time_str);

            if ($start_time >= $end_time) {
                $message = 'La hora de inicio debe ser anterior a la hora de fin.';
            } else {
                // Calcular la diferencia total en minutos
                $interval = $start_time->diff($end_time);
                $total_minutes = ($interval->h * 60) + $interval->i;

                // Calcular cuántos cupos caben en el intervalo de tiempo
                $total_slots = floor($total_minutes / $duration_minutes);

                if ($total_slots <= 0) {
                    $message = 'El intervalo de tiempo no es suficiente para crear al menos un cupo con la duración especificada.';
                } else {
                    // Preparar la inserción en la base de datos
                    $sql = "INSERT INTO appointment_slots 
                                (specialty_id, slot_date, start_time, end_time, duration_minutes, total_slots, available_slots, created_by_user_id, status)
                            VALUES 
                                (:specialty_id, :slot_date, :start_time, :end_time, :duration_minutes, :total_slots, :available_slots, :created_by_user_id, 1)"; // Status 1 = Aprobado por defecto
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':specialty_id' => $specialty_id,
                        ':slot_date' => $slot_date,
                        ':start_time' => $start_time_str,
                        ':end_time' => $end_time_str,
                        ':duration_minutes' => $duration_minutes,
                        ':total_slots' => $total_slots,
                        ':available_slots' => $total_slots, // Al inicio, todos los cupos están disponibles
                        ':created_by_user_id' => $created_by_user_id
                    ]);

                    $message = "Bloque de horarios creado con éxito. Se generaron {$total_slots} cupos.";
                    $message_type = 'success';
                }
            }
        } catch (Exception $e) {
            $message = 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage();
        }
    }
} else {
    $message = 'Acceso no permitido.';
}

// Redirigir de vuelta a la página de gestión con el mensaje
header('Location: manage_slots.php?message=' . urlencode($message) . '&type=' . $message_type);
exit;