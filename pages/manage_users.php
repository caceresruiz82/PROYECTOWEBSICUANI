<?php
/**
 * manage_users.php
 *
 * Módulo de Gestión de Usuarios para el Panel de Administración de EsSalud Sicuani.
 * Permite a los administradores ver, filtrar, y gestionar los datos de los usuarios
 * registrados en el sistema. Incluye opciones para activar/desactivar y cambiar roles.
 *
 * @package EssaludSicuaniWeb
 * @subpackage Pages
 * @author Yudelvis Caceres
 * @version 1.0.1 // Versión actualizada por inclusión de mensajes de sesión
 * @date 2025-07-26
 */

// Iniciar la sesión PHP
session_start();

// Incluir la conexión a la base de datos
require_once '../includes/db_connection.php';

// --- Protección de la Página ---
// 1. Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Verificar si el usuario tiene el rol de Administrador (role_id = 1)
if ($_SESSION['role_id'] != 1) {
    // Si no es administrador, redirigir a un dashboard genérico o de acceso denegado
    header("Location: dashboard.php"); // Crear dashboard.php para esto
    exit();
}

$users = [];
$limit = 10; // Número de usuarios por página
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Preparar y ejecutar la consulta para obtener el total de usuarios
$stmt_total = $conn->prepare("SELECT COUNT(*) FROM users");
$stmt_total->execute();
$stmt_total->bind_result($total_users);
$stmt_total->fetch();
$stmt_total->close();
$total_pages = ceil($total_users / $limit);

// Preparar y ejecutar la consulta para obtener los usuarios con paginación y el nombre del rol
// Hacemos un JOIN con la tabla roles para obtener el nombre legible del rol
$stmt_users = $conn->prepare("SELECT u.user_id, u.full_name, u.email, u.phone_number, u.dni, u.status, r.role_name, u.registration_date
                              FROM users u
                              JOIN roles r ON u.role_id = r.role_id
                              ORDER BY u.registration_date DESC
                              LIMIT ? OFFSET ?");
$stmt_users->bind_param("ii", $limit, $offset);
$stmt_users->execute();
$result_users = $stmt_users->get_result();

if ($result_users->num_rows > 0) {
    while ($row = $result_users->fetch_assoc()) {
        $users[] = $row;
    }
}
$stmt_users->close();

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Admin EsSalud Sicuani</title>
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
        .status-badge-activo { background-color: #28a745; color: white; padding: .3em .6em; border-radius: .25rem; }
        .status-badge-inactivo { background-color: #dc3545; color: white; padding: .3em .6em; border-radius: .25rem; }
        .status-badge-pendiente { background-color: #ffc107; color: black; padding: .3em .6em; border-radius: .25rem; }
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
                Panel Administrador
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
                        <a class="nav-link active" aria-current="page" href="manage_users.php">Gestión de Usuarios</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="program_slots.php">Programar Turnos</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="manage_slots.php">Gestión de Citas</a>
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
                <h4 class="mb-0">Gestión de Usuarios</h4>
            </div>
            <div class="card-body">
                <?php
                // Mostrar mensajes de la sesión si existen (para eliminación o creación)
                if (isset($_SESSION['delete_message'])) {
                    echo $_SESSION['delete_message'];
                    unset($_SESSION['delete_message']); // Eliminar el mensaje después de mostrarlo
                }
                if (isset($_SESSION['create_user_message'])) {
                    echo $_SESSION['create_user_message'];
                    unset($_SESSION['create_user_message']);
                }
                ?>

                <div class="d-flex justify-content-between mb-3">
                    <h5>Lista de Usuarios del Sistema</h5>
                    <a href="create_user.php" class="btn btn-success"><i class="bi bi-person-plus-fill"></i> Nuevo Usuario</a>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre Completo</th>
                                <th>Correo Electrónico</th>
                                <th>DNI</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Fecha Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['dni']); ?></td>
                                        <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            switch ($user['status']) {
                                                case 'activo':
                                                    $status_class = 'status-badge-activo';
                                                    break;
                                                case 'inactivo':
                                                    $status_class = 'status-badge-inactivo';
                                                    break;
                                                case 'pendiente':
                                                    $status_class = 'status-badge-pendiente';
                                                    break;
                                            }
                                            echo '<span class="' . $status_class . '">' . htmlspecialchars(ucfirst($user['status'])) . '</span>';
                                            ?>
                                        </td>
                                        <td><?php echo date('Y-m-d', strtotime($user['registration_date'])); ?></td>
                                        <td>
                                            <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-info me-1" title="Editar"><i class="bi bi-pencil-square"></i></a>
                                            <a href="delete_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-danger" title="Eliminar" onclick="return confirm('¿Está seguro de que desea eliminar este usuario? Esta acción es irreversible.');"><i class="bi bi-trash-fill"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No hay usuarios registrados en el sistema.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Paginación de usuarios" class="pagination-container">
                    <ul class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="manage_users.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
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