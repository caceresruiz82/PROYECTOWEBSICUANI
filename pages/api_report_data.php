<?php
/**
 * API Endpoint para proveer datos para los gráficos de reportes.
 */

// Este script no necesita la protección de sesión completa, pero sí la conexión a la BD.
// La seguridad se maneja en la página que lo llama (reports.php).
header('Content-Type: application/json');
require_once '../includes/db_connection.php';
require_once '../includes/config.php'; // Para la zona horaria

// Obtener y validar las fechas del filtro
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$response = [
    'statusReport' => [],
    'specialtiesReport' => []
];

try {
    // 1. Reporte de Citas por Estado
    $sql_status = "SELECT status, COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN :start_date AND :end_date GROUP BY status";
    $stmt_status = $pdo->prepare($sql_status);
    $stmt_status->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $status_data = $stmt_status->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $data = [];
    foreach($status_data as $row) {
        $labels[] = ucfirst(str_replace('_', ' ', $row['status']));
        $data[] = $row['count'];
    }
    $response['statusReport'] = [
        'labels' => $labels,
        'data' => $data
    ];


    // 2. Reporte de Especialidades más Solicitadas
    $sql_specialties = "SELECT s.specialty_name, COUNT(a.appointment_id) as count 
                        FROM appointments a
                        JOIN appointment_slots sl ON a.slot_id = sl.slot_id
                        JOIN specialties s ON sl.specialty_id = s.specialty_id
                        WHERE a.appointment_date BETWEEN :start_date AND :end_date
                        GROUP BY s.specialty_name
                        ORDER BY count DESC
                        LIMIT 5";
    $stmt_specialties = $pdo->prepare($sql_specialties);
    $stmt_specialties->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $specialties_data = $stmt_specialties->fetchAll(PDO::FETCH_ASSOC);

    $labels_spec = [];
    $data_spec = [];
    foreach($specialties_data as $row) {
        $labels_spec[] = $row['specialty_name'];
        $data_spec[] = $row['count'];
    }
     $response['specialtiesReport'] = [
        'labels' => $labels_spec,
        'data' => $data_spec
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al generar los datos del reporte.']);
}