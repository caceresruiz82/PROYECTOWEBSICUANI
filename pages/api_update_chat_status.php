<?php
/**
 * API Endpoint para actualizar el estado de disponibilidad del chat de un usuario.
 */

header('Content-Type: application/json');
require_once '../includes/auth_guard.php'; // Usa auth_guard para asegurar que el usuario esté logueado
require_once '../includes/db_connection.php';

$response = ['success' => false, 'message' => 'Error desconocido.'];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Solo el personal puede cambiar su estado de chat
if ($user_role !== 'Medico' && $user_role !== 'Admision' && $user_role !== 'Administrador') {
    $response['message'] = 'Acción no permitida para este rol.';
    echo json_encode($response);
    exit;
}

// Obtener el nuevo estado enviado por JavaScript
$data = json_decode(file_get_contents('php://input'), true);
$new_status = isset($data['status']) && $data['status'] === 'available' ? 'available' : 'unavailable';

try {
    $stmt = $pdo->prepare("UPDATE users SET chat_status = :status WHERE user_id = :user_id");
    $stmt->execute([':status' => $new_status, ':user_id' => $user_id]);
    
    $response['success'] = true;
    $response['message'] = 'Estado del chat actualizado a ' . $new_status;
    $response['new_status'] = $new_status;

} catch (PDOException $e) {
    $response['message'] = 'Error al actualizar el estado en la base de datos.';
    http_response_code(500);
}

echo json_encode($response);