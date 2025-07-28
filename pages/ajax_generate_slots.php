<?php
/**
 * Endpoint AJAX para generar una vista previa de los turnos individuales
 * a partir de un bloque de tiempo.
 */

header('Content-Type: application/json');
require_once '../includes/db_connection.php';
require_once '../includes/config.php'; // Para la zona horaria

// No se necesita guardia de rol completa, ya que es llamado por una página protegida.

$response = ['success' => false, 'slots' => [], 'doctors' => [], 'message' => ''];

try {
    // Obtener y validar datos de la solicitud
    $start_time_str = $_GET['start_time'] ?? '';
    $end_time_str = $_GET['end_time'] ?? '';
    $duration = filter_input(INPUT_GET, 'duration_minutes', FILTER_VALIDATE_INT);

    if (!$start_time_str || !$end_time_str || !$duration || $duration <= 0) {
        throw new Exception("Parámetros inválidos.");
    }

    $start = new DateTime($start_time_str);
    $end = new DateTime($end_time_str);
    $interval = new DateInterval("PT{$duration}M"); // Intervalo de la duración
    
    $slots = [];
    $current = clone $start;

    // Calcular cada turno individual
    while ($current < $end) {
        $slot_start = clone $current;
        $current->add($interval);
        // Asegurarse de que el final del turno no exceda la hora de fin del bloque
        if ($current > $end) {
            break;
        }
        $slot_end = clone $current;
        
        $slots[] = [
            'start_time' => $slot_start->format('H:i'),
            'end_time' => $slot_end->format('H:i')
        ];
    }
    
    // Obtener la lista de médicos para los selectores
    $doctors = $pdo->query("SELECT user_id, full_name FROM users WHERE role_id = 5 AND status = 'activo' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['slots'] = $slots;
    $response['doctors'] = $doctors;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(400); // Bad Request
}

echo json_encode($response);