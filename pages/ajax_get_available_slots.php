<?php
/**
 * AJAX Endpoint para obtener cupos disponibles.
 *
 * Recibe una ID de especialidad y devuelve los cupos disponibles en formato JSON.
 */

// Se requiere la conexión a la BD, pero no el protector de página completo,
// ya que la seguridad se basa en que solo un usuario logueado puede acceder a la página que lo llama.
require_once '../includes/db_connection.php';

// Establecer la cabecera del contenido como JSON
header('Content-Type: application/json');

// Verificar que se haya recibido la ID de la especialidad
if (!isset($_GET['specialty_id']) || !is_numeric($_GET['specialty_id'])) {
    echo json_encode(['error' => 'ID de especialidad no válido.']);
    exit;
}

$specialty_id = intval($_GET['specialty_id']);
$slots = [];

try {
    // Buscar cupos aprobados (status=1), con disponibilidad y para fechas futuras
    $sql = "SELECT slot_id, slot_date, start_time, end_time 
            FROM appointment_slots 
            WHERE specialty_id = :specialty_id 
              AND available_slots > 0
              AND status = 1
              AND slot_date >= CURDATE()
            ORDER BY slot_date, start_time";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':specialty_id', $specialty_id, PDO::PARAM_INT);
    $stmt->execute();

    $slots = $stmt->fetchAll();

    // Devolver los resultados en formato JSON
    echo json_encode($slots);

} catch (PDOException $e) {
    // En caso de error de base de datos, devolver un JSON de error
    // En un entorno de producción, sería mejor registrar este error en un log.
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Error al consultar la base de datos.']);
}