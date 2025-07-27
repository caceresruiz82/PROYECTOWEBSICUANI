<?php
/**
 * login.php
 *
 * Página de inicio de sesión para el sistema web de EsSalud Sicuani.
 * Permite a los usuarios (pacientes y administrativos) autenticarse
 * y acceder a las funcionalidades correspondientes a su rol.
 *
 * @package EssaludSicuaniWeb
 * @subpackage Pages
 * @author Yudelvis Caceres
 * @version 1.0.0
 * @date 2025-07-25
 */

// Iniciar la sesión PHP al principio de la página
// Esto es crucial para poder usar $_SESSION para mantener el estado del usuario.
session_start();

// Incluir la conexión a la base de datos
require_once '../includes/db_connection.php';
// Incluir funciones de ayuda (si las necesitamos más adelante para, por ejemplo, redirecciones)
// require_once '../includes/functions.php'; // Aún no lo hemos creado, pero lo tendremos en cuenta.

$message = ''; // Variable para almacenar mensajes de éxito o error

// Verificar si el usuario ya está logueado para redirigirlo
// Esto evita que un usuario ya autenticado vea la página de login.
if (isset($_SESSION['user_id'])) {
    // Si ya hay una sesión activa, redirigir al dashboard.
    // Asumimos que tendremos un dashboard principal o un redireccionador de roles.
    // Por ahora, redirigimos a una página de ejemplo, que crearemos después.
    header("Location: dashboard.php"); // Será el panel general o un redireccionador
    exit();
}

// Verificar si el formulario de inicio de sesión ha sido enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Recopilar y sanear los datos del formulario
    $email = htmlspecialchars(trim($_POST['email']));
    $password = $_POST['password']; // La contraseña no se sanea, se compara con el hash

    // 2. Validación básica
    if (empty($email) || empty($password)) {
        $message = '<div class="alert alert-danger">Por favor, ingrese su correo electrónico y contraseña.</div>';
    } else {
        // 3. Consultar la base de datos para verificar las credenciales
        // Incluimos password_hash, role_id y status para usarlos después de la verificación.
        $stmt = $conn->prepare("SELECT user_id, full_name, email, password_hash, role_id, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // 4. Verificar la contraseña cifrada
            // password_verify() es la función segura para comparar la contraseña ingresada
            // con el hash almacenado.
            if (password_verify($password, $user['password_hash'])) {
                // 5. Verificar el estado del usuario
                if ($user['status'] === 'activo') {
                    // Contraseña correcta y usuario activo: Iniciar sesión

                    // Regenerar ID de sesión para prevenir ataques de fijación de sesión
                    session_regenerate_id(true);

                    // Almacenar información del usuario en la sesión
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role_id'] = $user['role_id']; // Almacenar el ID del rol

                    // Actualizar last_login en la base de datos
                    $update_login_stmt = $conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?");
                    $update_login_stmt->bind_param("i", $user['user_id']);
                    $update_login_stmt->execute();
                    $update_login_stmt->close();

                    // Redirigir al usuario según su rol
                    // Aquí es donde la lógica de roles se vuelve importante.
                    switch ($user['role_id']) {
                        case 1: // Asumiendo role_id 1 para Administrador
                            header("Location: admin_dashboard.php");
                            break;
                        case 2: // Asumiendo role_id 2 para Director
                            header("Location: director_dashboard.php");
                            break;
                        case 3: // Asumiendo role_id 3 para Secretario
                            header("Location: secretary_dashboard.php");
                            break;
                        case 4: // Asumiendo role_id 4 para Admision
                            header("Location: admission_dashboard.php");
                            break;
                        case 5: // Asumiendo role_id 5 para Medico
                            header("Location: doctor_dashboard.php");
                            break;
                        case 6: // Asumiendo role_id 6 para Paciente (el que acabamos de registrar)
                            header("Location: patient_dashboard.php");
                            break;
                        default:
                            // Rol no reconocido o sin dashboard específico
                            header("Location: dashboard.php"); // Dashboard genérico
                            break;
                    }
                    exit(); // Terminar el script después de la redirección

                } elseif ($user['status'] === 'pendiente') {
                    $message = '<div class="alert alert-warning">Su cuenta está pendiente de activación. Por favor, revise su correo electrónico o espere la aprobación de un administrador.</div>';
                } else { // 'inactivo'
                    $message = '<div class="alert alert-danger">Su cuenta está inactiva. Contacte al administrador.</div>';
                }
            } else {
                // Contraseña incorrecta
                $message = '<div class="alert alert-danger">Correo electrónico o contraseña incorrectos.</div>';
            }
        } else {
            // Usuario no encontrado (correo incorrecto)
            $message = '<div class="alert alert-danger">Correo electrónico o contraseña incorrectos.</div>';
        }
        $stmt->close();
    }
}
// No cerrar la conexión aquí, ya que la página html puede necesitarla si se incluyen partes dinámicas.
// La conexión se cierra al finalizar el script.
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de Sesión - EsSalud Sicuani</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
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
        <div class="login-container">
            <div class="logo">
                <img src="../assets/images/logo_essalud.png" alt="Logo EsSalud">
            </div>
            <h2 class="text-center mb-4">Iniciar Sesión</h2>

            <?php echo $message; // Mostrar mensajes de éxito o error ?>

            <form action="login.php" method="POST">
                <div class="mb-3">
                    <label for="email" class="form-label">Correo Electrónico:</label>
                    <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña:</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Iniciar Sesión</button>
            </form>
            <p class="text-center mt-3">¿No tienes cuenta? <a href="register.php">Regístrate aquí</a></p>
            <p class="text-center mt-2"><a href="#">¿Olvidaste tu contraseña?</a></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>