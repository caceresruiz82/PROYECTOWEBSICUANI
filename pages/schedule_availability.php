<?php
/**
 * Página para programar la disponibilidad de turnos.
 */

require_once '../includes/auth_guard.php';
require_once '../includes/db_connection.php';

// Guardia de Rol
if ($_SESSION['user_role'] !== 'Admision' && $_SESSION['user_role'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

try {
    // Obtener las especialidades para el formulario
    $specialties = $pdo->query("SELECT specialty_id, specialty_name FROM specialties ORDER BY specialty_name")->fetchAll();
    // Obtener los médicos para el formulario
    $doctors = $pdo->query("SELECT user_id, full_name FROM users WHERE role_id = 5 AND status = 'activo' ORDER BY full_name")->fetchAll();
} catch (PDOException $e) {
    $specialties = [];
    $doctors = [];
    $error_message = "Error al cargar los datos necesarios.";
}

$page_title = 'Programar Disponibilidad de Citas';
include_once '../templates/header.php';
?>

<h2><?php echo htmlspecialchars($page_title); ?></h2>
<p>Utilice este formulario para generar los turnos que estarán disponibles para los pacientes.</p>

<?php if (isset($error_message)): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<div class="form-container" style="margin-bottom: 2rem;">
    <h3>1. Definir Bloque de Horarios</h3>
    <form id="generate-slots-form">
        <div class="form-grid">
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
                <input type="time" name="start_time" id="start_time" required step="300">
            </div>
            <div>
                <label for="end_time">Hora de Fin:</label>
                <input type="time" name="end_time" id="end_time" required step="300">
            </div>
            <div>
                <label for="duration_minutes">Duración por cita (minutos):</label>
                <input type="number" name="duration_minutes" id="duration_minutes" value="20" min="5" step="5" required>
            </div>
        </div>
        <div style="margin-top: 1rem;">
            <button type="submit" class="btn">Generar Turnos</button>
        </div>
    </form>
</div>

<div id="slots-panel-container" style="display:none;">
    <h3>2. Seleccionar Turnos y Asignar Médico (Opcional)</h3>
    <form id="publish-slots-form" action="publish_slots.php" method="POST">
        <div id="slots-list" class="table-container">
            </div>
        <div style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-success">Publicar Turnos Seleccionados</button>
        </div>
    </form>
</div>

<style>
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
    .btn-success { background-color: #28a745; }
    .btn-success:hover { background-color: #218838; }
</style>

<?php
include_once '../templates/footer.php';
?>