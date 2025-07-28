<?php
require_once '../includes/session_manager.php';
require_once '../includes/db_connection.php';

$error_message = '';

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? 'Paciente';
    $dashboard_page = ($role === 'Medico') ? 'doctor_dashboard.php' : 'dashboard.php';
    header("Location: " . $dashboard_page);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty(trim($_POST['email'])) || empty(trim($_POST['password']))) {
        $error_message = 'Por favor, ingrese su email y contraseña.';
    } else {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $sql = "SELECT u.user_id, u.full_name, u.password_hash, r.role_name 
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                WHERE u.email = :email AND u.status = 'activo'";
        if ($stmt = $pdo->prepare($sql)) {
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            if ($stmt->execute()) {
                if ($stmt->rowCount() == 1) {
                    $user = $stmt->fetch();
                    if (password_verify($password, $user['password_hash'])) {
                        session_regenerate_id();
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['user_role'] = $user['role_name'];
                        
                        $redirect_page = ($user['role_name'] === 'Medico') ? 'doctor_dashboard.php' : 'dashboard.php';
                        header("Location: " . $redirect_page);
                        exit();
                    } else { $error_message = 'El email o la contraseña son incorrectos.'; }
                } else { $error_message = 'El email o la contraseña son incorrectos, o la cuenta no está activa.'; }
            } else { $error_message = '¡Ups! Algo salió mal.'; }
            unset($stmt);
        }
    }
    unset($pdo);
}

$page_title = 'Iniciar Sesión';
include_once '../templates/header.php';
?>

<h2><?php echo htmlspecialchars($page_title); ?></h2>
<p>Por favor, ingrese sus credenciales para iniciar sesión.</p>

<?php if (!empty($error_message)): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>
<?php if (isset($_GET['registration']) && $_GET['registration'] === 'success'): ?>
    <div class="alert alert-success">¡Registro completado! Su cuenta está pendiente de activación.</div>
<?php endif; ?>
<?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
    <div class="alert alert-success">¡Contraseña actualizada con éxito! Ya puede iniciar sesión.</div>
<?php endif; ?>

<form action="login.php" method="post" novalidate>
    <div>
        <label for="email">Correo Electrónico:</label>
        <input type="email" id="email" name="email" required>
    </div>
    <div>
        <label for="password">Contraseña:</label>
        <input type="password" id="password" name="password" required>
    </div>
    <div>
        <input type="submit" value="Iniciar Sesión" class="btn">
    </div>
</form>

<div style="margin-top: 1rem; display: flex; justify-content: space-between;">
    <p>¿No tienes una cuenta? <a href="register.php">Regístrate aquí</a>.</p>
    <p><a href="forgot_password.php">¿Olvidó su contraseña?</a></p>
</div>

<?php
include_once '../templates/footer.php';
?>