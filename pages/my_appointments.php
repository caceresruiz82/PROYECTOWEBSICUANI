<?php
/**
 * my_appointments.php
 *
 * Página para que el paciente vea el listado y estado de sus citas de teleconsulta.
 * Permite al paciente cancelar citas que estén en estado 'pendiente' o 'confirmada',
 * con una restricción de 3 días de anticipación.
 * Registra cancelaciones tardías en patient_penalties_log.
 *
 * @package EssaludSicuaniWeb
 * @subpackage Pages
 * @author Yudelvis Caceres
 * @version 1.0.1 // Implementación de restricción de 3 días para cancelación y registro de penalizaciones.
 * @date 2025-07-27
 */

session_start();

require_once '../includes/db_connection.php';

// --- Protección de la Página ---
// 1. Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Verificar si el usuario tiene el rol de Paciente (role_id = 6)
if ($_SESSION['role_id'] != 6) {
    header("Location: dashboard.php"); // Redirigir si no es paciente
    exit();
}

$message = '';
$patient_id = $_SESSION['user_id'];
$appointments = [];

// Definir mapeo de estados de cita a texto legible y clases CSS
$appointment_status_map = [
    'pendiente' => 'Pendiente de Confirmación',
    'confirmada' => 'Confirmada',
    'cancelada' => 'Cancelada',
    'completada' => 'Completada',
    'reprogramada' => 'Reprogramada',
    'en_espera_recita' => 'En Espera de Recita'
];

$appointment_status_class = [
    'pendiente' => 'badge bg-warning text-dark',
    'confirmada' => 'badge bg-success',
    'cancelada' => 'badge bg-danger',
    'completada' => 'badge bg-secondary',
    'reprogramada' => 'badge bg-info text-dark',
    'en_espera_recita' => 'badge bg-primary'
];

