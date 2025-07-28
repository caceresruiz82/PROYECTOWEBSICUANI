<?php
require_once '../includes/session_manager.php';
require_once '../includes/db_connection.php';
require_once '../includes/mail_sender.php';

$message = '';
$message_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

    if ($email) {
        $stmt = $pdo->prepare("SELECT user_id, full_name FROM users WHERE email = :email AND status = 'activo'");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = new DateTime('now + 1 hour');
            $expires_str = $expires->format('Y-m-d H:i:s');
            $token_hash = hash('sha256', $token);

            $update_stmt = $pdo->prepare("UPDATE users SET reset_token = :token_hash, reset_token_expires = :expires WHERE user_id = :user_id");
            $update_stmt->execute([
                ':token_hash' => $token_hash,
                ':expires' => $expires_str,
                ':user_id' => $user['user_id']
            ]);

            $reset_link = APP_URL . '/pages/reset_password.php?token=' . $token;
            $subject = "Recuperación de Contraseña - " . APP_NAME;
            $body = "<h1>Recuperación de Contraseña</h1><p>Hola " . htmlspecialchars($user['full_name']) . ",</p><p>Haga clic en el siguiente enlace para restablecer su contraseña:</p><p><a href='" . $reset_link . "'>" . $reset_link . "</a></p><p>Este enlace es válido por una hora.</p>";
            
            send_email($email, $user['full_name'], $subject, $body);
        }
    }
    $message = "Si una cuenta con ese correo existe, hemos enviado un enlace para restablecer la contraseña.";
    $message_type = 'success';
}

$page_title = 'Recuperar Contraseña';
include_once '../templates/header.php';
?>
<h2><?php echo htmlspecialchars($page_title); ?></h2>
<p>Ingrese el correo electrónico asociado a su cuenta.</p>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<form action="forgot_password.php" method="POST">
    <div>
        <label for="email">Correo Electrónico:</label>
        <input type="email" id="email" name="email" required>
    </div>
    <div>
        <button type="submit" class="btn">Enviar Enlace de Recuperación</button>
    </div>
</form>

<?php include_once '../templates/footer.php'; ?>