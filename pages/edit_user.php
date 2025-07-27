<?php
/**
 * edit_user.php
 *
 * Página para editar la información de un usuario existente.
 * Accesible solo por administradores. Permite modificar datos personales,
 * estado de cuenta y rol del usuario.
 *
 * @package EssaludSicuaniWeb
 * @subpackage Pages
 * @author Yudelvis Caceres
 * @version 1.0.0
 * @date 2025-07-25
 */

session_start();

require_once '../includes/db_connection.php';

// --- Protección de la Página ---
// 1. Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Verificar si el usuario tiene el rol de Administrador (role_id = 1)
if ($_SESSION['role_id'] != 1) {
    header("Location: dashboard.php");
    exit();
}

$message = '';
$user_data = null;
$roles = []; // Para almacenar los roles disponibles en la base de datos

// Obtener la lista de roles de la base de datos
$stmt_roles = $conn->prepare("SELECT role_id, role_name FROM roles ORDER BY role_name ASC");
$stmt_roles->execute();
$result_roles = $stmt_roles->get_result();
while ($row = $result_roles->fetch_assoc()) {
    $roles[$row['role_id']] = $row['role_name'];
}
$stmt_roles->close();

// --- Lógica para cargar los datos del usuario a editar ---
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = (int)$_GET['id'];

    // Prevenir que un administrador edite su propio rol o estado desde aquí si es el único admin activo
    // (Esta es una mejora de seguridad que se puede implementar más adelante si se desea)

    $stmt = $conn->prepare("SELECT user_id, full_name, email, phone_number, dni, address, birth_date, gender, role_id, status FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user_data = $result->fetch_assoc();
    } else {
        $message = '<div class="alert alert-danger">Usuario no encontrado.</div>';
        $user_data = null; // Reiniciar para no mostrar formulario si no se encuentra
    }
    $stmt->close();
} elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Si no hay ID en GET y no es un POST, significa que se accedió sin un ID válido
    $message = '<div class="alert alert-warning">Debe especificar un ID de usuario para editar.</div>';
}


// --- Lógica para procesar la actualización del usuario (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    $full_name = htmlspecialchars(trim($_POST['full_name']));
    $email = htmlspecialchars(trim($_POST['email']));
    $phone_number = htmlspecialchars(trim($_POST['phone_number']));
    $dni = htmlspecialchars(trim($_POST['dni']));
    $address = htmlspecialchars(trim($_POST['address']));
    $birth_date = htmlspecialchars(trim($_POST['birth_date']));
    $gender = htmlspecialchars(trim($_POST['gender']));
    $role_id = (int)$_POST['role_id'];
    $status = htmlspecialchars(trim($_POST['status']));

    $errors = [];

    // Validaciones
    if (empty($full_name) || empty($email) || empty($phone_number) || empty($dni) || empty($birth_date) || empty($gender) || empty($role_id) || empty($status)) {
        $errors[] = 'Todos los campos obligatorios deben ser llenados.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El formato del correo electrónico es inválido.';
    }
    if (!preg_match('/^[0-9]{8}$/', $dni) && !preg_match('/^[0-9]{10}$/', $dni)) { // DNI 8 o 10 dígitos, ajustar.
        $errors[] = 'El DNI debe contener solo 8 o 10 dígitos numéricos.';
    }
    if (!array_key_exists($role_id, $roles)) {
        $errors[] = 'Rol seleccionado inválido.';
    }
    if (!in_array($status, ['activo', 'inactivo', 'pendiente'])) {
        $errors[] = 'Estado seleccionado inválido.';
    }

    // Verificar si el email o DNI ya existen para OTRO usuario
    if (empty($errors)) {
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM users WHERE (email = ? OR dni = ?) AND user_id != ?");
        $stmt_check->bind_param("ssi", $email, $dni, $user_id);
        $stmt_check->execute();
        $stmt_check->bind_result($count);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($count > 0) {
            $errors[] = 'El correo electrónico o el DNI ya están registrados por otro usuario.';
        }
    }

    if (empty($errors)) {
        // Preparar la consulta SQL para actualizar el usuario
        $stmt_update = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone_number = ?, dni = ?, address = ?, birth_date = ?, gender = ?, role_id = ?, status = ? WHERE user_id = ?");
        $stmt_update->bind_param("sssssssiis", $full_name, $email, $phone_number, $dni, $address, $birth_date, $gender, $role_id, $status, $user_id);

        if ($stmt_update->execute()) {
            $message = '<div class="alert alert-success">Usuario actualizado exitosamente.</div>';
            // Opcional: Recargar los datos del usuario desde la DB para reflejar los cambios en el formulario
            $stmt_re_fetch = $conn->prepare("SELECT user_id, full_name, email, phone_number, dni, address, birth_date, gender, role_id, status FROM users WHERE user_id = ?");
            $stmt_re_fetch->bind_param("i", $user_id);
            $stmt_re_fetch->execute();
            $user_data = $stmt_re_fetch->get_result()->fetch_assoc();
            $stmt_re_fetch->close();

        } else {
            $message = '<div class="alert alert-danger">Error al actualizar el usuario: ' . $stmt_update->error . '</div>';
        }
        $stmt_update->close();
    } else {
        $message = '<div class="alert alert-danger"><ul>';
        foreach ($errors as $error) {
            $message .= '<li>' . $error . '</li>';
        }
        $message .= '</ul></div>';
        // Mantener los datos del POST para que el usuario no pierda lo que escribió
        $user_data = $_POST;
        $user_data['user_id'] = $user_id; // Asegurar que el ID se mantenga
    }
}

