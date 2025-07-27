<?php
/**
 * ajax_get_available_slots.php
 *
 * Script AJAX para obtener los turnos de disponibilidad (appointment_slots)
 * aprobados y con slots disponibles, filtrados por especialidad y fecha.
 * Utilizado por el modal de reprogramación en manage_patient_appointments.php.
 * Devuelve los resultados en formato JSON.
 *
 * @package EssaludSicuaniWeb
 * @subpackage Pages
 * @author Yudelvis Caceres
 * @version 1.0.0
 * @date 2025-07-27
 */

session_start(); // Necesario para verificar si el usuario está logueado

require_once '../includes/db_connection.php';

header('Content-Type: application/json'); // Indicar que la respuesta es JSON

// --- Protección del script AJAX ---
// Solo permitir acceso si el usuario está logueado y tiene rol de Admin o Admision
if (!isset($_SESSION['user_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 4)) {
    echo json_encode(['error' => 'Acceso denegado.']);
    exit();
}

$specialty_id = isset($_GET['specialty_id']) && is_numeric($_GET['specialty_id']) ? (int)$_GET['specialty_id'] : null;
$slot_date = isset($_GET['slot_date']) && !empty($_GET['slot_date']) ? htmlspecialchars($_GET['slot_date']) : null;

$slots = [];

if ($specialty_id && $slot_date) {
    // Consultar slots aprobados y disponibles
    $stmt = $conn->prepare("SELECT slot_id, slot_date, start_time, end_time, available_slots
                             FROM appointment_slots
                             WHERE specialty_id = ?
                               AND slot_date = ?
                               AND status = 1 -- Solo slots aprobados
                               AND available_slots > 0
                             ORDER BY start_time ASC");
    $stmt->bind_param("is", $specialty_id, $slot_date);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $slots[] = $row;
    }
    $stmt->close();
}

echo json_encode($slots); // Devolver los slots en formato JSON
$conn->close(); // Cerrar la conexión
?>