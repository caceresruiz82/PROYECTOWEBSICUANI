<?php
/**
 * create_user.php
 *
 * Página para que un administrador cree una nueva cuenta de usuario en el sistema.
 * Permite al administrador asignar un rol y un estado inicial al nuevo usuario.
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
$roles = []; // Para almacenar los roles disponibles en la base de datos

// Obtener la lista de roles de la base de datos
$stmt_roles = $conn->prepare("SELECT role_id, role_name FROM roles ORDER BY role_name ASC");
$stmt_roles->execute();
$result_roles = $stmt_roles->get_result();
while ($row = $result_roles->fetch_assoc()) {
    $roles[$row['role_id']] = $row['role_name'];
}
$stmt_roles->close();

// --- Lógica para procesar la creación del nuevo usuario (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = htmlspecialchars(trim($_POST['full_name'] ?? ''));
    $email = htmlspecialchars(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone_number = htmlspecialchars(trim($_POST['phone_number'] ?? ''));
    $dni = htmlspecialchars(trim($_POST['dni'] ?? ''));
    $address = htmlspecialchars(trim($_POST['address'] ?? ''));
    $birth_date = htmlspecialchars(trim($_POST['birth_date'] ?? ''));
    $gender = htmlspecialchars(trim($_POST['gender'] ?? ''));
    $role_id = (int)($_POST['role_id'] ?? 0); // Convertir a int, 0 si no se recibe
    $status = htmlspecialchars(trim($_POST['status'] ?? 'pendiente'));

    $errors = [];

    // Validaciones
    if (empty($full_name)) { $errors[] = 'El nombre completo es obligatorio.'; }
    if (empty($email)) { $errors[] = 'El correo electrónico es obligatorio.'; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'El formato del correo electrónico es inválido.'; }
    if (empty($password)) { $errors[] = 'La contraseña es obligatoria.'; }
    elseif (strlen($password) < 8) { $errors[] = 'La contraseña debe tener al menos 8 caracteres.'; }
    if ($password !== $confirm_password) { $errors[] = 'Las contraseñas no coinciden.'; }
    if (empty($phone_number)) { $errors[] = 'El número de teléfono es obligatorio.'; }
    if (empty($dni)) { $errors[] = 'El DNI es obligatorio.'; }
    elseif (!preg_match('/^[0-9]{8}$/', $dni) && !preg_match('/^[0-9]{10}$/', $dni)) {
        $errors[] = 'El DNI debe contener solo 8 o 10 dígitos numéricos.';
    }
    if (empty($birth_date)) { $errors[] = 'La fecha de nacimiento es obligatoria.'; }
    if (empty($gender) || !in_array($gender, ['Masculino', 'Femenino', 'Otro'])) {
        $errors[] = 'Debe seleccionar un género válido.';
    }
    if (!array_key_exists($role_id, $roles)) {
        $errors[] = 'Rol seleccionado inválido.';
    }
    if (!in_array($status, ['activo', 'inactivo', 'pendiente'])) {
        $errors[] = 'Estado seleccionado inválido.';
    }

    // Verificar si el correo o DNI ya existen en la base de datos (para cualquier usuario)
    if (empty($errors)) {
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR dni = ?");
        $stmt_check->bind_param("ss", $email, $dni);
        $stmt_check->execute();
        $stmt_check->bind_result($count);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($count > 0) {
            $errors[] = 'El correo electrónico o el DNI ya están registrados.';
        }
    }

    if (empty($errors)) {
        // Cifrar la contraseña
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        // Preparar la consulta SQL para insertar el nuevo usuario
        $stmt_insert = $conn->prepare("INSERT INTO users (full_name, email, password_hash, phone_number, dni, address, birth_date, gender, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_insert->bind_param("ssssssssis", $full_name, $email, $password_hash, $phone_number, $dni, $address, $birth_date, $gender, $role_id, $status);

        if ($stmt_insert->execute()) {
            $_SESSION['create_user_message'] = '<div class="alert alert-success">Usuario creado exitosamente.</div>';
            header("Location: manage_users.php");
            exit();
        } else {
            $message = '<div class="alert alert-danger">Error al crear el usuario: ' . $stmt_insert->error . '</div>';
        }
        $stmt_insert->close();
    } else {
        // Si hay errores de validación, mostrarlos
        $message = '<div class="alert alert-danger"><ul>';
        foreach ($errors as $error) {
            $message .= '<li>' . $error . '</li>';
        }
        $message .= '</ul></div>';
    }
}

// Variables para prellenar el formulario en caso de error de POST
$full_name_val = $_POST['full_name'] ?? '';
$email_val = $_POST['email'] ?? '';
$phone_number_val = $_POST['phone_number'] ?? '';
$dni_val = $_POST['dni'] ?? '';
$address_val = $_POST['address'] ?? '';
$birth_date_val = $_POST['birth_date'] ?? '';
$gender_val = $_POST['gender'] ?? '';
$role_id_val = $_POST['role_id'] ?? '';
$status_val = $_POST['status'] ?? 'pendiente'; // Default a 'pendiente'

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Nuevo Usuario - Admin EsSalud Sicuani</title>
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
                <h4 class="mb-0">Crear Nuevo Usuario</h4>
            </div>
            <div class="card-body">
                <?php echo $message; // Mostrar mensajes de éxito o error ?>

                <form action="create_user.php" method="POST">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Nombre Completo:</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name_val); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico:</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email_val); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña:</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmar Contraseña:</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone_number" class="form-label">Número de Teléfono:</label>
                        <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($phone_number_val); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="dni" class="form-label">DNI:</label>
                        <input type="text" class="form-control" id="dni" name="dni" maxlength="15" value="<?php echo htmlspecialchars($dni_val); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Dirección:</label>
                        <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($address_val); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="birth_date" class="form-label">Fecha de Nacimiento:</label>
                        <input type="date" class="form-control" id="birth_date" name="birth_date" value="<?php echo htmlspecialchars($birth_date_val); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="gender" class="form-label">Género:</label>
                        <select class="form-select" id="gender" name="gender" required>
                            <option value="">Seleccione...</option>
                            <option value="Masculino" <?php echo ($gender_val == 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
                            <option value="Femenino" <?php echo ($gender_val == 'Femenino') ? 'selected' : ''; ?>>Femenino</option>
                            <option value="Otro" <?php echo ($gender_val == 'Otro') ? 'selected' : ''; ?>>Otro</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="role_id" class="form-label">Rol:</label>
                        <select class="form-select" id="role_id" name="role_id" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($roles as $id => $name): ?>
                                <option value="<?php echo $id; ?>" <?php echo ($role_id_val == $id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Estado:</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="">Seleccione...</option>
                            <option value="activo" <?php echo ($status_val == 'activo') ? 'selected' : ''; ?>>Activo</option>
                            <option value="inactivo" <?php echo ($status_val == 'inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                            <option value="pendiente" <?php echo ($status_val == 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-danger">Crear Usuario</button>
                    <a href="manage_users.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>