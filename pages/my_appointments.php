<?php
/**
 * Página "Mis Citas" para los pacientes.
 * Muestra una lista de todas las citas solicitadas por el usuario, su estado,
 * y permite cancelar las que están pendientes.
 */

require_once '../includes/auth_guard.php';
require_once '../includes/db_connection.php';

// Asegurarse de que el usuario sea un paciente
if ($_SESSION['user_role'] !== 'Paciente') {
    header('Location: dashboard.php');
    exit;
}

$patient_id = $_SESSION['user_id'];
$message = $_GET['message'] ?? '';
$message_type = $_GET['type'] ?? '';

try {
    // Consulta para obtener todas las citas del paciente con información relevante de otras tablas
    $sql = "SELECT 
                a.appointment_id,
                a.appointment_date,
                a.appointment_time,
                a.reason,
                a.status,
                s.specialty_name
            FROM appointments a
            JOIN appointment_slots sl ON a.slot_id = sl.slot_id
            JOIN specialties s ON sl.specialty_id = s.specialty_id
            WHERE a.patient_id = :patient_id
            ORDER BY a.appointment_date DESC, a.appointment_time DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':patient_id' => $patient_id]);
    $appointments = $stmt->fetchAll();

} catch (PDOException $e) {
    $appointments = [];
    $message = "Error al cargar sus citas. Por favor, intente más tarde.";
    $message_type = 'error';
    // Podrías registrar el error real en un log: error_log($e->getMessage());
}

$page_title = 'Mis Citas';
include_once '../templates/header.php';
?>

<h2><?php echo htmlspecialchars($page_title); ?></h2>
<p>Aquí puede ver el historial y el estado de todas sus citas solicitadas.</p>

<?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
        <?php echo htmlspecialchars(urldecode($message)); ?>
    </div>
<?php endif; ?>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Especialidad</th>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Motivo</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($appointments)): ?>
                <tr>
                    <td colspan="6" style="text-align:center;">No tiene ninguna cita registrada.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($appointment['specialty_name']); ?></td>
                        <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($appointment['appointment_date']))); ?></td>
                        <td><?php echo htmlspecialchars(date("h:i A", strtotime($appointment['appointment_time']))); ?></td>
                        <td><?php echo htmlspecialchars($appointment['reason']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo htmlspecialchars($appointment['status']); ?>">
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $appointment['status']))); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($appointment['status'] === 'pendiente'): ?>
                                <a href="cancel_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" 
                                   class="btn btn-danger" 
                                   onclick="return confirm('¿Está seguro de que desea cancelar esta cita?');">
                                   Cancelar
                                </a>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
    .table-container { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
    th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
    th { background-color: #f2f2f2; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    .status-badge {
        padding: 5px 10px;
        border-radius: 15px;
        color: white;
        font-size: 0.85rem;
        font-weight: 600;
    }
    .status-pendiente { background-color: #ffc107; color: #333; }
    .status-confirmada { background-color: #28a745; }
    .status-cancelada, .status-cancelada_institucional { background-color: #dc3545; }
    .status-completada { background-color: #007bff; }
    .btn-danger { padding: 5px 10px; font-size: 0.9rem; }
</style>

<?php
include_once '../templates/footer.php';
?>