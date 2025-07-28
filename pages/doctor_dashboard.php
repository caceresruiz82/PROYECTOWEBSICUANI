<?php
/**
 * Panel de Control para el Médico Moderador.
 * Muestra las citas asignadas y permite gestionarlas.
 */

require_once '../includes/auth_guard.php';
require_once '../includes/db_connection.php';

// Guardia de Rol: Solo los médicos pueden acceder.
if ($_SESSION['user_role'] !== 'Medico') {
    header('Location: dashboard.php');
    exit;
}

$doctor_id = $_SESSION['user_id'];
$message = $_GET['message'] ?? '';
$message_type = $_GET['type'] ?? '';

try {
    // Consulta para obtener las citas asignadas a este médico moderador
    $sql = "SELECT 
                a.appointment_id,
                a.appointment_date,
                a.appointment_time,
                a.reason,
                a.status,
                a.notes,
                p.full_name as patient_name,
                p.document_type,
                p.document_number,
                s.specialty_name
            FROM appointments a
            JOIN users p ON a.patient_id = p.user_id
            JOIN appointment_slots sl ON a.slot_id = sl.slot_id
            JOIN specialties s ON sl.specialty_id = s.specialty_id
            WHERE a.doctor_id_moderator = :doctor_id
            ORDER BY 
                CASE a.status
                    WHEN 'confirmada' THEN 1
                    WHEN 'completada' THEN 2
                    ELSE 3
                END,
                a.appointment_date, a.appointment_time";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':doctor_id' => $doctor_id]);
    $appointments = $stmt->fetchAll();

} catch (PDOException $e) {
    $appointments = [];
    $message = "Error al cargar sus citas asignadas.";
    $message_type = 'error';
    // error_log($e->getMessage());
}

$page_title = 'Panel de Médico Moderador';
include_once '../templates/header.php';
?>

<h2><?php echo htmlspecialchars($page_title); ?></h2>
<p>Bienvenido(a), <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>. Aquí puede ver y gestionar las teleconsultas que tiene asignadas.</p>

<?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
        <?php echo htmlspecialchars(urldecode($message)); ?>
    </div>
<?php endif; ?>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Paciente</th>
                <th>Documento</th>
                <th>Especialidad</th>
                <th>Fecha y Hora</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($appointments)): ?>
                <tr>
                    <td colspan="6" style="text-align:center;">No tiene ninguna cita asignada.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                        <td><?php echo htmlspecialchars($appointment['document_type']) . ': ' . htmlspecialchars($appointment['document_number']); ?></td>
                        <td><?php echo htmlspecialchars($appointment['specialty_name']); ?></td>
                        <td><?php echo htmlspecialchars(date("d/m/Y h:i A", strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']))); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo htmlspecialchars($appointment['status']); ?>">
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $appointment['status']))); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($appointment['status'] === 'confirmada'): ?>
                                <form action="complete_appointment.php" method="POST">
                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                    <textarea name="notes" rows="2" placeholder="Añadir notas post-consulta..." style="width: 100%; margin-bottom: 5px;"></textarea>
                                    <button type="submit" class="btn">Marcar como Completada</button>
                                </form>
                            <?php elseif ($appointment['status'] === 'completada' && !empty($appointment['notes'])): ?>
                                <p><strong>Notas:</strong> <?php echo htmlspecialchars($appointment['notes']); ?></p>
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

<?php
include_once '../templates/footer.php';
?>