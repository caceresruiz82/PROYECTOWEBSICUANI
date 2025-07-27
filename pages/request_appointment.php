<?php
/**
 * request_appointment.php
 *
 * Página para que los pacientes soliciten citas de teleconsulta.
 * Muestra la disponibilidad de turnos y permite al paciente seleccionar uno.
 * Implementa la validación de un máximo de 3 citas en total al mes.
 *
 * @package EssaludSicuaniWeb
 * @subpackage Pages
 * @author Yudelvis Caceres
 * @version 1.0.3 // Implementación de validación de 3 citas TOTALES al mes.
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
$specialties = [];
$available_slots = []; // Slots disponibles para mostrar

// Obtener la lista de especialidades para el select
$stmt_specialties = $conn->prepare("SELECT specialty_id, specialty_name FROM specialties ORDER BY specialty_name ASC");
$stmt_specialties->execute();
$result_specialties = $stmt_specialties->get_result();
while ($row = $result_specialties->fetch_assoc()) {
    $specialties[$row['specialty_id']] = $row['specialty_name'];
}
$stmt_specialties->close();

// --- Lógica para buscar slots disponibles (cuando el paciente usa los filtros) ---
$search_specialty_id = isset($_GET['specialty_id']) && is_numeric($_GET['specialty_id']) ? (int)$_GET['specialty_id'] : null;
$search_date = isset($_GET['slot_date']) && !empty($_GET['slot_date']) ? htmlspecialchars($_GET['slot_date']) : null;

$where_clauses = [];
$bind_types = '';
$bind_params = [];

// Mostrar solo slots "aprobados" (status = 1) y que tengan "available_slots" > 0
$where_clauses[] = 's.status = 1';
$where_clauses[] = 's.available_slots > 0';

// Filtro por especialidad
if ($search_specialty_id !== null) {
    $where_clauses[] = 's.specialty_id = ?';
    $bind_types .= 'i';
    $bind_params[] = $search_specialty_id;
}

// Filtro por fecha (solo futuras)
if ($search_date && strtotime($search_date) >= strtotime(date('Y-m-d'))) {
    $where_clauses[] = 's.slot_date = ?';
    $bind_types .= 's';
    $bind_params[] = $search_date;
} elseif (!$search_date) {
    // Si no se especifica fecha, mostrar desde mañana en adelante
    $where_clauses[] = 's.slot_date >= ?';
    $bind_types .= 's';
    $bind_params[] = date('Y-m-d', strtotime('tomorrow'));
} else {
    // Si la fecha es pasada, forzar a no mostrar resultados o mostrar un error
    $message = '<div class="alert alert-warning">La fecha de búsqueda no puede ser en el pasado.</div>';
}


$where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$stmt_slots_sql = "SELECT s.slot_id, s.slot_date, s.start_time, s.end_time, s.duration_minutes, s.available_slots, sp.specialty_name
                   FROM appointment_slots s
                   JOIN specialties sp ON s.specialty_id = sp.specialty_id
                   {$where_sql}
                   ORDER BY s.slot_date ASC, s.start_time ASC";

$stmt_slots = $conn->prepare($stmt_slots_sql);

if (!empty($bind_params)) {
    $stmt_slots->bind_param($bind_types, ...$bind_params);
}

$stmt_slots->execute();
$result_slots = $stmt_slots->get_result();

if ($result_slots->num_rows > 0) {
    while ($row = $result_slots->fetch_assoc()) {
        $available_slots[] = $row;
    }
} else {
    if (!empty($_GET)) { // Solo mostrar si ya se aplicaron filtros y no hubo resultados
        $message .= '<div class="alert alert-info">No se encontraron turnos disponibles con los criterios de búsqueda.</div>';
    }
}
$stmt_slots->close();


// --- Lógica para procesar la solicitud de cita (cuando el paciente hace clic en "Solicitar") ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_appointment') {
    $selected_slot_id = (int)($_POST['slot_id'] ?? 0);
    $reason = htmlspecialchars(trim($_POST['reason'] ?? ''));

    // Iniciar transacción para asegurar atomicidad
    $conn->begin_transaction();

    try {
        // Validar el slot seleccionado y obtener su specialty_id y fechas
        $stmt_check_slot = $conn->prepare("SELECT specialty_id, slot_date, start_time, available_slots FROM appointment_slots WHERE slot_id = ? AND status = 1 AND available_slots > 0");
        $stmt_check_slot->bind_param("i", $selected_slot_id);
        $stmt_check_slot->execute();
        $result_check_slot = $stmt_check_slot->get_result();

        if ($result_check_slot->num_rows === 1) {
            $slot_info = $result_check_slot->fetch_assoc();
            $slot_specialty_id = $slot_info['specialty_id'];
            $slot_date = $slot_info['slot_date'];
            $slot_start_time = $slot_info['start_time'];

            // 1. Validación de 3 citas TOTALES al mes (independientemente de la especialidad)
            $start_of_month = date('Y-m-01');
            $end_of_month = date('Y-m-t');

            // Contar todas las citas activas o pendientes para el paciente en el mes
            // Excluir canceladas y canceladas_institucional
            $stmt_count_total_appointments = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND appointment_date BETWEEN ? AND ? AND status NOT IN ('cancelada', 'cancelada_institucional', 'completada')");
            $stmt_count_total_appointments->bind_param("iss", $patient_id, $start_of_month, $end_of_month);
            $stmt_count_total_appointments->execute();
            $stmt_count_total_appointments->bind_result($current_month_total_count);
            $stmt_count_total_appointments->fetch();
            $stmt_count_total_appointments->close();

            // --- DEP. TEMPORAL para la validación de 3 citas TOTALES/mes ---
            error_log("DEBUG: request_appointment.php - Citas TOTALES encontradas este mes: " . $current_month_total_count);
            // --- FIN DEP. TEMPORAL ---

            if ($current_month_total_count >= 3) {
                throw new Exception('Ya has solicitado el máximo de 3 citas en total este mes (entre todas las especialidades).');
            } else {
                // Proceder con la solicitud de cita
                // Insertar la cita en la tabla 'appointments'
                $stmt_insert_appointment = $conn->prepare("INSERT INTO appointments (patient_id, slot_id, doctor_id_moderator, appointment_date, appointment_time, appointment_type, reason, status, is_reprogrammed_institutional, recita_original_appointment_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $type = 'Teleconsulta';
                $status = 'pendiente'; // Estado inicial de la cita del paciente
                $is_reprogrammed_institutional = 0; // Por defecto, no es una reprogramación institucional
                $recita_original_appointment_id = null; // Por defecto, no es una recita

                $stmt_insert_appointment->bind_param("iiissssisi", $patient_id, $selected_slot_id, null, $slot_date, $slot_start_time, $type, $reason, $status, $is_reprogrammed_institutional, $recita_original_appointment_id);
                $stmt_insert_appointment->execute();

                if ($stmt_insert_appointment->affected_rows === 1) {
                    // Decrementar available_slots en appointment_slots
                    $stmt_decrement_slot = $conn->prepare("UPDATE appointment_slots SET available_slots = available_slots - 1 WHERE slot_id = ? AND available_slots > 0");
                    $stmt_decrement_slot->bind_param("i", $selected_slot_id);
                    $stmt_decrement_slot->execute();

                    if ($stmt_decrement_slot->affected_rows === 1) {
                        $conn->commit(); // Confirmar la transacción
                        $_SESSION['appointment_message'] = '<div class="alert alert-success">¡Cita solicitada exitosamente! Está pendiente de confirmación por parte de Admisión/Administrador.</div>';
                        header("Location: my_appointments.php"); // Redirigir al panel de citas del paciente
                        exit();
                    } else {
                        throw new Exception("Error al decrementar turnos disponibles. El turno pudo haberse agotado.");
                    }
                } else {
                    throw new Exception("Error al insertar la solicitud de cita: " . $stmt_insert_appointment->error);
                }
            }
        } else {
            throw new Exception("El turno seleccionado no es válido o ya no está disponible.");
        }
    } catch (Exception $e) {
        $conn->rollback(); // Revertir la transacción si algo falla
        $message = '<div class="alert alert-danger">Error al procesar la solicitud de cita: ' . $e->getMessage() . '</div>';
        error_log("ERROR en request_appointment.php (catch): " . $e->getMessage());
    } finally {
        if (isset($stmt_check_slot) && $stmt_check_slot) $stmt_check_slot->close();
        // Los otros statements se cierran dentro de sus bloques o se cierran al final del script.
    }
}

// Variables para mantener los filtros en el formulario
$selected_specialty = htmlspecialchars($_GET['specialty_id'] ?? '');
$selected_date = htmlspecialchars($_GET['slot_date'] ?? '');

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar Cita - EsSalud Sicuani</title>
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
        .slot-card {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .slot-card h6 {
            color: #007bff;
            font-weight: bold;
        }
        .slot-card .btn-request {
            width: 100%;
        }
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        .filter-form .form-control, .filter-form .form-select {
            margin-right: 10px;
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
                        <a class="nav-link active" aria-current="page" href="request_appointment.php">Solicitar Cita</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_appointments.php">Mis Citas</a>
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
                <h4 class="mb-0">Solicitar Cita de Teleconsulta</h4>
            </div>
            <div class="card-body">
                <?php echo $message; // Mostrar mensajes de éxito o error ?>

                <form class="filter-form mb-4" action="request_appointment.php" method="GET">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label for="searchSpecialty" class="form-label visually-hidden">Especialidad</label>
                            <select class="form-select" id="searchSpecialty" name="specialty_id">
                                <option value="">Todas las Especialidades</option>
                                <?php foreach ($specialties as $id => $name): ?>
                                    <option value="<?php echo htmlspecialchars($id); ?>"
                                        <?php echo ($selected_specialty == $id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="searchDate" class="form-label visually-hidden">Fecha</label>
                            <input type="date" class="form-control" id="searchDate" name="slot_date" value="<?php echo htmlspecialchars($selected_date); ?>" min="<?php echo date('Y-m-d', strtotime('tomorrow')); ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Buscar Turnos</button>
                        </div>
                    </div>
                </form>

                <hr>

                <h5>Turnos Disponibles</h5>
                <?php if (!empty($available_slots)): ?>
                    <div class="row">
                        <?php foreach ($available_slots as $slot): ?>
                            <div class="col-md-4">
                                <div class="slot-card">
                                    <h6><?php echo htmlspecialchars($slot['specialty_name']); ?></h6>
                                    <p>Fecha: <strong><?php echo htmlspecialchars($slot['slot_date']); ?></strong></p>
                                    <p>Horario: <strong><?php echo htmlspecialchars(date('H:i', strtotime($slot['start_time']))) . ' - ' . htmlspecialchars(date('H:i', strtotime($slot['end_time']))); ?></strong></p>
                                    <p>Duración por turno: <?php echo htmlspecialchars($slot['duration_minutes']); ?> min</p>
                                    <p>Turnos disponibles: <span class="badge bg-success"><?php echo htmlspecialchars($slot['available_slots']); ?></span></p>

                                    <form action="request_appointment.php" method="POST">
                                        <input type="hidden" name="action" value="request_appointment">
                                        <input type="hidden" name="slot_id" value="<?php echo htmlspecialchars($slot['slot_id']); ?>">
                                        <div class="mb-3">
                                            <label for="reason_<?php echo $slot['slot_id']; ?>" class="form-label">Motivo de la consulta (opcional):</label>
                                            <textarea class="form-control" id="reason_<?php echo $slot['slot_id']; ?>" name="reason" rows="2"></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-request">Solicitar este Turno</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center" role="alert">
                        No hay turnos disponibles que coincidan con su búsqueda o en fechas futuras. Por favor, intente con otra fecha o especialidad.
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>