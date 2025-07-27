<?php
/**
 * register.php
 *
 * Página de registro de nuevos usuarios (Pacientes) para el sistema web de EsSalud Sicuani.
 * Contiene el formulario HTML para la recopilación de datos y procesará la lógica
 * de registro una vez enviado.
 *
 * @package EssaludSicuaniWeb
 * @subpackage Pages
 * @author Yudelvis Caceres
 * @version 1.0.0
 * @date 2025-07-25
 */

// Incluir la conexión a la base de datos
require_once '../includes/db_connection.php';
// Incluir funciones de ayuda (se creará más adelante para hashing de contraseñas, etc.)
// require_once '../includes/functions.php';

$message = ''; // Variable para almacenar mensajes de éxito o error

// Verificar si el formulario ha sido enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Recopilar y sanear los datos del formulario
    // trim() elimina espacios en blanco al inicio y final
    // htmlspecialchars() convierte caracteres especiales a entidades HTML para prevenir XSS
    $full_name = htmlspecialchars(trim($_POST['full_name']));
    $email = htmlspecialchars(trim($_POST['email']));
    $password = $_POST['password']; // La contraseña no se sanea directamente, se validará y hashificará
    $confirm_password = $_POST['confirm_password'];
    $phone_number = htmlspecialchars(trim($_POST['phone_number']));
    $dni = htmlspecialchars(trim($_POST['dni']));
    $address = htmlspecialchars(trim($_POST['address']));
    $birth_date = htmlspecialchars(trim($_POST['birth_date']));
    $gender = htmlspecialchars(trim($_POST['gender']));

    // 2. Validación de Datos (básica en el frontend y más robusta aquí)
    $errors = [];

    if (empty($full_name)) {
        $errors[] = 'El nombre completo es obligatorio.';
    }
    if (empty($email)) {
        $errors[] = 'El correo electrónico es obligatorio.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El formato del correo electrónico es inválido.';
    }
    if (empty($password)) {
        $errors[] = 'La contraseña es obligatoria.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Las contraseñas no coinciden.';
    }
    if (empty($phone_number)) {
        $errors[] = 'El número de teléfono es obligatorio.';
    }
    if (empty($dni)) {
        $errors[] = 'El DNI es obligatorio.';
    } elseif (!preg_match('/^[0-9]{8}$/', $dni) && !preg_match('/^[0-9]{10}$/', $dni)) { // Ejemplo para DNI 8 o 10 dígitos, ajustar según necesidad local.
        $errors[] = 'El DNI debe contener solo 8 o 10 dígitos numéricos.';
    }
    if (empty($birth_date)) {
        $errors[] = 'La fecha de nacimiento es obligatoria.';
    }
    if (empty($gender) || !in_array($gender, ['Masculino', 'Femenino', 'Otro'])) {
        $errors[] = 'Debe seleccionar un género válido.';
    }

    // 3. Verificar si el correo o DNI ya existen en la base de datos
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

    // Si no hay errores, proceder con el registro
    if (empty($errors)) {
        // Cifrar la contraseña
        // password_hash() es la función recomendada por PHP para cifrar contraseñas de forma segura.
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        // Asignar el role_id para 'Paciente' (asumiendo que 'Paciente' tiene role_id = 6 según nuestra inserción)
        // **IMPORTANTE: En un sistema real, primero consultaríamos el role_id de 'Paciente' desde la tabla roles.**
        // Por ahora, asumiremos que 'Paciente' es role_id = 6. Esto se mejorará después.
        $role_id_paciente = 6; 
        
        // El estado inicial es 'pendiente' hasta que un administrador lo active o se verifique el email
        $status = 'pendiente';

        // Preparar la consulta SQL para insertar el nuevo usuario
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, phone_number, dni, address, birth_date, gender, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // Vincular los parámetros a la consulta
        $stmt->bind_param("ssssssssis", $full_name, $email, $password_hash, $phone_number, $dni, $address, $birth_date, $gender, $role_id_paciente, $status);

        // Ejecutar la consulta
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">¡Registro exitoso! Tu cuenta está pendiente de activación por parte de un administrador.</div>';
            // Opcional: Redirigir al usuario a una página de confirmación
            // header("Location: registration_success.php");
            // exit();
        } else {
            $message = '<div class="alert alert-danger">Error al registrar el usuario: ' . $stmt->error . '</div>';
        }
        $stmt->close(); // Cerrar la declaración preparada
    } else {
        // Si hay errores de validación, mostrarlos
        $message = '<div class="alert alert-danger"><ul>';
        foreach ($errors as $error) {
            $message .= '<li>' . $error . '</li>';
        }
        $message .= '</ul></div>';
    }
}

// No cerrar la conexión aquí, ya que otras partes del sitio podrían necesitarla.
// La conexión se cierra al finalizar el script o cuando el objeto $conn es destruido.

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Paciente - EsSalud Sicuani</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .register-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo img {
            max-width: 150px;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="register-container">
            <div class="logo">
                <img src="../assets/images/logo_essalud.png" alt="Logo EsSalud">
            </div>
            <h2 class="text-center mb-4">Registro de Paciente</h2>

            <?php echo $message; // Mostrar mensajes de éxito o error ?>

            <form action="register.php" method="POST">
                <div class="mb-3">
                    <label for="full_name" class="form-label">Nombre Completo:</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Correo Electrónico:</label>
                    <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
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
                    <input type="text" class="form-control" id="phone_number" name="phone_number" required value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label for="dni" class="form-label">DNI:</label>
                    <input type="text" class="form-control" id="dni" name="dni" required maxlength="10" value="<?php echo htmlspecialchars($_POST['dni'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label for="address" class="form-label">Dirección:</label>
                    <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label for="birth_date" class="form-label">Fecha de Nacimiento:</label>
                    <input type="date" class="form-control" id="birth_date" name="birth_date" required value="<?php echo htmlspecialchars($_POST['birth_date'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label for="gender" class="form-label">Género:</label>
                    <select class="form-select" id="gender" name="gender" required>
                        <option value="">Seleccione...</option>
                        <option value="Masculino" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
                        <option value="Femenino" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Femenino') ? 'selected' : ''; ?>>Femenino</option>
                        <option value="Otro" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Otro') ? 'selected' : ''; ?>>Otro</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary w-100">Registrarme</button>
            </form>
            <p class="text-center mt-3">¿Ya tienes cuenta? <a href="login.php">Inicia Sesión aquí</a></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>