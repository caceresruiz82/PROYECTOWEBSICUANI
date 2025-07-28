<?php
/**
 * Página de Perfil de Usuario.
 * Permite al usuario ver y actualizar su información personal y cambiar su contraseña.
 */

require_once '../includes/auth_guard.php';
require_once '../includes/db_connection.php';

$user_id = $_SESSION['user_id'];
$profile_message = '';
$profile_message_type = '';
$password_message = '';
$password_message_type = '';

// --- LÓGICA PARA ACTUALIZAR DATOS DEL PERFIL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $full_name = trim(filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING));
    $phone_number = trim(filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_STRING));
    $address = trim(filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING));

    if (empty($full_name)) {
        $profile_message = 'El nombre completo no puede estar vacío.';
        $profile_message_type = 'error';
    } else {
        try {
            $sql = "UPDATE users SET full_name = :full_name, phone_number = :phone_number, address = :address WHERE user_id = :user_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':full_name' => $full_name,
                ':phone_number' => $phone_number,
                ':address' => $address,
                ':user_id' => $user_id
            ]);

            $_SESSION['full_name'] = $full_name;
            $profile_message = '¡Tus datos han sido actualizados con éxito!';
            $profile_message_type = 'success';
        } catch (PDOException $e) {
            $profile_message = 'Error al actualizar tus datos.';
            $profile_message_type = 'error';
        }
    }
}

// --- LÓGICA PARA CAMBIAR LA CONTRASEÑA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_message = 'Todos los campos son obligatorios.';
        $password_message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $password_message = 'La nueva contraseña y su confirmación no coinciden.';
        $password_message_type = 'error';
    } elseif (strlen($new_password) < 6) {
        $password_message = 'La nueva contraseña debe tener al menos 6 caracteres.';
        $password_message_type = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $user_id]);
            $user = $stmt->fetch();

            if ($user && password_verify($current_password, $user['password_hash'])) {
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $pdo->prepare("UPDATE users SET password_hash = :password_hash WHERE user_id = :user_id");
                $update_stmt->execute([':password_hash' => $new_password_hash, ':user_id' => $user_id]);

                $password_message = '¡Contraseña cambiada con éxito!';
                $password_message_type = 'success';
            } else {
                $password_message = 'La contraseña actual que ingresó es incorrecta.';
                $password_message_type = 'error';
            }
        } catch (PDOException $e) {
            $password_message = 'Error al cambiar la contraseña.';
            $password_message_type = 'error';
        }
    }
}

// Obtener los datos actuales del usuario para mostrarlos en el formulario
try {
    $stmt = $pdo->prepare("SELECT full_name, email, phone_number, document_type, document_number, address FROM users WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    $user = null;
    $profile_message = "Error al cargar los datos del perfil.";
    $profile_message_type = 'error';
}

$page_title = 'Mi Perfil';
include_once '../templates/header.php';
?>

<h2><?php echo htmlspecialchars($page_title); ?></h2>
<p>Aquí puedes ver y actualizar tu información de contacto, y cambiar tu contraseña.</p>

<div class="profile-section">
    <h3>Información Personal</h3>
    <?php if ($profile_message): ?>
        <div class="alert alert-<?php echo $profile_message_type; ?>"><?php echo htmlspecialchars($profile_message); ?></div>
    <?php endif; ?>

    <?php if ($user): ?>
    <form action="profile.php" method="POST">
        <input type="hidden" name="action" value="update_profile">
        <div>
            <label for="document_type">Tipo de Documento (no se puede cambiar):</label>
            <input type="text" value="<?php echo htmlspecialchars($user['document_type']); ?>" disabled>
        </div>
        <div>
            <label for="document_number">Número de Documento (no se puede cambiar):</label>
            <input type="text" value="<?php echo htmlspecialchars($user['document_number']); ?>" disabled>
        </div>
        <div>
            <label for="email">Correo Electrónico (no se puede cambiar):</label>
            <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
        </div>
        <div>
            <label for="full_name">Nombre Completo:</label>
            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
        </div>
        <div>
            <label for="phone_number">Número de Teléfono:</label>
            <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number']); ?>">
        </div>
        <div>
            <label for="address">Dirección:</label>
            <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($user['address']); ?>">
        </div>
        <div>
            <button type="submit" class="btn">Actualizar Información</button>
        </div>
    </form>
    <?php else: ?>
        <div class="alert alert-error">No se pudieron cargar los datos del perfil.</div>
    <?php endif; ?>
</div>

<div class="profile-section">
    <h3>Cambiar Contraseña</h3>
    <?php if ($password_message): ?>
        <div class="alert alert-<?php echo $password_message_type; ?>"><?php echo htmlspecialchars($password_message); ?></div>
    <?php endif; ?>

    <form action="profile.php" method="POST">
        <input type="hidden" name="action" value="change_password">
        <div>
            <label for="current_password">Contraseña Actual:</label>
            <input type="password" id="current_password" name="current_password" required>
        </div>
        <div>
            <label for="new_password">Nueva Contraseña:</label>
            <input type="password" id="new_password" name="new_password" required>
        </div>
        <div>
            <label for="confirm_password">Confirmar Nueva Contraseña:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        <div>
            <button type="submit" class="btn">Cambiar Contraseña</button>
        </div>
    </form>
</div>

<style>
    .profile-section { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #eee; }
</style>

<?php
include_once '../templates/footer.php';
?>