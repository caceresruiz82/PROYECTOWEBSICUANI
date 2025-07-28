<?php
/**
 * API Endpoint central para todas las operaciones del chat.
 *
 * Acciones soportadas (vía POST):
 * - ping: Actualiza el estado de actividad del usuario actual.
 * - get_contacts: Devuelve la lista de contactos para el usuario actual.
 * - get_messages: Devuelve los mensajes de una conversación específica.
 * - send_message: Envía un nuevo mensaje a un destinatario.
 */

header('Content-Type: application/json');
require_once '../includes/auth_guard.php';
require_once '../includes/db_connection.php';

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$response = ['success' => false, 'message' => 'Acción no válida.'];
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

try {
    switch ($action) {
        case 'ping':
            $stmt = $pdo->prepare("INSERT INTO user_activity (user_id, last_seen) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE last_seen = NOW()");
            $stmt->execute([$user_id]);
            $response = ['success' => true];
            break;

        case 'get_contacts':
            $contacts = [];
            if ($user_role === 'Paciente') {
                // Un paciente puede chatear con Admins/Admision y con los médicos asignados a sus citas
                $sql = "SELECT u.user_id, u.full_name, r.role_name, u.chat_status, ua.last_seen
                        FROM users u
                        JOIN roles r ON u.role_id = r.role_id
                        LEFT JOIN user_activity ua ON u.user_id = ua.user_id
                        WHERE (r.role_name IN ('Administrador', 'Admision')) OR 
                              (u.user_id IN (SELECT DISTINCT doctor_id_moderator FROM appointments WHERE patient_id = ? AND doctor_id_moderator IS NOT NULL))";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user_id]);
                $contacts = $stmt->fetchAll();
            } else {
                // El personal (Admin/Admision/Medico) puede ver a TODOS los pacientes.
                $sql = "SELECT u.user_id, u.full_name, r.role_name, u.chat_status, ua.last_seen
                        FROM users u
                        JOIN roles r ON u.role_id = r.role_id
                        LEFT JOIN user_activity ua ON u.user_id = ua.user_id
                        WHERE r.role_name = 'Paciente'";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $contacts = $stmt->fetchAll();
            }
            $response = ['success' => true, 'contacts' => $contacts];
            break;

        case 'get_messages':
            $contact_id = filter_var($data['contact_id'], FILTER_VALIDATE_INT);
            if (!$contact_id) { throw new Exception("ID de contacto no válido."); }

            $patient_id = ($user_role === 'Paciente') ? $user_id : $contact_id;
            $staff_id = ($user_role !== 'Paciente') ? $user_id : $contact_id;

            $stmt_thread = $pdo->prepare("SELECT thread_id FROM chat_threads WHERE patient_id = ? AND staff_id = ?");
            $stmt_thread->execute([$patient_id, $staff_id]);
            $thread = $stmt_thread->fetch();
            $messages = [];
            if ($thread) {
                $thread_id = $thread['thread_id'];
                $stmt_messages = $pdo->prepare("SELECT message_id, sender_id, message_text, sent_at FROM chat_messages WHERE thread_id = ? ORDER BY sent_at ASC");
                $stmt_messages->execute([$thread_id]);
                $messages = $stmt_messages->fetchAll();
                
                $stmt_read = $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE thread_id = ? AND sender_id = ? AND is_read = 0");
                $stmt_read->execute([$thread_id, $contact_id]);
            }
            $response = ['success' => true, 'messages' => $messages];
            break;
            
        case 'send_message':
            $recipient_id = filter_var($data['recipient_id'], FILTER_VALIDATE_INT);
            $message_text = trim(filter_var($data['message_text'], FILTER_SANITIZE_STRING));
            if (!$recipient_id || empty($message_text)) { throw new Exception("Faltan datos para enviar el mensaje."); }
            
            $patient_id = ($user_role === 'Paciente') ? $user_id : $recipient_id;
            $staff_id = ($user_role !== 'Paciente') ? $user_id : $recipient_id;

            $pdo->beginTransaction();
            $stmt_thread = $pdo->prepare("SELECT thread_id FROM chat_threads WHERE patient_id = ? AND staff_id = ?");
            $stmt_thread->execute([$patient_id, $staff_id]);
            $thread = $stmt_thread->fetch();
            if (!$thread) {
                $stmt_create_thread = $pdo->prepare("INSERT INTO chat_threads (patient_id, staff_id) VALUES (?, ?)");
                $stmt_create_thread->execute([$patient_id, $staff_id]);
                $thread_id = $pdo->lastInsertId();
            } else {
                $thread_id = $thread['thread_id'];
            }
            $stmt_insert = $pdo->prepare("INSERT INTO chat_messages (thread_id, sender_id, message_text) VALUES (?, ?, ?)");
            $stmt_insert->execute([$thread_id, $user_id, $message_text]);
            $pdo->commit();

            $response = ['success' => true, 'message' => 'Mensaje enviado.'];
            break;

        default:
            http_response_code(400);
            $response['message'] = 'Acción no reconocida.';
            break;
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);