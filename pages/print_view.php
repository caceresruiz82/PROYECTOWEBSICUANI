<?php
/**
 * Script para generar una vista de impresión de las citas seleccionadas.
 */

require_once '../includes/auth_guard.php';
require_once '../includes/db_connection.php';
require_once '../includes/config.php';

// Guardia de Rol
if ($_SESSION['user_role'] !== 'Admision' && $_SESSION['user_role'] !== 'Administrador') {
    // No se muestra nada si el rol es incorrecto.
    exit('Acceso denegado.');
}

if (!isset($_POST['appointment_ids']) || !is_array($_POST['appointment_ids']) || empty($_POST['appointment_ids'])) {
    die("No se ha seleccionado ninguna cita para imprimir. Por favor, regrese y seleccione al menos una.");
}

$appointment_ids = array_map('intval', $_POST['appointment_ids']);
$placeholders = implode(',', array_fill(0, count($appointment_ids), '?'));

try {
    // Obtener los datos completos de las citas seleccionadas
    $sql = "SELECT 
                a.appointment_date, a.appointment_time, a.status,
                p.full_name as patient_name, p.document_type, p.document_number,
                s.specialty_name,
                m.full_name as doctor_name
            FROM appointments a
            JOIN users p ON a.patient_id = p.user_id
            JOIN appointment_slots sl ON a.slot_id = sl.slot_id
            JOIN specialties s ON sl.specialty_id = s.specialty_id
            LEFT JOIN users m ON a.doctor_id_moderator = m.user_id
            WHERE a.appointment_id IN ($placeholders)
            ORDER BY a.appointment_date ASC, a.appointment_time ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($appointment_ids);
    $appointments = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Error en la base de datos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Imprimir Reporte de Citas</title>
    <style>
        body { font-family: Arial, sans-serif; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        h1 { text-align: center; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <h1>Reporte de Citas Programadas</h1>
    <p>Generado el: <?php echo date('d/m/Y H:i:s'); ?></p>
    
    <table>
        <thead>
            <tr>
                <th>Paciente</th>
                <th>Documento</th>
                <th>Especialidad</th>
                <th>Fecha y Hora</th>
                <th>Médico Asignado</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($appointments as $appointment): ?>
                <tr>
                    <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                    <td><?php echo htmlspecialchars($appointment['document_type'] . ': ' . $appointment['document_number']); ?></td>
                    <td><?php echo htmlspecialchars($appointment['specialty_name']); ?></td>
                    <td><?php echo htmlspecialchars(date("d/m/Y h:i A", strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']))); ?></td>
                    <td><?php echo htmlspecialchars($appointment['doctor_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $appointment['status']))); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print();">Imprimir</button>
        <button onclick="window.close();">Cerrar</button>
    </div>

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>