<?php
/**
 * manage_patient_appointments.php
 *
 * Módulo para la gestión de citas de pacientes. Accesible por Administradores y Admisionistas.
 * Permite visualizar, filtrar, confirmar y cancelar las citas solicitadas por los pacientes.
 * Incluye el nombre completo y número de documento (DNI) del paciente en el listado.
 * Implementa la funcionalidad de reprogramación institucional por parte de Admin/Admision.
 *
 * @package EssaludSicuaniWeb
 * @subpackage Pages
 * @author Yudelvis Caceres
 * @version 1.0.4 // DEBUG INFO retirado, manejo de mensajes mejorado.
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

// 2. Verificar si el usuario tiene el rol de Administrador (1) o Admision (4)
$is_admin = ($_SESSION['role_id'] == 1);
$is_admision = ($_SESSION['role_id'] == 4);

if (!$is_admin && !$is_admision) {
    header("Location: dashboard.php"); // Redirigir si no tiene el rol adecuado
    exit();
}

$message = '';
$appointments = [];
$users_for_filter = []; // Para el filtro de paciente por nombre/DNI

// Definir mapeo de estados de cita a texto legible y clases CSS
$appointment_status_map = [
    'pendiente' => 'Pendiente de Confirmación',
    'confirmada' => 'Confirmada',
    'cancelada' => 'Cancelada',
    'completada' => 'Completada',
    'reprogramada' => 'Reprogramada', // Nuevo estado para reprogramaciones institucionales
    'en_espera_recita' => 'En Espera de Recita',
    'cancelada_institucional' => 'Cancelada Institucionalmente' // Nuevo estado para la cita original
];

$appointment_status_class = [
    'pendiente' => 'badge bg-warning text-dark',
    'confirmada' => 'badge bg-success',
    'cancelada' => 'badge bg-danger',
    'completada' => 'badge bg-secondary',
    'reprogramada' => 'badge bg-info text-dark',
    'en_espera_recita' => 'badge bg-primary',
    'cancelada_institucional' => 'badge bg-dark' // Nuevo color para cancelada institucional
];

// Obtener todas las especialidades para el filtro y el modal
$specialties_for_modal = []; // Inicializar antes de usar
try {
    $stmt_all_specialties = $conn->prepare("SELECT specialty_id, specialty_name FROM specialties ORDER BY specialty_name ASC");
    if (!$stmt_all_specialties) {
        throw new Exception("Error al preparar la consulta de especialidades: " . $conn->error);
    }
    $stmt_all_specialties->execute();
    if ($stmt_all_specialties->error) {
        throw new Exception("Error al ejecutar la consulta de especialidades: " . $stmt_all_specialties->error);
    }
    $result_all_specialties = $stmt_all_specialties->get_result();
    if (!$result_all_specialties) {
        throw new Exception("Error crítico: get_result() falló para especialidades. MySQLnd no habilitado o problema de consulta. Error: " . $stmt_all_specialties->error);
    }
    while ($row = $result_all_specialties->fetch_assoc()) {
        $all_specialties[] = $row; // Para el filtro principal
        $specialties_for_modal[$row['specialty_id']] = $row['specialty_name']; // Para el select del modal
    }
    $stmt_all_specialties->close();
} catch (Exception $e) {
    // Captura el error y lo muestra en la página, además de loguearlo.
    $message = '<div class="alert alert-danger">Error al cargar especialidades: ' . $e->getMessage() . '</div>';
    error_log("ERROR al cargar especialidades en manage_patient_appointments.php: " . $e->getMessage());
    // No hacer exit() aquí, para que el resto de la página se cargue (sin especialidades)
}


// --- Lógica para Acciones de Cita (Confirmar, Cancelar, Reprogramar Institucional) ---
// Las acciones del modal (process_reprogram_institutional) son POST.
// Las acciones de la tabla (confirm, cancel) son GET.
// Ambos entran por aquí.
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id']) || (isset($_POST['action']) && isset($_POST['id']) && is_numeric($_POST['id']))) {
    
    // Determinar si es GET o POST
    $action_source = $_SERVER['REQUEST_METHOD'];
    $request_data = ($action_source === 'GET') ? $_GET : $_POST;

    $appointment_id = (int)$request_data['id'];
    $action = htmlspecialchars($request_data['action']);

    // Variable para almacenar el mensaje final de la acción antes de redirigir
    $action_final_message = '';

    // Iniciar transacción para asegurar atomicidad
    $conn->begin_transaction();

    try {
        // Obtener información crucial de la cita y el slot para la acción
        $stmt_get_info = $conn->prepare("SELECT a.patient_id, a.slot_id, a.status, a.appointment_date, a.appointment_time, a.reason, als.specialty_id
                                         FROM appointments a
                                         JOIN appointment_slots als ON a.slot_id = als.slot_id
                                         WHERE a.appointment_id = ?");
        $stmt_get_info->bind_param("i", $appointment_id);
        $stmt_get_info->execute();
        $result_info = $stmt_get_info->get_result();

        if ($result_info->num_rows === 0) {
            throw new Exception("Cita no encontrada.");
        }
        $app_info = $result_info->fetch_assoc();
        $patient_id_for_action = $app_info['patient_id'];
        $slot_id_for_action = $app_info['slot_id'];
        $current_app_status = $app_info['status'];
        $appointment_date_for_action = $app_info['appointment_date'];
        $appointment_time_for_action = $app_info['appointment_time'];
        $reason_for_action = $app_info['reason'];
        $specialty_id_for_action = $app_info['specialty_id'];

        $success_action = false; // Usar una variable diferente para el éxito de la acción


        if ($action === 'confirm' && ($current_app_status === 'pendiente' || $current_app_status === 'reprogramada')) {
            // Acción: CONFIRMAR Cita
            $stmt_confirm = $conn->prepare("UPDATE appointments SET status = 'confirmada', updated_at = CURRENT_TIMESTAMP WHERE appointment_id = ? AND status IN ('pendiente', 'reprogramada')");
            $stmt_confirm->bind_param("i", $appointment_id);
            $stmt_confirm->execute();

            if ($stmt_confirm->affected_rows > 0) {
                $action_final_message = '<div class="alert alert-success">Cita confirmada exitosamente.</div>';
                $success_action = true;
            } else {
                throw new Exception("Error al confirmar la cita. La cita ya no está en estado pendiente o reprogramada.");
            }
            if (isset($stmt_confirm)) $stmt_confirm->close();

        } elseif ($action === 'cancel' && ($current_app_status === 'pendiente' || $current_app_status === 'confirmada' || $current_app_status === 'reprogramada')) {
            // Acción: CANCELAR Cita (con lógica de penalización)
            $stmt_cancel = $conn->prepare("UPDATE appointments SET status = 'cancelada', updated_at = CURRENT_TIMESTAMP WHERE appointment_id = ? AND status IN ('pendiente', 'confirmada', 'reprogramada')");
            $stmt_cancel->bind_param("i", $appointment_id);
            $stmt_cancel->execute();

            if ($stmt_cancel->affected_rows > 0) {
                // Reponer el slot disponible SOLO si la cita NO es una reprogramación institucional
                if ($current_app_status !== 'reprogramada') { 
                    $stmt_increment_slot = $conn->prepare("UPDATE appointment_slots SET available_slots = available_slots + 1 WHERE slot_id = ?");
                    $stmt_increment_slot->bind_param("i", $slot_id_for_action);
                    $stmt_increment_slot->execute();
                    if ($stmt_increment_slot->affected_rows === 0) {
                        error_log("ADVERTENCIA: manage_patient_appointments.php - No se pudo reponer el turno disponible para slot_id: " . $slot_id_for_action . " (Puede que ya no exista o esté lleno).");
                    }
                    if (isset($stmt_increment_slot)) $stmt_increment_slot->close();
                }

                // Lógica de Penalización por Cancelación Tardía
                $current_date = new DateTime(date('Y-m-d'));
                $appointment_dt = new DateTime($appointment_date_for_action);
                $interval = $current_date->diff($appointment_dt);
                $days_diff = (int)$interval->format('%R%a'); // Diferencia en días, incluye signo (+ o -)

                $is_late_cancellation = false;
                // Si la cita es en el futuro y la diferencia es 0, 1, 2 días (menos de 3 días completos de anticipación)
                // Y NO es una cita que fue resultado de una reprogramación institucional.
                if ($days_diff >= 0 && $days_diff < 3 && $current_app_status !== 'reprogramada') {
                     $is_late_cancellation = true;
                }

                if ($is_late_cancellation) {
                    $log_date = date('Y-m-d');
                    $penalty_type = 'cancelacion_tardia';
                    $notes = "Cancelación tardía por Admisión/Admin (menos de 3 días de anticipación) para especialidad_id: $specialty_id_for_action. ID Cita: $appointment_id.";

                    $stmt_log_penalty = $conn->prepare("INSERT INTO patient_penalties_log (patient_id, log_date, penalty_type, appointment_id, notes) VALUES (?, ?, ?, ?, ?)");
                    $stmt_log_penalty->bind_param("isiss", $patient_id_for_action, $log_date, $penalty_type, $appointment_id, $notes);
                    $stmt_log_penalty->execute();

                    if ($stmt_log_penalty->affected_rows === 0) {
                        error_log("ADVERTENCIA: manage_patient_appointments.php - No se pudo registrar la penalización por cancelación tardía.");
                    }
                    if (isset($stmt_log_penalty)) $stmt_log_penalty->close();
                }

                $action_final_message = '<div class="alert alert-success">Cita cancelada exitosamente.';
                if ($current_app_status !== 'reprogramada') { 
                     $action_final_message .= ' El turno ha sido liberado.';
                }
                if ($is_late_cancellation) {
                    $action_final_message .= ' Se ha registrado una cancelación tardía.';
                }
                $action_final_message .= '</div>';
                $success_action = true;
            } else {
                throw new Exception("Error al cancelar la cita.");
            }

        } elseif ($action === 'process_reprogram_institutional' && $action_source === 'POST') { // Este es un POST del modal
            $new_slot_id = (int)($request_data['new_slot_id'] ?? 0); // Usar $request_data
            $reason_reprogram = htmlspecialchars(trim($request_data['reason_reprogram'] ?? '')); // Usar $request_data

            // 1. Obtener detalles del nuevo slot seleccionado (debe estar aprobado y disponible)
            $stmt_get_new_slot = $conn->prepare("SELECT specialty_id, slot_date, start_time FROM appointment_slots WHERE slot_id = ? AND status = 1 AND available_slots > 0");
            $stmt_get_new_slot->bind_param("i", $new_slot_id);
            $stmt_get_new_slot->execute();
            $result_new_slot = $stmt_get_new_slot->get_result();

            if ($result_new_slot->num_rows === 0) {
                throw new Exception("El nuevo turno seleccionado no es válido o ya no está disponible.");
            }
            $new_slot_info = $result_new_slot->fetch_assoc();
            $new_specialty_id = $new_slot_info['specialty_id'];
            $new_appointment_date = $new_slot_info['slot_date'];
            $new_appointment_time = $new_slot_info['start_time'];
            $stmt_get_new_slot->close(); // Cerrar statement aquí

            // 2. Crear una NUEVA cita con la nueva fecha/hora/slot
            // Esta nueva cita NO DECREMENTA 'available_slots' del nuevo slot (es adicional).
            $stmt_create_new_reprogram_app = $conn->prepare("INSERT INTO appointments (patient_id, slot_id, doctor_id_moderator, appointment_date, appointment_time, appointment_type, reason, status, is_reprogrammed_institutional, recita_original_appointment_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $type = 'Teleconsulta'; // Asumimos teleconsulta
            $new_status = 'reprogramada'; // Estado que indica reprogramación institucional

            $stmt_create_new_reprogram_app->bind_param("iiissssisi",
                $patient_id_for_action,          // patient_id (de la cita original)
                $new_slot_id,                    // slot_id (del nuevo slot seleccionado)
                null,                             // doctor_id_moderator (null por ahora)
                $new_appointment_date,           // appointment_date (del nuevo slot)
                $new_appointment_time,           // appointment_time (del nuevo slot)
                $type,                           // appointment_type
                $reason_reprogram,               // reason (motivo de la reprogramacion o de la original)
                $new_status,                     // status (reprogramada)
                1,                                // is_reprogrammed_institutional (TRUE)
                $appointment_id                  // recita_original_appointment_id (ID de la cita original)
            );
            $stmt_create_new_reprogram_app->execute();

            if ($stmt_create_new_reprogram_app->affected_rows === 0) {
                throw new Exception("Error al crear la nueva cita reprogramada.");
            }
            $new_appointment_inserted_id = $conn->insert_id; // Obtener el ID de la nueva cita creada
            $stmt_create_new_reprogram_app->close(); // Cerrar statement aquí

            // 3. Marcar la cita ORIGINAL como 'cancelada_institucional'
            $stmt_update_original_app = $conn->prepare("UPDATE appointments SET status = 'cancelada_institucional', updated_at = CURRENT_TIMESTAMP WHERE appointment_id = ?");
            $stmt_update_original_app->bind_param("i", $appointment_id);
            $stmt_update_original_app->execute();

            if ($stmt_update_original_app->affected_rows === 0) {
                throw new Exception("Error al actualizar la cita original a cancelada institucionalmente.");
            }
            $stmt_update_original_app->close(); // Cerrar statement aquí

            $conn->commit();
            $action_final_message = '<div class="alert alert-success">Cita reprogramada exitosamente por la institución. Se ha creado una nueva cita (ID: ' . $new_appointment_inserted_id . ').</div>';
            $success_action = true;

        } else {
            throw new Exception("Acción no reconocida o no válida para el estado actual de la cita.");
        }

    } catch (Exception $e) {
        $conn->rollback(); // Revertir la transacción si algo falla
        $action_final_message = '<div class="alert alert-danger">Error en la gestión de la cita: ' . $e->getMessage() . '</div>';
        error_log("ERROR en la gestión de la cita (catch): " . $e->getMessage() . " para app_id: " . $appointment_id);
    } finally {
        if (isset($stmt_get_info) && $stmt_get_info) $stmt_get_info->close();
    }
    // Después de cualquier acción, redirigir y mostrar el mensaje de la sesión
    $_SESSION['app_mgmt_message'] = $action_final_message; // Almacenar el mensaje
    header("Location: manage_patient_appointments.php"); // Redirigir para limpiar la URL
    exit();
}


// --- Lógica para mostrar citas con filtros y paginación ---
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filtros
$filter_status = isset($_GET['status_filter']) && array_key_exists($_GET['status_filter'], $appointment_status_map) ? htmlspecialchars($_GET['status_filter']) : null;
$filter_specialty = isset($_GET['specialty_id']) && is_numeric($_GET['specialty_id']) ? (int)$_GET['specialty_id'] : null;
$filter_date = isset($_GET['app_date']) && !empty($_GET['app_date']) ? htmlspecialchars($_GET['app_date']) : null;
$filter_dni = isset($_GET['dni']) && !empty($_GET['dni']) ? htmlspecialchars(trim($_GET['dni'])) : null;
$filter_patient_name = isset($_GET['patient_name']) && !empty($_GET['patient_name']) ? '%' . htmlspecialchars(trim($_GET['patient_name'])) . '%' : null;

$where_clauses = [];
$bind_types = '';
$bind_params = [];

if ($filter_status !== null) {
    $where_clauses[] = 'a.status = ?';
    $bind_types .= 's';
    $bind_params[] = $filter_status;
}
if ($filter_specialty !== null) {
    $where_clauses[] = 'als.specialty_id = ?';
    $bind_types .= 'i';
    $bind_params[] = $filter_specialty;
}
if ($filter_date) {
    $where_clauses[] = 'a.appointment_date = ?';
    $bind_types .= 's';
    $bind_params[] = $filter_date;
}
if ($filter_dni) {
    $where_clauses[] = 'u.dni = ?';
    $bind_types .= 's';
    $bind_params[] = $filter_dni;
}
if ($filter_patient_name) {
    $where_clauses[] = 'u.full_name LIKE ?';
    $bind_types .= 's';
    $bind_params[] = $filter_patient_name;
}

$where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Obtener el total de citas con filtros
$stmt_total_sql = "SELECT COUNT(*)
                   FROM appointments a
                   JOIN users u ON a.patient_id = u.user_id
                   JOIN appointment_slots als ON a.slot_id = als.slot_id
                   {$where_sql}";
$stmt_total = $conn->prepare($stmt_total_sql);
if (count($bind_params) > 0) {
    $stmt_total->bind_param($bind_types, ...$bind_params);
}
$stmt_total->execute();
$stmt_total->bind_result($total_appointments_count);
$stmt_total->fetch();
$stmt_total->close();
$total_pages = ceil($total_appointments_count / $limit);

// Preparar y ejecutar la consulta para obtener las citas con paginación, filtros y detalles
$stmt_appointments_sql = "SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.reason, a.status,
                                 u.full_name AS patient_name, u.dni,
                                 sp.specialty_name, als.duration_minutes
                           FROM appointments a
                           JOIN users u ON a.patient_id = u.user_id
                           JOIN appointment_slots als ON a.slot_id = als.slot_id
                           JOIN specialties sp ON als.specialty_id = sp.specialty_id
                           {$where_sql}
                           ORDER BY a.appointment_date DESC, a.appointment_time DESC
                           LIMIT ? OFFSET ?";

$stmt_appointments = $conn->prepare($stmt_appointments_sql);

$final_bind_types = $bind_types . 'ii';
$final_bind_params = array_merge($bind_params, [$limit, $offset]);

if (!empty($final_bind_params)) {
    $stmt_appointments->bind_param($final_bind_types, ...$final_bind_params);
}

$stmt_appointments->execute();
$result_appointments = $stmt_appointments->get_result();

if ($result_appointments->num_rows > 0) {
    while ($row = $result_appointments->fetch_assoc()) {
        $appointments[] = $row;
    }
}
$stmt_appointments->close();


// Variables para mantener los filtros en el formulario
$selected_status_filter = htmlspecialchars($_GET['status_filter'] ?? '');
$selected_specialty_filter = htmlspecialchars($_GET['specialty_id'] ?? '');
$selected_date_filter = htmlspecialchars($_GET['app_date'] ?? '');
$selected_dni_filter = htmlspecialchars($_GET['dni'] ?? '');
$selected_patient_name_filter = htmlspecialchars($_GET['patient_name'] ?? '');

// Mensajes de sesión (para acciones de gestión de citas)
if (isset($_SESSION['app_mgmt_message'])) {
    $message = $_SESSION['app_mgmt_message'];
    unset($_SESSION['app_mgmt_message']); // Limpiar el mensaje después de mostrarlo
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Citas de Pacientes - EsSalud Sicuani</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .navbar {
            background-color: #fd7e14; /* Color distintivo para Admisión */
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
        /* Colores de badges de estado de cita */
        .badge-pendiente { background-color: #ffc107; color: black; }
        .badge-confirmada { background-color: #28a745; color: white; }
        .badge-cancelada { background-color: #dc3545; color: white; }
        .badge-completada { background-color: #6c757d; color: white; }
        .badge-reprogramada { background-color: #0dcaf0; color: black; } /* Color para reprogramada institucional */
        .badge-en_espera_recita { background-color: #0d6efd; color: white; }
        .badge-cancelada_institucional { background-color: #343a40; color: white; } /* Color para cancelada institucional */
        
        .filter-form .form-control, .filter-form .form-select {
            margin-right: 10px;
        }
        /* Estilos responsivos adicionales para la tabla */
        @media (max-width: 768px) {
            .table-responsive table {
                border: 0;
            }
            .table-responsive thead {
                display: none;
            }
            .table-responsive tr {
                display: block;
                margin-bottom: .625em;
                border: 1px solid #dee2e6;
                border-radius: .25rem;
                background-color: #fff;
            }
            .table-responsive td {
                display: block;
                text-align: right;
                padding-left: 50% !important;
                position: relative;
            }
            .table-responsive td::before {
                content: attr(data-label);
                position: absolute;
                left: 0;
                width: 50%;
                padding-left: 15px;
                font-weight: bold;
                text-align: left;
            }
            .table-responsive td:last-child {
                text-align: left;
            }
            .table-responsive td:first-child {
                text-align: left;
                font-weight: bold;
            }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="admission_dashboard.php">
                <img src="../assets/images/logo_essalud.png" alt="Logo EsSalud" width="40" class="d-inline-block align-text-top me-2">
                Panel Admisión
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="admission_dashboard.php">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="program_slots.php">Programar Turnos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_slots.php">Supervisar Turnos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="manage_patient_appointments.php">Gestión de Citas Pacientes</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="#">Lista de Espera Recitas</a>
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
            <div class="card-header bg-warning text-white">
                <h4 class="mb-0">Gestión de Citas de Pacientes</h4>
            </div>
            <div class="card-body">
                <?php echo $message; // Mostrar mensajes de éxito o error ?>

                <div class="d-flex justify-content-between mb-3 align-items-center">
                    <h5>Lista de Citas Solicitadas</h5>
                    </div>

                <form class="filter-form mb-4" action="manage_patient_appointments.php" method="GET">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="filterStatus" class="form-label visually-hidden">Estado</label>
                            <select class="form-select" id="filterStatus" name="status_filter">
                                <option value="">Todos los Estados</option>
                                <?php foreach ($appointment_status_map as $val => $text): ?>
                                    <option value="<?php echo htmlspecialchars($val); ?>"
                                        <?php echo ($selected_status_filter == $val) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($text); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="filterSpecialty" class="form-label visually-hidden">Especialidad</label>
                            <select class="form-select" id="filterSpecialty" name="specialty_id">
                                <option value="">Todas las Especialidades</option>
                                <?php foreach ($all_specialties as $specialty): ?>
                                    <option value="<?php echo htmlspecialchars($specialty['specialty_id']); ?>"
                                        <?php echo ($selected_specialty_filter == $specialty['specialty_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($specialty['specialty_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="filterDate" class="form-label visually-hidden">Fecha</label>
                            <input type="date" class="form-control" id="filterDate" name="app_date" value="<?php echo htmlspecialchars($selected_date_filter); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="filterDNI" class="form-label visually-hidden">DNI Paciente</label>
                            <input type="text" class="form-control" id="filterDNI" name="dni" placeholder="DNI Paciente" value="<?php echo htmlspecialchars($selected_dni_filter); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="filterPatientName" class="form-label visually-hidden">Nombre Paciente</label>
                            <input type="text" class="form-control" id="filterPatientName" name="patient_name" placeholder="Nombre Paciente" value="<?php echo htmlspecialchars($selected_patient_name_filter); ?>">
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-filter"></i> Filtrar</button>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID Cita</th>
                                <th>Paciente</th>
                                <th>DNI</th>
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
                            <?php if (!empty($appointments)): ?>
                                <?php foreach ($appointments as $app): ?>
                                    <tr>
                                        <td data-label="ID Cita"><?php echo htmlspecialchars($app['appointment_id']); ?></td>
                                        <td data-label="Paciente"><?php echo htmlspecialchars($app['patient_name']); ?></td>
                                        <td data-label="DNI"><?php echo htmlspecialchars($app['dni']); ?></td>
                                        <td data-label="Especialidad"><?php echo htmlspecialchars($app['specialty_name']); ?></td>
                                        <td data-label="Fecha"><?php echo htmlspecialchars($app['appointment_date']); ?></td>
                                        <td data-label="Hora"><?php echo htmlspecialchars(date('H:i', strtotime($app['appointment_time']))); ?></td>
                                        <td data-label="Duración"><?php echo htmlspecialchars($app['duration_minutes']); ?> min</td>
                                        <td data-label="Motivo"><?php echo htmlspecialchars($app['reason'] ?? 'N/A'); ?></td>
                                        <td data-label="Estado">
                                            <span class="status-badge badge-<?php echo str_replace(' ', '_', $app['status']); ?>">
                                                <?php echo htmlspecialchars($appointment_status_map[$app['status']] ?? 'Desconocido'); ?>
                                            </span>
                                        </td>
                                        <td data-label="Acciones">
                                            <?php if ($app['status'] == 'pendiente' || $app['status'] == 'reprogramada'): ?>
                                                <a href="manage_patient_appointments.php?action=confirm&id=<?php echo $app['appointment_id']; ?>" class="btn btn-sm btn-success me-1" title="Confirmar Cita" onclick="return confirm('¿Está seguro de que desea confirmar esta cita?');"><i class="bi bi-check-circle-fill"></i> Confirmar</a>
                                            <?php endif; ?>
                                            
                                            <?php if ($app['status'] == 'pendiente' || $app['status'] == 'confirmada' || $app['status'] == 'reprogramada'): ?>
                                                <a href="manage_patient_appointments.php?action=cancel&id=<?php echo $app['appointment_id']; ?>" class="btn btn-sm btn-danger me-1" title="Cancelar Cita" onclick="return confirm('¿Está seguro de que desea cancelar esta cita?');"><i class="bi bi-x-circle-fill"></i> Cancelar</a>
                                            <?php endif; ?>

                                            <?php // Botón para abrir el modal de reprogramación institucional ?>
                                            <?php if (($is_admin || $is_admision) && ($app['status'] == 'pendiente' || $app['status'] == 'confirmada')): ?>
                                                <button type="button" class="btn btn-sm btn-info me-1" title="Reprogramar Institucionalmente" 
                                                        data-bs-toggle="modal" data-bs-target="#reprogramModal" 
                                                        data-appointment-id="<?php echo $app['appointment_id']; ?>"
                                                        data-patient-name="<?php echo htmlspecialchars($app['patient_name']); ?>"
                                                        data-current-date="<?php echo htmlspecialchars($app['appointment_date']); ?>"
                                                        data-current-time="<?php echo htmlspecialchars(date('H:i', strtotime($app['appointment_time']))); ?>"
                                                        data-specialty-id="<?php echo $app['specialty_id']; ?>">
                                                    <i class="bi bi-arrow-repeat"></i> Reprogramar Inst.
                                                </button>
                                            <?php endif; ?>
                                            
                                            </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center">No hay citas de pacientes que coincidan con los criterios.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Paginación de citas" class="pagination-container">
                    <ul class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="manage_patient_appointments.php?page=<?php echo $i; ?>
                                    <?php echo $filter_status ? '&status_filter=' . htmlspecialchars($filter_status) : ''; ?>
                                    <?php echo $filter_specialty ? '&specialty_id=' . htmlspecialchars($filter_specialty) : ''; ?>
                                    <?php echo $filter_date ? '&app_date=' . htmlspecialchars($filter_date) : ''; ?>
                                    <?php echo $filter_dni ? '&dni=' . htmlspecialchars($filter_dni) : ''; ?>
                                    <?php echo $filter_patient_name ? '&patient_name=' . htmlspecialchars(substr($filter_patient_name, 1, -1)) : ''; ?>
                                ">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>

            </div>
        </div>
    </div>

    <div class="modal fade" id="reprogramModal" tabindex="-1" aria-labelledby="reprogramModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="reprogramModalLabel">Reprogramar Cita Institucionalmente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Reprogramando cita para: <strong id="modalPatientName"></strong></p>
                    <p>Cita Actual: <span id="modalCurrentDate"></span> a las <span id="modalCurrentTime"></span></p>
                    <hr>
                    <form id="reprogramForm" action="manage_patient_appointments.php" method="POST">
                        <input type="hidden" name="action" value="process_reprogram_institutional">
                        <input type="hidden" name="id" id="modalAppointmentId">

                        <div class="mb-3">
                            <label for="reprogramSpecialty" class="form-label">Especialidad (para buscar nuevo turno):</label>
                            <select class="form-select" id="reprogramSpecialty" name="reprogram_specialty_id" required>
                                <option value="">Seleccione una especialidad</option>
                                <?php foreach ($specialties_for_modal as $id => $name): ?>
                                    <option value="<?php echo htmlspecialchars($id); ?>"><?php echo htmlspecialchars($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="reprogramDate" class="form-label">Nueva Fecha:</label>
                            <input type="date" class="form-control" id="reprogramDate" name="reprogram_date" required min="<?php echo date('Y-m-d', strtotime('tomorrow')); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="reprogramSlot" class="form-label">Seleccionar Nuevo Turno Disponible:</label>
                            <select class="form-select" id="reprogramSlot" name="new_slot_id" required>
                                <option value="">Cargando turnos...</option>
                            </select>
                            <small class="form-text text-muted">Primero selecciona Especialidad y Fecha.</small>
                        </div>
                         <div class="mb-3">
                            <label for="reasonReprogram" class="form-label">Motivo de la Reprogramación Institucional:</label>
                            <textarea class="form-control" id="reasonReprogram" name="reason_reprogram" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-info w-100">Confirmar Reprogramación</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var reprogramModal = document.getElementById('reprogramModal');
            reprogramModal.addEventListener('show.bs.modal', function (event) {
                // Botón que disparó el modal
                var button = event.relatedTarget; 
                // Extraer información de los atributos data-*
                var appointmentId = button.getAttribute('data-appointment-id');
                var patientName = button.getAttribute('data-patient-name');
                var currentDate = button.getAttribute('data-current-date');
                var currentTime = button.getAttribute('data-current-time');
                var specialtyId = button.getAttribute('data-specialty-id'); 

                // Actualizar el contenido del modal
                var modalPatientName = reprogramModal.querySelector('#modalPatientName');
                var modalCurrentDate = reprogramModal.querySelector('#modalCurrentDate');
                var modalCurrentTime = reprogramModal.querySelector('#modalCurrentTime');
                var modalAppointmentId = reprogramModal.querySelector('#modalAppointmentId');
                var reprogramSpecialtySelect = reprogramModal.querySelector('#reprogramSpecialty');
                var reprogramDateInput = reprogramModal.querySelector('#reprogramDate');
                var reprogramSlotSelect = reprogramModal.querySelector('#reprogramSlot');

                modalPatientName.textContent = patientName;
                modalCurrentDate.textContent = currentDate;
                modalCurrentTime.textContent = currentTime;
                modalAppointmentId.value = appointmentId;

                // Preseleccionar la especialidad de la cita original y limpiar la fecha
                if (specialtyId) {
                    reprogramSpecialtySelect.value = specialtyId;
                } else {
                    reprogramSpecialtySelect.value = ''; 
                }
                reprogramDateInput.value = ''; // Limpiar la fecha para forzar al usuario a elegir una nueva
                
                // Limpiar select de slots disponibles al abrir el modal
                reprogramSlotSelect.innerHTML = '<option value="">Seleccione Especialidad y Fecha</option>';
                reprogramSlotSelect.disabled = true;

                // Función para cargar slots disponibles
                function loadAvailableSlots() {
                    const selectedSpecialty = reprogramSpecialtySelect.value;
                    const selectedDate = reprogramDateInput.value;

                    if (selectedSpecialty && selectedDate) {
                        reprogramSlotSelect.disabled = true;
                        reprogramSlotSelect.innerHTML = '<option value="">Cargando turnos...</option>';

                        // Realizar una petición AJAX para obtener los slots disponibles
                        fetch(`ajax_get_available_slots.php?specialty_id=${selectedSpecialty}&slot_date=${selectedDate}`)
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Network response was not ok');
                                }
                                return response.json();
                            })
                            .then(data => {
                                reprogramSlotSelect.innerHTML = '<option value="">Seleccione un turno</option>';
                                if (data.length > 0) {
                                    data.forEach(slot => {
                                        const option = document.createElement('option');
                                        option.value = slot.slot_id;
                                        option.textContent = `${slot.start_time.substring(0, 5)} - ${slot.end_time.substring(0, 5)} (${slot.available_slots} disponibles)`;
                                        reprogramSlotSelect.appendChild(option);
                                    });
                                    reprogramSlotSelect.disabled = false;
                                } else {
                                    reprogramSlotSelect.innerHTML = '<option value="">No hay turnos disponibles para esta fecha/especialidad</option>';
                                }
                            })
                            .catch(error => {
                                console.error('Error al cargar turnos:', error);
                                reprogramSlotSelect.innerHTML = '<option value="">Error al cargar turnos</option>';
                            });
                    } else {
                        reprogramSlotSelect.innerHTML = '<option value="">Seleccione Especialidad y Fecha</option>';
                        reprogramSlotSelect.disabled = true;
                    }
                }

                // Event Listeners para cambios en el select de especialidad y el input de fecha
                reprogramSpecialtySelect.addEventListener('change', loadAvailableSlots);
                reprogramDateInput.addEventListener('change', loadAvailableSlots);
            });

            // Opcional: Reiniciar el formulario del modal cuando se oculta
            reprogramModal.addEventListener('hidden.bs.modal', function () {
                document.getElementById('reprogramForm').reset();
                document.getElementById('reprogramSlot').innerHTML = '<option value="">Cargando turnos...</option>';
                document.getElementById('reprogramSlot').disabled = true;
            });
        });
    </script>
</body>
</html>