// --- Lógica para CANCELAR una cita ---
if (isset($_GET['action']) && $_GET['action'] == 'cancel' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $appointment_id_to_cancel = (int)$_GET['id'];

    // Iniciar transacción para asegurar atomicidad
    $conn->begin_transaction();

    try {
        // 1. Obtener slot_id, specialty_id y fecha/estado de la cita antes de cancelar
        // Unimos con appointment_slots para obtener specialty_id y así poder registrar la penalización si es necesaria.
        $stmt_get_appointment_info = $conn->prepare("SELECT a.slot_id, a.status, a.appointment_date, als.specialty_id
                                                      FROM appointments a
                                                      JOIN appointment_slots als ON a.slot_id = als.slot_id
                                                      WHERE a.appointment_id = ? AND a.patient_id = ?");
        $stmt_get_appointment_info->bind_param("ii", $appointment_id_to_cancel, $patient_id);
        $stmt_get_appointment_info->execute();
        $result_info = $stmt_get_appointment_info->get_result();

        if ($result_info->num_rows === 0) {
            throw new Exception("Cita no encontrada o no pertenece a este paciente.");
        }
        $appointment_info = $result_info->fetch_assoc();
        $slot_id = $appointment_info['slot_id'];
        $current_status = $appointment_info['status'];
        $appointment_date = $appointment_info['appointment_date'];
        $specialty_id = $appointment_info['specialty_id']; // Necesario para la penalización

        // Validar si la cita puede ser cancelada
        if ($current_status === 'pendiente' || $current_status === 'confirmada') {
            $current_date = new DateTime(date('Y-m-d'));
            $appointment_dt = new DateTime($appointment_date);
            $interval = $current_date->diff($appointment_dt);
            $days_diff = (int)$interval->format('%R%a'); // Diferencia en días, incluye signo (+ o -)

            $is_late_cancellation = false;
            // Si la cita es en el futuro y la diferencia es 0, 1, 2 días (menos de 3 días completos de anticipación)
            if ($days_diff >= 0 && $days_diff < 3) {
                 $is_late_cancellation = true;
            }

            // 2. Actualizar el estado de la cita a 'cancelada'
            $stmt_cancel = $conn->prepare("UPDATE appointments SET status = 'cancelada', updated_at = CURRENT_TIMESTAMP WHERE appointment_id = ?");
            $stmt_cancel->bind_param("i", $appointment_id_to_cancel);
            $stmt_cancel->execute();

            if ($stmt_cancel->affected_rows === 0) {
                throw new Exception("Error al cancelar la cita. Podría no estar en un estado cancelable o no se pudo actualizar.");
            }

            // 3. Incrementar available_slots en appointment_slots
            $stmt_increment_slot = $conn->prepare("UPDATE appointment_slots SET available_slots = available_slots + 1 WHERE slot_id = ?");
            $stmt_increment_slot->bind_param("i", $slot_id);
            $stmt_increment_slot->execute();

            if ($stmt_increment_slot->affected_rows === 0) {
                throw new Exception("Error al reponer el turno disponible. El slot no pudo ser actualizado.");
            }

            // 4. Registrar la penalización si es una cancelación tardía
            if ($is_late_cancellation) {
                $log_date = date('Y-m-d');
                $penalty_type = 'cancelacion_tardia';
                $notes = "Cancelación de cita tardía (menos de 3 días de anticipación) para especialidad_id: $specialty_id.";

                $stmt_log_penalty = $conn->prepare("INSERT INTO patient_penalties_log (patient_id, log_date, penalty_type, appointment_id, notes) VALUES (?, ?, ?, ?, ?)");
                $stmt_log_penalty->bind_param("isiss", $patient_id, $log_date, $penalty_type, $appointment_id_to_cancel, $notes);
                $stmt_log_penalty->execute();

                if ($stmt_log_penalty->affected_rows === 0) {
                    throw new Exception("Error al registrar la penalización de cancelación tardía.");
                }
                $stmt_log_penalty->close();
            }

            $conn->commit(); // Confirmar la transacción
            $_SESSION['appointment_message'] = '<div class="alert alert-success">Cita cancelada exitosamente. El turno ha sido liberado.';
            if ($is_late_cancellation) {
                $_SESSION['appointment_message'] .= ' Se ha registrado una cancelación tardía.';
            }
            $_SESSION['appointment_message'] .= '</div>';

        } else {
            throw new Exception("La cita no puede ser cancelada en su estado actual: " . $appointment_status_map[$current_status]);
        }
    } catch (Exception $e) {
        $conn->rollback(); // Revertir la transacción si algo falla
        $_SESSION['appointment_message'] = '<div class="alert alert-danger">Error al cancelar la cita: ' . $e->getMessage() . '</div>';
    } finally {
        if (isset($stmt_get_appointment_info) && $stmt_get_appointment_info) $stmt_get_appointment_info->close();
        if (isset($stmt_cancel) && $stmt_cancel) $stmt_cancel->close();
        if (isset($stmt_increment_slot) && $stmt_increment_slot) $stmt_increment_slot->close();
    }
    header("Location: my_appointments.php"); // Redirigir para limpiar la URL
    exit();
}


// --- Lógica para mostrar las citas del paciente ---
// Se unen appointments con appointment_slots y specialties para obtener todos los detalles
$stmt_appointments = $conn->prepare("SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.reason, a.status,
                                            s.specialty_name, als.duration_minutes
                                     FROM appointments a
                                     JOIN appointment_slots als ON a.slot_id = als.slot_id
                                     JOIN specialties s ON als.specialty_id = s.specialty_id
                                     WHERE a.patient_id = ?
                                     ORDER BY a.appointment_date DESC, a.appointment_time DESC");
$stmt_appointments->bind_param("i", $patient_id);
$stmt_appointments->execute();
$result_appointments = $stmt_appointments->get_result();

if ($result_appointments->num_rows > 0) {
    while ($row = $result_appointments->fetch_assoc()) {
        $appointments[] = $row;
    }
}
$stmt_appointments->close();

// Mensajes de sesión (viene de request_appointment.php o de la cancelación aquí mismo)
if (isset($_SESSION['appointment_message'])) {
    $message = $_SESSION['appointment_message'];
    unset($_SESSION['appointment_message']); // Limpiar el mensaje después de mostrarlo
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Citas - EsSalud Sicuani</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .navbar {
            background-color: #007bff; /* Color primario para pacientes */
        }
        .navbar .nav-link {
            color: #ffffff !important;
        }
        .navbar .nav-link:hover {
            color: #e2e6ea !important;
        }
        .container-fluid {
            padding-top: 20px;
            padding-bottom: 20px;
        }
        .card {
            margin-top: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        .table-responsive {
            margin-top: 20px;
        }
        .status-badge {
            padding: .3em .6em;
            border-radius: .25rem;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="patient_dashboard.php">
                <img src="../assets/images/logo_essalud.png" alt="Logo EsSalud" width="40" class="d-inline-block align-text-top me-2">
                Panel Paciente
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="patient_dashboard.php">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="request_appointment.php">Solicitar Cita</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="my_appointments.php">Mis Citas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Mesa de Partes</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="logout.php">Cerrar Sesión</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Mis Citas de Teleconsulta</h4>
            </div>
            <div class="card-body">
                <?php echo $message; // Mostrar mensajes de éxito o error ?>

                <?php if (!empty($appointments)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID Cita</th>
                                    <th>Especialidad</th>
                                    <th>Fecha</th>
                                    <th>Hora</th>
                                    <th>Duración</th>
                                    <th>Motivo</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $app): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($app['appointment_id']); ?></td>
                                        <td><?php echo htmlspecialchars($app['specialty_name']); ?></td>
                                        <td><?php echo htmlspecialchars($app['appointment_date']); ?></td>
                                        <td><?php echo htmlspecialchars(date('H:i', strtotime($app['appointment_time']))); ?></td>
                                        <td><?php echo htmlspecialchars($app['duration_minutes']); ?> min</td>
                                        <td><?php echo htmlspecialchars($app['reason'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="<?php echo $appointment_status_class[$app['status']] ?? 'badge bg-dark'; ?>">
                                                <?php echo htmlspecialchars($appointment_status_map[$app['status']] ?? 'Desconocido'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            // Lógica para determinar si la cita puede ser cancelada
                                            $can_cancel = false;
                                            if ($app['status'] == 'pendiente' || $app['status'] == 'confirmada') {
                                                $current_date = new DateTime(date('Y-m-d'));
                                                $appointment_dt = new DateTime($app['appointment_date']);
                                                $interval = $current_date->diff($appointment_dt);
                                                $days_diff = (int)$interval->format('%R%a'); // Diferencia en días

                                                // Si quedan 3 o más días de anticipación (ej. hoy 27, cita 30, diff = +3)
                                                if ($days_diff >= 3) {
                                                    $can_cancel = true;
                                                }
                                            }
                                            ?>
                                            <?php if ($can_cancel): ?>
                                                <a href="my_appointments.php?action=cancel&id=<?php echo $app['appointment_id']; ?>" class="btn btn-sm btn-danger" title="Cancelar Cita" onclick="return confirm('¿Está seguro de que desea cancelar esta cita?');"><i class="bi bi-x-circle-fill"></i> Cancelar</a>
                                            <?php elseif ($app['status'] == 'pendiente' || $app['status'] == 'confirmada'): // Si no se puede cancelar por los 3 días, pero está activa ?>
                                                <span class="badge bg-secondary ms-1">No cancelable (pocos días)</span>
                                            <?php endif; ?>
                                            </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center" role="alert">
                        No tienes citas programadas o pendientes.
                        <br><a href="request_appointment.php" class="alert-link">Solicita una cita ahora.</a>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>