// Asegurarse de que $user_data tenga algo si se cargó via GET pero falló la validación POST
$display_user_data = $user_data;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($errors)) {
    // Si hubo errores en POST, usamos los datos del POST para rellenar el formulario
    // Aseguramos que los valores sean htmlspecialchars para evitar XSS
    foreach ($display_user_data as $key => $value) {
        if (!is_array($value)) { // No aplicar htmlspecialchars a arrays como roles, etc.
            $display_user_data[$key] = htmlspecialchars($value);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario - Admin EsSalud Sicuani</title>
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
                        <a class="nav-link" href="#">Configuración</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="#">Reportes</a>
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
                <h4 class="mb-0">Editar Usuario</h4>
            </div>
            <div class="card-body">
                <?php echo $message; // Mostrar mensajes de éxito o error ?>

                <?php if ($display_user_data): ?>
                <form action="edit_user.php" method="POST">
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($display_user_data['user_id']); ?>">

                    <div class="mb-3">
                        <label for="full_name" class="form-label">Nombre Completo:</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo $display_user_data['full_name']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico:</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo $display_user_data['email']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone_number" class="form-label">Número de Teléfono:</label>
                        <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?php echo $display_user_data['phone_number']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="dni" class="form-label">DNI:</label>
                        <input type="text" class="form-control" id="dni" name="dni" value="<?php echo $display_user_data['dni']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Dirección:</label>
                        <input type="text" class="form-control" id="address" name="address" value="<?php echo $display_user_data['address']; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="birth_date" class="form-label">Fecha de Nacimiento:</label>
                        <input type="date" class="form-control" id="birth_date" name="birth_date" value="<?php echo $display_user_data['birth_date']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="gender" class="form-label">Género:</label>
                        <select class="form-select" id="gender" name="gender" required>
                            <option value="">Seleccione...</option>
                            <option value="Masculino" <?php echo ($display_user_data['gender'] == 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
                            <option value="Femenino" <?php echo ($display_user_data['gender'] == 'Femenino') ? 'selected' : ''; ?>>Femenino</option>
                            <option value="Otro" <?php echo ($display_user_data['gender'] == 'Otro') ? 'selected' : ''; ?>>Otro</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="role_id" class="form-label">Rol:</label>
                        <select class="form-select" id="role_id" name="role_id" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($roles as $id => $name): ?>
                                <option value="<?php echo $id; ?>" <?php echo ($display_user_data['role_id'] == $id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Estado:</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="">Seleccione...</option>
                            <option value="activo" <?php echo ($display_user_data['status'] == 'activo') ? 'selected' : ''; ?>>Activo</option>
                            <option value="inactivo" <?php echo ($display_user_data['status'] == 'inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                            <option value="pendiente" <?php echo ($display_user_data['status'] == 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-danger">Actualizar Usuario</button>
                    <a href="manage_users.php" class="btn btn-secondary">Cancelar</a>
                </form>
                <?php else: ?>
                    <p class="text-center">No se pudieron cargar los datos del usuario o no se especificó un ID válido.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>