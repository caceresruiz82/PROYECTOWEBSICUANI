<?php
/**
 * Página para que los pacientes soliciten una nueva cita.
 */

require_once '../includes/auth_guard.php';
require_once '../includes/db_connection.php';

// Verificar que el usuario sea un paciente
if ($_SESSION['user_role'] !== 'Paciente') {
    // Si no es paciente, redirigir a su dashboard.
    header('Location: dashboard.php');
    exit;
}

$message = '';
$message_type = '';

// Obtener la lista de especialidades para el dropdown
try {
    $specialties_stmt = $pdo->query("SELECT specialty_id, specialty_name FROM specialties ORDER BY specialty_name");
    $specialties = $specialties_stmt->fetchAll();
} catch (PDOException $e) {
    $specialties = [];
    $message = "Error al cargar las especialidades.";
    $message_type = 'error';
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slot_id = filter_input(INPUT_POST, 'slot_id', FILTER_VALIDATE_INT);
    $reason = trim(filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING));
    $patient_id = $_SESSION['user_id'];

    if (empty($slot_id) || empty($reason)) {
        $message = "Por favor, seleccione un cupo y escriba el motivo de la consulta.";
        $message_type = 'error';
    } else {
        try {
            // Iniciar una transacción para asegurar la integridad de los datos
            $pdo->beginTransaction();

            // 1. Verificar si el cupo todavía está disponible
            $slot_check_sql = "SELECT available_slots, slot_date, start_time FROM appointment_slots WHERE slot_id = :slot_id FOR UPDATE";
            $slot_stmt = $pdo->prepare($slot_check_sql);
            $slot_stmt->execute([':slot_id' => $slot_id]);
            $slot = $slot_stmt->fetch();

            if ($slot && $slot['available_slots'] > 0) {
                // 2. Crear la cita
                $appointment_sql = "INSERT INTO appointments (patient_id, slot_id, appointment_date, appointment_time, reason, status) 
                                    VALUES (:patient_id, :slot_id, :appointment_date, :appointment_time, :reason, 'pendiente')";
                $app_stmt = $pdo->prepare($appointment_sql);
                $app_stmt->execute([
                    ':patient_id' => $patient_id,
                    ':slot_id' => $slot_id,
                    ':appointment_date' => $slot['slot_date'],
                    ':appointment_time' => $slot['start_time'],
                    ':reason' => $reason
                ]);

                // 3. Disminuir el contador de cupos disponibles
                $update_slot_sql = "UPDATE appointment_slots SET available_slots = available_slots - 1 WHERE slot_id = :slot_id";
                $update_stmt = $pdo->prepare($update_slot_sql);
                $update_stmt->execute([':slot_id' => $slot_id]);

                // Confirmar la transacción
                $pdo->commit();
                $message = "¡Su cita ha sido solicitada con éxito! Será revisada por nuestro personal de admisión.";
                $message_type = 'success';

            } else {
                // Si el cupo ya no está disponible, revertir la transacción
                $pdo->rollBack();
                $message = "Lo sentimos, el cupo seleccionado ya no está disponible. Por favor, elija otro.";
                $message_type = 'error';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error al procesar su solicitud. Por favor, inténtelo de nuevo. " . $e->getMessage();
            $message_type = 'error';
        }
    }
}


$page_title = 'Solicitar Nueva Cita';
include_once '../templates/header.php';
?>

<h2><?php echo htmlspecialchars($page_title); ?></h2>
<p>Siga los pasos a continuación para encontrar un cupo y solicitar su cita.</p>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<form action="request_appointment.php" method="post">
    <div>
        <label for="specialty_id">1. Seleccione la Especialidad:</label>
        <select name="specialty_id" id="specialty_id" required>
            <option value="">-- Elija una especialidad --</option>
            <?php foreach ($specialties as $specialty): ?>
                <option value="<?php echo $specialty['specialty_id']; ?>">
                    <?php echo htmlspecialchars($specialty['specialty_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label for="slot_id">2. Seleccione el Horario Disponible:</label>
        <select name="slot_id" id="slot_id" required disabled>
            <option value="">Seleccione una especialidad primero</option>
        </select>
    </div>

    <div>
        <label for="reason">3. Motivo de la Consulta:</label>
        <textarea name="reason" id="reason" rows="4" required placeholder="Describa brevemente el motivo de su visita..."></textarea>
    </div>

    <div>
        <input type="submit" value="Confirmar Solicitud de Cita" class="btn">
    </div>
</form>

<?php
include_once '../templates/footer.php';
?>