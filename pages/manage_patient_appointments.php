<?php
/**
 * Página para Admisión/Admin para gestionar citas.
 * AHORA MUESTRA EL MÉDICO ASIGNADO Y PERMITE LA REASIGNACIÓN.
 */

require_once '../includes/auth_guard.php';
require_once '../includes/db_connection.php';

// Guardia de Rol
if ($_SESSION['user_role'] !== 'Admision' && $_SESSION['user_role'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

$message = $_GET['message'] ?? '';
$message_type = $_GET['type'] ?? '';

try {
    // --- CORRECCIÓN CLAVE: Se añade un LEFT JOIN para obtener el nombre del médico moderador ---
    $sql_appointments = "SELECT 
                a.appointment_id, a.appointment_date, a.appointment_time, a.status,
                p.full_name as patient_name, 
                s.specialty_name,
                m.full_name as doctor_name
            FROM appointments a
            JOIN users p ON a.patient_id = p.user_id
            JOIN appointment_slots sl ON a.slot_id = sl.slot_id
            JOIN specialties s ON sl.specialty_id = s.specialty_id
            LEFT JOIN users m ON a.doctor_id_moderator = m.user_id
            ORDER BY 
                CASE a.status
                    WHEN 'pendiente' THEN 1
                    WHEN 'confirmada' THEN 2
                    ELSE 3
                END,
                a.appointment_date, a.appointment_time";
    $stmt_appointments = $pdo->query($sql_appointments);
    $appointments = $stmt_appointments->fetchAll();

    // Obtener la lista de médicos de la institución (sin cambios)
    $sql_doctors = "SELECT user_id, full_name FROM users WHERE role_id = 5 AND status = 'activo'";
    $stmt_doctors = $pdo->query($sql_doctors);
    $doctors = $stmt_doctors->fetchAll();

} catch (PDOException $e) {
    $appointments = [];
    $doctors = [];
    $message = "Error fatal al cargar los datos. Contacte a soporte.";
    $message_type = 'error';
}

$page_title = 'Gestionar Citas de Pacientes';
include_once '../templates/header.php';
?>

<h2><?php echo htmlspecialchars($page_title); ?></h2>
<p>Revise, asigne, reasigne y gestione las solicitudes de citas de los pacientes.</p>

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
                <th>Especialidad</th>
                <th>Fecha de Cita</th>
                <th>Médico Asignado</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($appointments)): ?>
                <tr><td colspan="6">No hay citas para gestionar.</td></tr>
            <?php else: ?>
                <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                        <td><?php echo htmlspecialchars($appointment['specialty_name']); ?></td>
                        <td><?php echo htmlspecialchars(date("d/m/Y h:i A", strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']))); ?></td>
                        <td>
                            <?php echo htmlspecialchars($appointment['doctor_name'] ?? 'No asignado'); ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo htmlspecialchars($appointment['status']); ?>">
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $appointment['status']))); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($appointment['status'] === 'pendiente'): ?>
                                <form action="update_appointment_status.php" method="POST" class="form-inline">
                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                    <select name="doctor_id_moderator" required>
                                        <option value="">-- Asignar Médico --</option>
                                        <?php foreach ($doctors as $doctor): ?>
                                            <option value="<?php echo $doctor['user_id']; ?>"><?php echo htmlspecialchars($doctor['full_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="action" value="confirm" class="btn">Confirmar</button>
                                </form>
                            <?php elseif ($appointment['status'] === 'confirmada'): ?>
                                <form action="reassign_doctor.php" method="POST" class="form-inline">
                                     <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                    <select name="new_doctor_id" required>
                                        <option value="">-- Reasignar a --</option>
                                        <?php foreach ($doctors as $doctor): ?>
                                            <option value="<?php echo $doctor['user_id']; ?>"><?php echo htmlspecialchars($doctor['full_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn">Reasignar</button>
                                </form>
                            <?php else: ?>
                                Gestionada
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style> /* Estilos (sin cambios) */ </style>

<?php
include_once '../templates/footer.php';
?>