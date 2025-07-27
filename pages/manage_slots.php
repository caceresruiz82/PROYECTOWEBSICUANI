<?php
/**
 * manage_slots.php
 *
 * Módulo para la gestión y visualización de los bloques de disponibilidad
 * de teleconsultas (`appointment_slots`). Accesible por Administradores y Admisionistas.
 * Permite a los Administradores aprobar los slots pendientes.
 *
 * @package EssaludSicuaniWeb
 * @subpackage Pages
 * @author Yudelvis Caceres
 * @version 1.0.3 // Actualización del href de eliminar para apuntar a delete_slot.php y ajuste de estilos.
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
$slots = [];

// Definir los mapeos de estado (numérico a textual)
$status_map = [
    0 => 'pendiente_aprobacion',
    1 => 'aprobado',
    // Puedes añadir más estados numéricos si los implementas en el futuro (ej. 2 => 'rechazado', 3 => 'finalizado')
];

// --- Lógica para APROBAR un slot (solo para Administradores) ---
if ($is_admin && isset($_GET['action']) && $_GET['action'] == 'approve' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $slot_id_to_approve = (int)$_GET['id'];

    // Asegurarse de que solo se aprueben slots con status = 0 (pendiente_aprobacion)
    $stmt_approve = $conn->prepare("UPDATE appointment_slots SET status = 1, approved_by_user_id = ?, approval_date = CURRENT_TIMESTAMP WHERE slot_id = ? AND status = 0");
    $stmt_approve->bind_param("ii", $_SESSION['user_id'], $slot_id_to_approve);

    if ($stmt_approve->execute()) {
        if ($stmt_approve->affected_rows > 0) {
            $_SESSION['slot_message'] = '<div class="alert alert-success">Slot de disponibilidad aprobado exitosamente.</div>';
        } else {
            $_SESSION['slot_message'] = '<div class="alert alert-warning">No se encontró el slot pendiente de aprobación o ya estaba aprobado.</div>';
        }
    } else {
        $_SESSION['slot_message'] = '<div class="alert alert-danger">Error al aprobar el slot: ' . $stmt_approve->error . '</div>';
    }
    $stmt_approve->close();
    header("Location: manage_slots.php"); // Redirigir para limpiar la URL
    exit();
}

// --- Lógica para mostrar slots ---
$limit = 10; // Número de slots por página
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filtros
$filter_specialty = isset($_GET['specialty_id']) && is_numeric($_GET['specialty_id']) ? (int)$_GET['specialty_id'] : null;
$filter_date = isset($_GET['slot_date']) && !empty($_GET['slot_date']) ? htmlspecialchars($_GET['slot_date']) : null;
// El filtro de estado ahora también esperará 0 o 1
$filter_status_value = isset($_GET['status_filter']) && ($_GET['status_filter'] === '0' || $_GET['status_filter'] === '1') ? (int)$_GET['status_filter'] : null;

$where_clauses = [];
$bind_types = '';
$bind_params = [];

if ($filter_specialty !== null) {
    $where_clauses[] = 's.specialty_id = ?';
    $bind_types .= 'i';
    $bind_params[] = $filter_specialty;
}
if ($filter_date) {
    $where_clauses[] = 's.slot_date = ?';
    $bind_types .= 's';
    $bind_params[] = $filter_date;
}
if ($filter_status_value !== null) {
    $where_clauses[] = 's.status = ?';
    $bind_types .= 'i'; // Ahora es entero
    $bind_params[] = $filter_status_value;
}

$where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Obtener el total de slots con filtros
$stmt_total_sql = "SELECT COUNT(*) FROM appointment_slots s {$where_sql}";
$stmt_total = $conn->prepare($stmt_total_sql);
if (count($bind_params) > 0) {
    $stmt_total->bind_param($bind_types, ...$bind_params);
}
$stmt_total->execute();
$stmt_total->bind_result($total_slots_count);
$stmt_total->fetch();
$stmt_total->close();
$total_pages = ceil($total_slots_count / $limit);

// Preparar y ejecutar la consulta para obtener los slots con paginación, filtros y nombres de especialidad/creador/aprobador
$stmt_slots_sql = "SELECT s.*, sp.specialty_name, uc.full_name AS created_by_name, ua.full_name AS approved_by_name
                   FROM appointment_slots s
                   JOIN specialties sp ON s.specialty_id = sp.specialty_id
                   JOIN users uc ON s.created_by_user_id = uc.user_id
                   LEFT JOIN users ua ON s.approved_by_user_id = ua.user_id
                   {$where_sql}
                   ORDER BY s.slot_date ASC, s.start_time ASC
                   LIMIT ? OFFSET ?";

$stmt_slots = $conn->prepare($stmt_slots_sql);

$final_bind_types = $bind_types . 'ii';
$final_bind_params = array_merge($bind_params, [$limit, $offset]);

if (!empty($final_bind_params)) {
    $stmt_slots->bind_param($final_bind_types, ...$final_bind_params);
}

$stmt_slots->execute();
$result_slots = $stmt_slots->get_result();

if ($result_slots->num_rows > 0) {
    while ($row = $result_slots->fetch_assoc()) {
        $slots[] = $row;
    }
}
$stmt_slots->close();

// Obtener todas las especialidades para el filtro
$all_specialties = [];
$stmt_all_specialties = $conn->prepare("SELECT specialty_id, specialty_name FROM specialties ORDER BY specialty_name ASC");
$stmt_all_specialties->execute();
$result_all_specialties = $stmt_all_specialties->get_result();
while ($row = $result_all_specialties->fetch_assoc()) {
    $all_specialties[] = $row;
}
$stmt_all_specialties->close();

// Mensajes de sesión (viene de program_slots.php o de la aprobación aquí mismo)
if (isset($_SESSION['slot_message'])) {
    $message = $_SESSION['slot_message'];
    unset($_SESSION['slot_message']); // Limpiar el mensaje después de mostrarlo
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Turnos - EsSalud Sicuani</title>
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
        .table-responsive {
            margin-top: 20px;
        }
        .status-badge-aprobado { background-color: #28a745; color: white; padding: .3em .6em; border-radius: .25rem; }
        .status-badge-pendiente_aprobacion { background-color: #ffc107; color: black; padding: .3em .6em; border-radius: .25rem; }
        .status-badge-rechazado { background-color: #dc3545; color: white; padding: .3em .6em; border-radius: .25rem; }
        .status-badge-finalizado { background-color: #6c757d; color: white; padding: .3em .6em; border-radius: .25rem; }
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
                        <a class="nav-link" href="program_slots.php">Programar Turnos</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="manage_slots.php">Gestión de Citas</a>
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
                <h4 class="mb-0">Gestión de Disponibilidad de Turnos</h4>
            </div>
            <div class="card-body">
                <?php echo $message; // Mostrar mensajes de éxito o error (ej. de program_slots.php) ?>

                <div class="d-flex justify-content-between mb-3 align-items-center">
                    <h5>Lista de Turnos Programados</h5>
                    <a href="program_slots.php" class="btn btn-success"><i class="bi bi-calendar-plus-fill"></i> Programar Nuevo Turno</a>
                </div>

                <form class="filter-form mb-4" action="manage_slots.php" method="GET">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="filterSpecialty" class="form-label visually-hidden">Especialidad</label>
                            <select class="form-select" id="filterSpecialty" name="specialty_id">
                                <option value="">Todas las Especialidades</option>
                                <?php foreach ($all_specialties as $specialty): ?>
                                    <option value="<?php echo htmlspecialchars($specialty['specialty_id']); ?>"
                                        <?php echo ($filter_specialty == $specialty['specialty_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($specialty['specialty_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="filterDate" class="form-label visually-hidden">Fecha</label>
                            <input type="date" class="form-control" id="filterDate" name="slot_date" value="<?php echo htmlspecialchars($filter_date ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="filterStatus" class="form-label visually-hidden">Estado</label>
                            <select class="form-select" id="filterStatus" name="status_filter"> <option value="">Todos los Estados</option>
                                <option value="1" <?php echo ($filter_status_value === 1) ? 'selected' : ''; ?>>Aprobado</option>
                                <option value="0" <?php echo ($filter_status_value === 0) ? 'selected' : ''; ?>>Pendiente de Aprobación</option>
                                </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Filtrar</button>
                        </div>
                    </div>
                </form>


                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Especialidad</th>
                                <th>Fecha</th>
                                <th>Hora Inicio</th>
                                <th>Hora Fin</th>
                                <th>Duración (min)</th>
                                <th>Total Turnos</th>
                                <th>Disponibles</th>
                                <th>Creado Por</th>
                                <th>Estado</th>
                                <th>Aprobado Por</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($slots)): ?>
                                <?php foreach ($slots as $slot): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($slot['slot_id']); ?></td>
                                        <td><?php echo htmlspecialchars($slot['specialty_name']); ?></td>
                                        <td><?php echo htmlspecialchars($slot['slot_date']); ?></td>
                                        <td><?php echo htmlspecialchars(date('H:i', strtotime($slot['start_time']))); ?></td>
                                        <td><?php echo htmlspecialchars(date('H:i', strtotime($slot['end_time']))); ?></td>
                                        <td><?php echo htmlspecialchars($slot['duration_minutes']); ?></td>
                                        <td><?php echo htmlspecialchars($slot['total_slots']); ?></td>
                                        <td><?php echo htmlspecialchars($slot['available_slots']); ?></td>
                                        <td><?php echo htmlspecialchars($slot['created_by_name']); ?></td>
                                        <td>
                                            <?php
                                            // Mapear el valor numérico del status a texto
                                            $display_status = $status_map[$slot['status']] ?? 'Desconocido';
                                            $status_class = '';
                                            switch ($slot['status']) {
                                                case 1: // Aprobado
                                                    $status_class = 'status-badge-aprobado';
                                                    break;
                                                case 0: // Pendiente de Aprobación
                                                    $status_class = 'status-badge-pendiente_aprobacion';
                                                    break;
                                                // Puedes añadir más casos para 2 (rechazado), 3 (finalizado)
                                            }
                                            echo '<span class="' . $status_class . '">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $display_status))) . '</span>';
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($slot['approved_by_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($is_admin && $slot['status'] == 0): // Si es administrador y el estado es 0 (pendiente) ?>
                                                <a href="manage_slots.php?action=approve&id=<?php echo $slot['slot_id']; ?>" class="btn btn-sm btn-success me-1" title="Aprobar" onclick="return confirm('¿Está seguro de que desea aprobar este turno?');"><i class="bi bi-check-circle-fill"></i></a>
                                            <?php endif; ?>
                                            <a href="#" class="btn btn-sm btn-info me-1" title="Editar"><i class="bi bi-pencil-square"></i></a>
                                            <a href="delete_slot.php?id=<?php echo $slot['slot_id']; ?>" class="btn btn-sm btn-danger" title="Eliminar" onclick="return confirm('¿Está seguro de que desea eliminar este turno? Esto también afectará las citas asociadas.');"><i class="bi bi-trash-fill"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="12" class="text-center">No hay turnos de disponibilidad programados.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Paginación de turnos" class="pagination-container">
                    <ul class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="manage_slots.php?page=<?php echo $i; ?>
                                    <?php echo $filter_specialty ? '&specialty_id=' . $filter_specialty : ''; ?>
                                    <?php echo $filter_date ? '&slot_date=' . $filter_date : ''; ?>
                                    <?php echo (isset($filter_status_value) && $filter_status_value !== '') ? '&status_filter=' . $filter_status_value : ''; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>