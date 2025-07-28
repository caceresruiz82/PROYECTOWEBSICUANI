<?php
require_once '../includes/session_manager.php';
require_once '../includes/db_connection.php';
require_once '../includes/config.php';
require_once '../includes/mail_sender.php';

$token_is_valid = false;
$error_message = '';
$user_id = null;
$token = $_GET['token'] ?? '';

if (!empty($token)) {
    $token_hash = hash('sha256', $token);
    $now = new DateTime();
    $now_str = $now->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE reset_token = :token_hash AND reset_token_expires > :now");
    $stmt->execute([':token_hash' => $token_hash, ':now' => $now_str]);
    $user = $stmt->fetch();

    if ($user) {
        $token_is_valid = true;
        $user_id = $user['user_id'];
    } else {
        $error_message = "El enlace de recuperación no es válido o ha expirado.";
    }
} else {
    $error_message = "No se ha proporcionado un token de recuperación.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_is_valid) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $user_id_from_form = $_POST['user_id'];

    if ($user_id != $user_id_from_form) {
        $error_message = "Error de validación. Intente de nuevo.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Las contraseñas no coinciden.';
    } elseif (strlen($new_password) < 6) {
        $error_message = 'La nueva contraseña debe tener al menos 6 caracteres.';
    } else {
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $pdo->prepare("UPDATE users SET password_hash = :password_hash, reset_token = NULL, reset_token_expires = NULL WHERE user_id = :user_id");
        $update_stmt->execute([':password_hash' => $new_password_hash, ':user_id' => $user_id_from_form]);
        
        $user_stmt = $pdo->prepare("SELECT u.full_name, u.email, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = :user_id");
        $user_stmt->execute([':user_id' => $user_id_from_form]);
        $user_data = $user_stmt->fetch();

        if ($user_data) {
            // --- CORRECCIÓN CLAVE EN EL CUERPO DEL EMAIL ---
            $subject = "Confirmación de cambio de contraseña - " . APP_NAME;
            $whatsapp_number_clean = str_replace(['+', ' '], '', SUPPORT_WHATSAPP);
            $body = "<h1>Hola, " . htmlspecialchars($user_data['full_name']) . "</h1>
                     <p>Te confirmamos que la contraseña de tu cuenta ha sido actualizada exitosamente.</p>
                     <p><strong>Si tú no realizaste este cambio</strong>, por favor, contacta a nuestro equipo de soporte de inmediato para proteger tu cuenta:</p>
                     <ul>
                        <li><strong>Email:</strong> <a href='mailto:" . SUPPORT_EMAIL . "'>" . SUPPORT_EMAIL . "</a></li>
                        <li><strong>WhatsApp (solo chat):</strong> <a href='https://wa.me/" . $whatsapp_number_clean . "'>" . SUPPORT_WHATSAPP . "</a></li>
                     </ul>";
            send_email($user_data['email'], $user_data['full_name'], $subject, $body);

            // Login automático
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user_id_from_form;
            $_SESSION['full_name'] = $user_data['full_name'];
            $_SESSION['user_role'] = $user_data['role_name'];

            $dashboard_page = ($user_data['role_name'] === 'Medico') ? 'doctor_dashboard.php' : 'dashboard.php';
            header("Location: " . $dashboard_page);
            exit();
        }
        
        header("Location: login.php?reset=success");
        exit();
    }
}

$page_title = 'Restablecer Contraseña';
include_once '../templates/header.php';
?>

<h2><?php echo htmlspecialchars($page_title); ?></h2>

<?php if ($error_message): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<?php if ($token_is_valid): ?>
    <p>Por favor, establezca su nueva contraseña.</p>
    <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST">
        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
        <div>
            <label for="new_password">Nueva Contraseña:</label>
            <input type="password" id="new_password" name="new_password" required>
        </div>
        <div>
            <label for="confirm_password">Confirmar Nueva Contraseña:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        <div>
            <button type="submit" class="btn">Guardar y Entrar</button>
        </div>
    </form>
<?php endif; ?>

<?php include_once '../templates/footer.php'; ?>