<?php
/**
 * program_slots.php
 *
 * Página para que Administradores y Admisionistas programen la disponibilidad
 * de turnos para teleconsultas. Los turnos creados por Admisionistas
 * quedan en estado 'pendiente_aprobacion' (0) hasta ser aprobados por un Administrador (1).
 *
 * @package EssaludSicuaniWeb
 * @subpackage Pages
 * @author Yudelvis Caceres
 * @version 1.0.8 // ¡¡¡CORRECCIÓN FINAL CRÍTICA de bind_param para STATUS como INT!!!
 * @date 2025-07-26 // Fecha de última modificación
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
if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 4) {
    header("Location: dashboard.php"); // Redirigir si no tiene el rol adecuado
    exit();
}

$message = '';
$specialties = [];
$current_user_role_id = $_SESSION['role_id'];
$current_user_id = $_SESSION['user_id'];

// Obtener la lista de especialidades para el select
$stmt_specialties = $conn->prepare("SELECT specialty_id, specialty_name FROM specialties ORDER BY specialty_name ASC");
$stmt_specialties->execute();
$result_specialties = $stmt_specialties->get_result();
while ($row = $result_specialties->fetch_assoc()) {
    $specialties[$row['specialty_id']] = $row['specialty_name'];
}
$stmt_specialties->close();

// --- Lógica para procesar la creación de slots (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $specialty_id = (int)($_POST['specialty_id'] ?? 0);
    $slot_date = htmlspecialchars(trim($_POST['slot_date'] ?? ''));
    $start_time = htmlspecialchars(trim($_POST['start_time'] ?? ''));
    $end_time = htmlspecialchars(trim($_POST['end_time'] ?? ''));
    $duration_minutes = (int)($_POST['duration_minutes'] ?? 0);
    $total_slots = (int)($_POST['total_slots'] ?? 0);

    $errors = [];

    // Validaciones
    if (empty($specialty_id) || !array_key_exists($specialty_id, $specialties)) {
        $errors[] = 'Debe seleccionar una especialidad válida.';
    }
    if (empty($slot_date) || !strtotime($slot_date)) {
        $errors[] = 'La fecha del turno es obligatoria y debe ser válida.';
    } elseif (strtotime($slot_date) < strtotime(date('Y-m-d', strtotime('tomorrow')))) { // Fecha no puede ser hoy o en el pasado
        $errors[] = 'La fecha del turno no puede ser hoy ni en el pasado. Debe ser a partir de mañana.';
    }
    if (empty($start_time) || empty($end_time)) {
        $errors[] = 'Las horas de inicio y fin son obligatorias.';
    } elseif (strtotime($start_time) >= strtotime($end_time)) {
        $errors[] = 'La hora de inicio debe ser anterior a la hora de fin.';
    }
    if ($duration_minutes <= 0) {
        $errors[] = 'La duración de cada turno debe ser un número positivo.';
    } elseif ($duration_minutes % 5 !== 0) { // Añadida validación para que sea divisible por 5
        $errors[] = 'La duración de cada turno debe ser un múltiplo de 5 minutos.';
    }
    if ($total_slots <= 0) {
        $errors[] = 'La cantidad total de turnos debe ser un número positivo.';
    }

    // Calcular la duración total del bloque y verificar que sea divisible por duration_minutes
    $time_diff_seconds = strtotime($end_time) - strtotime($start_time);
    $total_duration_minutes_block = $time_diff_seconds / 60;
    if ($total_duration_minutes_block <= 0 || ($total_duration_minutes_block % $duration_minutes !== 0)) {
        $errors[] = 'El rango de horas (inicio-fin) debe ser divisible exactamente por la duración de cada turno.';
    } elseif ($total_slots > ($total_duration_minutes_block / $duration_minutes)) {
        $errors[] = 'La cantidad total de turnos excede la capacidad del bloque horario dada la duración.';
    }


    // Verificar superposición/duplicidad para la misma especialidad, fecha y rango horario
    // Esto es vital para evitar que se programen dos bloques de horarios que se crucen
    if (empty($errors)) {
        // La lógica de superposición es un poco más compleja para cubrir todos los casos:
        // (Slot_Start < New_End AND Slot_End > New_Start)
        $stmt_check_overlap = $conn->prepare("SELECT COUNT(*) FROM appointment_slots WHERE specialty_id = ? AND slot_date = ? AND ((start_time < ? AND end_time > ?) OR (start_time = ? AND end_time = ?))");
        $stmt_check_overlap->bind_param("isssss", $specialty_id, $slot_date, $end_time, $start_time, $start_time, $end_time);
        $stmt_check_overlap->execute();
        $stmt_check_overlap->bind_result($overlap_count);
        $stmt_check_overlap->fetch();
        $stmt_check_overlap->close();

        if ($overlap_count > 0) {
            $errors[] = 'Ya existe un bloque de disponibilidad para esta especialidad que se superpone con la fecha y horario especificados, o es un duplicado exacto.';
        }
    }


    if (empty($errors)) {
        // Determinar el estado inicial del slot (0 para pendiente, 1 para aprobado)
        $initial_status_value = ($current_user_role_id == 1) ? 1 : 0; // 1 = Administrador (aprobado=1), Admision (pendiente=0)
        
        // Manejar approved_by_user_id y approval_date
        $approved_by_val = ($current_user_role_id == 1) ? $current_user_id : null;
        $approval_date_val = ($current_user_role_id == 1) ? date('Y-m-d H:i:s') : null;


        // total_slots es el initial available_slots
        $available_slots = $total_slots;

        // Insertar el nuevo slot
        $stmt_insert = $conn->prepare("INSERT INTO appointment_slots (specialty_id, slot_date, start_time, end_time, duration_minutes, total_slots, available_slots, created_by_user_id, status, approved_by_user_id, approval_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // ¡¡¡CORRECCIÓN FINAL Y CRÍTICA DEL STRING DE TIPOS DE bind_param!!!
        // 1. specialty_id (i)
        // 2. slot_date (s)
        // 3. start_time (s)
        // 4. end_time (s)
        // 5. duration_minutes (i)
        // 6. total_slots (i)
        // 7. available_slots (i)
        // 8. created_by_user_id (i)
        // 9. status (i - ¡AHORA ES INT, NO STRING!)
        // 10. approved_by_user_id (i - si es NULL, MySQLi lo envía como NULL a la BD si la columna es INT NULLABLE)
        // 11. approval_date (s - si es NULL, MySQLi lo envía como NULL a la BD si la columna es DATETIME/TIMESTAMP NULLABLE)
        $stmt_insert->bind_param("isssiiisiis", $specialty_id, $slot_date, $start_time, $end_time, $duration_minutes, $total_slots, $available_slots, $current_user_id, $initial_status_value, $approved_by_val, $approval_date_val);

        if ($stmt_insert->execute()) {
            $_SESSION['slot_message'] = '<div class="alert alert-success">Bloque de disponibilidad programado exitosamente.</div>';
            if ($initial_status_value === 0) { // Si el estado es 0 (pendiente)
                $_SESSION['slot_message'] = '<div class="alert alert-warning">Bloque de disponibilidad programado. Pendiente de aprobación por un administrador.</div>';
            }
            header("Location: manage_slots.php"); // Redirigir a una página para ver los slots, la crearemos después
            exit();
        } else {
            // Este error puede ocurrir si UNIQUE KEY `idx_unique_slot` falla o por cualquier otro error de la BD
            $message = '<div class="alert alert-danger">Error al programar el bloque: ' . $stmt_insert->error . '</div>';
        }
        $stmt_insert->close();
    } else {
        $message = '<div class="alert alert-danger"><ul>';
        foreach ($errors as $error) {
            $message .= '<li>' . $error . '</li>';
        }
        $message .= '</ul></div>';
    }
}

// Variables para prellenar el formulario en caso de error de POST
$specialty_id_val = $_POST['specialty_id'] ?? '';
$slot_date_val = $_POST['slot_date'] ?? date('Y-m-d'); // Valor por defecto hoy
$start_time_val = $_POST['start_time'] ?? '09:00'; // Valor por defecto
$end_time_val = $_POST['end_time'] ?? '17:00';   // Valor por defecto
$duration_minutes_val = $_POST['duration_minutes'] ?? '15';
$total_slots_val = $_POST['total_slots'] ?? '';

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programar Turnos de Citas - EsSalud Sicuani</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .navbar {
            background-color: #dc3545; /* Color distintivo para el admin */
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
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="admin_dashboard.php">
                <img src="../assets/images/logo_essalud.png" alt="Logo EsSalud" width="40" class="d-inline-block align-text-top me-2">
                Panel Administrativo
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_users.php">Gestión de Usuarios</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="program_slots.php">Programar Turnos</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="#">Gestión de Citas</a>
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
            <div class="card-header bg-danger text-white">
                <h4 class="mb-0">Programar Disponibilidad de Teleconsultas</h4>
            </div>
            <div class="card-body">
                <?php echo $message; // Mostrar mensajes de éxito o error ?>

                <form action="program_slots.php" method="POST">
                    <div class="mb-3">
                        <label for="specialty_id" class="form-label">Especialidad:</label>
                        <select class="form-select" id="specialty_id" name="specialty_id" required>
                            <option value="">Seleccione una especialidad</option>
                            <?php foreach ($specialties as $id => $name): ?>
                                <option value="<?php echo $id; ?>" <?php echo ($specialty_id_val == $id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="slot_date" class="form-label">Fecha del Turno:</label>
                        <input type="date" class="form-control" id="slot_date" name="slot_date" value="<?php echo htmlspecialchars($slot_date_val); ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_time" class="form-label">Hora de Inicio:</label>
                            <input type="time" class="form-control" id="start_time" name="start_time" value="<?php echo htmlspecialchars($start_time_val); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="end_time" class="form-label">Hora de Fin:</label>
                            <input type="time" class="form-control" id="end_time" name="end_time" value="<?php echo htmlspecialchars($end_time_val); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="duration_minutes" class="form-label">Duración de Cada Turno (minutos):</label>
                            <input type="number" class="form-control" id="duration_minutes" name="duration_minutes" min="5" step="5" value="<?php echo htmlspecialchars($duration_minutes_val); ?>" required>
                            <small class="form-text text-muted">Ej: 15, 20, 30 minutos. Debe ser múltiplo de 5.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="total_slots" class="form-label">Cantidad Total de Turnos:</label>
                            <input type="number" class="form-control" id="total_slots" name="total_slots" min="1" value="<?php echo htmlspecialchars($total_slots_val); ?>" required>
                            <small class="form-text text-muted">Ej: 5 turnos. Debe coincidir con la duración del bloque.</small>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-danger">Programar Turnos</button>
                    <a href="admin_dashboard.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>