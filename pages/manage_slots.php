<?php
/**
 * Página para que los administradores gestionen los bloques de horarios para citas.
 */

require_once '../includes/auth_guard.php';
require_once '../includes/db_connection.php';

// Guardia de Rol: Solo los administradores pueden acceder.
if ($_SESSION['user_role'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

$message = $_GET['message'] ?? '';
$message_type = $_GET['type'] ?? '';

try {
    // Obtener las especialidades para el formulario
    $sql_specialties = "SELECT specialty_id, specialty_name FROM specialties ORDER BY specialty_name";
    $specialties = $pdo->query($sql_specialties)->fetchAll();

    // Obtener los bloques de horarios ya creados
    $sql_slots = "SELECT 
                    sl.slot_id,
                    s.specialty_name,
                    sl.slot_date,
                    sl.start_time,
                    sl.end_time,
                    sl.total_slots,
                    sl.available_slots,
                    u.full_name as created_by
                  FROM appointment_slots sl
                  JOIN specialties s ON sl.specialty_id = s.specialty_id
                  JOIN users u ON sl.created_by_user_id = u.user_id
                  ORDER BY sl.slot_date DESC, sl.start_time";
    $slots = $pdo->query($sql_slots)->fetchAll();

} catch (PDOException $e) {
    $specialties = [];
    $slots = [];
    $message = "Error fatal al cargar los datos. Contacte a soporte.";
    $message_type = 'error';
    // error_log($e->getMessage());
}

$page_title = 'Gestionar Horarios de Atención';
include_once '../templates/header.php';
?>

<h2><?php echo htmlspecialchars($page_title); ?></h2>
<p>Aquí puede crear nuevos bloques de horarios para que los pacientes soliciten citas y ver los bloques existentes.</p>

<?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
        <?php echo htmlspecialchars(urldecode($message)); ?>
    </div>
<?php endif; ?>

<div class="form-container" style="margin-bottom: 2rem; padding: 1.5rem; background-color: #f9f9f9; border-radius: 8px;">
    <h3>Crear Nuevo Bloque de Horarios</h3>
    <form action="create_slot.php" method="POST" class="form-grid">
        <div>
            <label for="specialty_id">Especialidad:</label>
            <select name="specialty_id" id="specialty_id" required>
                <option value="">-- Seleccione --</option>
                <?php foreach ($specialties as $specialty): ?>
                    <option value="<?php echo $specialty['specialty_id']; ?>"><?php echo htmlspecialchars($specialty['specialty_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="slot_date">Fecha:</label>
            <input type="date" name="slot_date" id="slot_date" required>
        </div>
        <div>
            <label for="start_time">Hora de Inicio:</label>
            <input type="time" name="start_time" id="start_time" required>
        </div>
        <div>
            <label for="end_time">Hora de Fin:</label>
            <input type="time" name="end_time" id="end_time" required>
        </div>
        <div>
            <label for="duration_minutes">Duración de cada cita (minutos):</label>
            <input type="number" name="duration_minutes" id="duration_minutes" value="20" min="5" required>
        </div>
        <div class="form-full-width">
            <button type="submit" class="btn">Crear Horarios</button>
        </div>
    </form>
</div>

<h3>Horarios Programados</h3>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Especialidad</th>
                <th>Fecha</th>
                <th>Horario</th>
                <th>Cupos Totales</th>
                <th>Cupos Disponibles</th>
                <th>Creado por</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($slots)): ?>
                <tr><td colspan="6">No hay horarios programados.</td></tr>
            <?php else: ?>
                <?php foreach ($slots as $slot): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($slot['specialty_name']); ?></td>
                        <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($slot['slot_date']))); ?></td>
                        <td><?php echo htmlspecialchars(date("h:i A", strtotime($slot['start_time']))) . ' - ' . htmlspecialchars(date("h:i A", strtotime($slot['end_time']))); ?></td>
                        <td><?php echo htmlspecialchars($slot['total_slots']); ?></td>
                        <td><?php echo htmlspecialchars($slot['available_slots']); ?></td>
                        <td><?php echo htmlspecialchars($slot['created_by']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
    .form-full-width { grid-column: 1 / -1; }
</style>
<?php
include_once '../templates/footer.php';
?>