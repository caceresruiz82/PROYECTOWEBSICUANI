<?php
/**
 * Script para procesar la confirmación o cancelación de una cita por parte de Admisión.
 * Guarda el ID del médico moderador asignado.
 * Envía notificaciones por Email y SMS.
 */

require_once '../includes/auth_guard.php';
require_once '../includes/db_connection.php';
require_once '../includes/mail_sender.php';
require_once '../includes/sms_sender.php'; // Incluimos el enviador de SMS

// Guardia de Rol: Solo Admision o Administradores pueden acceder.
if ($_SESSION['user_role'] !== 'Admision' && $_SESSION['user_role'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$message_type = 'error';

// Verificar que la solicitud sea por método POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
    $doctor_id_moderator = filter_input(INPUT_POST, 'doctor_id_moderator', FILTER_VALIDATE_INT);

    // Validar datos de entrada
    if (!$appointment_id || !$action) {
        $message = 'Datos inválidos o acción no especificada.';
    } elseif ($action === 'confirm' && !$doctor_id_moderator) {
        $message = 'Debe asignar un médico moderador para poder confirmar la cita.';
    } else {
        try {
            $pdo->beginTransaction();

            // Obtener todos los datos necesarios de la cita y del paciente en una sola consulta
            $sql_fetch = "SELECT 
                            a.slot_id, a.status, a.appointment_date, a.appointment_time,
                            p.full_name as patient_name, p.email as patient_email, p.phone_number,
                            s.specialty_name
                        FROM appointments a
                        JOIN users p ON a.patient_id = p.user_id
                        JOIN appointment_slots sl ON a.slot_id = sl.slot_id
                        JOIN specialties s ON sl.specialty_id = s.specialty_id
                        WHERE a.appointment_id = :appointment_id AND a.status = 'pendiente'";
            
            $stmt_fetch = $pdo->prepare($sql_fetch);
            $stmt_fetch->execute([':appointment_id' => $appointment_id]);
            $appointment_data = $stmt_fetch->fetch();

            if ($appointment_data) {
                $patient_phone = $appointment_data['phone_number'];
                $formatted_date = date("d/m/Y", strtotime($appointment_data['appointment_date']));
                $formatted_time = date("h:i A", strtotime($appointment_data['appointment_time']));

                if ($action === 'confirm') {
                    // Actualizar la cita a 'confirmada' y asignar el médico
                    $sql_update = "UPDATE appointments SET status = 'confirmada', doctor_id_moderator = :doctor_id WHERE appointment_id = :appointment_id";
                    $stmt_update = $pdo->prepare($sql_update);
                    $stmt_update->execute([
                        ':doctor_id' => $doctor_id_moderator,
                        ':appointment_id' => $appointment_id
                    ]);
                    
                    // Enviar correo de confirmación
                    $subject_mail = "Cita Confirmada en " . APP_NAME;
                    $body_mail = "<h1>¡Hola, " . htmlspecialchars($appointment_data['patient_name']) . "!</h1><p>Tu cita ha sido <strong>confirmada</strong>.</p><h3>Detalles:</h3><ul><li><strong>Especialidad:</strong> " . htmlspecialchars($appointment_data['specialty_name']) . "</li><li><strong>Fecha:</strong> " . htmlspecialchars($formatted_date) . "</li><li><strong>Hora:</strong> " . htmlspecialchars($formatted_time) . "</li></ul>";
                    send_email($appointment_data['patient_email'], $appointment_data['patient_name'], $subject_mail, $body_mail);

                    // Enviar SMS de confirmación
                    if (!empty($patient_phone)) {
                        $sms_message = "Estimado paciente, su cita para el {$formatted_date} a las {$formatted_time} ha sido CONFIRMADA. EsSalud Sicuani.";
                        send_sms($patient_phone, $sms_message);
                    }

                    $message = 'Cita confirmada y notificaciones enviadas exitosamente.';
                    $message_type = 'success';

                } elseif ($action === 'cancel') {
                    // Actualizar la cita a 'cancelada_institucional'
                    $sql_update = "UPDATE appointments SET status = 'cancelada_institucional' WHERE appointment_id = :appointment_id";
                    $stmt_update = $pdo->prepare($sql_update);
                    $stmt_update->execute([':appointment_id' => $appointment_id]);
                    
                    // Devolver el cupo a la lista de disponibles
                    $slot_id = $appointment_data['slot_id'];
                    $sql_slot = "UPDATE appointment_slots SET available_slots = available_slots + 1 WHERE slot_id = :slot_id";
                    $stmt_slot = $pdo->prepare($sql_slot);
                    $stmt_slot->execute([':slot_id' => $slot_id]);
                    
                    // Enviar SMS de cancelación
                    if (!empty($patient_phone)) {
                        $sms_message = "Estimado paciente, su cita para el {$formatted_date} a las {$formatted_time} ha sido CANCELADA. Para mas informacion contacte a soporte. EsSalud Sicuani.";
                        send_sms($patient_phone, $sms_message);
                    }
                    
                    $message = 'La cita ha sido cancelada, el cupo ha sido liberado y el paciente notificado.';
                    $message_type = 'success';
                }
            } else {
                $message = 'La cita no se encontró o ya fue gestionada previamente.';
            }

            $pdo->commit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = 'Ocurrió un error en la base de datos durante la operación.';
            // error_log($e->getMessage()); // Descomentar para depuración en el servidor
        }
    }
} else {
    $message = 'Acceso no permitido.';
}

// Redirigir de vuelta a la página de gestión de citas
header('Location: manage_patient_appointments.php?message=' . urlencode($message) . '&type=' . $message_type);
exit;