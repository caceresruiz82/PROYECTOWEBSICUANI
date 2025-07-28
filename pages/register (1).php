<?php
/**
 * Página de Registro de Usuarios - Paso 1: Ahora con múltiples tipos de documento.
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.save_path', realpath(dirname(__FILE__) . '/../includes/sessions'));
    session_start();
}

require_once '../includes/db_connection.php';
require_once '../includes/mail_sender.php';

// Inicializar todas las variables
$full_name = $email = $document_type = $document_number = "";
$full_name_err = $email_err = $password_err = $confirm_password_err = $document_err = $general_err = "";

// Definir los tipos de documento permitidos para la validación
$allowed_doc_types = ['DNI', 'CE', 'Pasaporte', 'Otros'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- Validación de Tipo y Número de Documento ---
    $document_type = trim($_POST["document_type"]);
    $document_number = trim($_POST["document_number"]);

    if (empty($document_type) || !in_array($document_type, $allowed_doc_types)) {
        $document_err = "Por favor, seleccione un tipo de documento válido.";
    }
    if (empty($document_number)) {
        $document_err = "Por favor, ingrese su número de documento.";
    }

    // --- Validación del nombre completo ---
    $input_full_name = trim($_POST["full_name"]);
    if (empty($input_full_name)) {
        $full_name_err = "Por favor, ingrese su nombre completo.";
    } else {
        $full_name = $input_full_name;
    }

    // --- Validación del email ---
    $input_email = trim($_POST["email"]);
    if (empty($input_email)) {
        $email_err = "Por favor, ingrese un correo electrónico.";
    } elseif (!filter_var($input_email, FILTER_VALIDATE_EMAIL)) {
        $email_err = "Por favor, ingrese un correo electrónico válido.";
    } else {
        // Verificar si el correo ya existe
        $sql_check_email = "SELECT user_id FROM users WHERE email = :email";
        if ($stmt_check_email = $pdo->prepare($sql_check_email)) {
            $stmt_check_email->bindParam(':email', $input_email, PDO::PARAM_STR);
            if ($stmt_check_email->execute() && $stmt_check_email->rowCount() > 0) {
                $email_err = "Este correo electrónico ya está registrado.";
            } else {
                $email = $input_email;
            }
            unset($stmt_check_email);
        }
    }

    // --- Validación de Contraseñas ---
    // (Lógica sin cambios)
    $input_password = trim($_POST["password"]);
    if (empty($input_password)) { $password_err = "Por favor, ingrese una contraseña."; }
    elseif (strlen($input_password) < 6) { $password_err = "La contraseña debe tener al menos 6 caracteres."; }
    else { $password = $input_password; }
    
    $input_confirm_password = trim($_POST["confirm_password"]);
    if (empty($input_confirm_password)) { $confirm_password_err = "Por favor, confirme la contraseña."; }
    elseif (empty($password_err) && ($password != $input_confirm_password)) { $confirm_password_err = "Las contraseñas no coinciden."; }

    // Si no hay errores, proceder a enviar el código de verificación
    if (empty($full_name_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err) && empty($document_err) && empty($general_err)) {
        
        $verification_code = random_int(100000, 999999);
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Guardar los datos del registro, incluyendo los nuevos campos, en la sesión
        $_SESSION['registration_data'] = [
            'full_name' => $full_name,
            'email' => $email,
            'password_hash' => $hashed_password,
            'document_type' => $document_type,
            'document_number' => $document_number,
            'verification_code' => $verification_code,
            'timestamp' => time()
        ];

        $subject = "Tu código de verificación para " . APP_NAME;
        $body = "<h1>Verifica tu correo electrónico</h1><p>Hola " . htmlspecialchars($full_name) . ",</p><p>Usa el siguiente código de 6 dígitos para completar tu registro:</p><h2 style='text-align:center; letter-spacing: 5px; font-size: 28px;'>" . $verification_code . "</h2>";

        if (send_email($email, $full_name, $subject, $body)) {
            header("location: verify_email.php");
            exit();
        } else {
            $general_err = "No se pudo enviar el correo de verificación. Inténtelo de nuevo.";
        }
    }
}

$page_title = 'Registro - Paso 1';
include_once '../templates/header.php';
?>

<h2><?php echo htmlspecialchars($page_title); ?></h2>
<p>Complete este formulario para iniciar el registro de su cuenta.</p>

<?php if(!empty($general_err)) echo '<div class="alert alert-error">' . htmlspecialchars($general_err) . '</div>'; ?>

<form action="register.php" method="post" novalidate>
    <div>
        <label for="document_type">Tipo de Documento:</label>
        <select name="document_type" id="document_type" required>
            <option value="DNI" <?php if(isset($document_type) && $document_type == 'DNI') echo 'selected'; ?>>DNI</option>
            <option value="CE" <?php if(isset($document_type) && $document_type == 'CE') echo 'selected'; ?>>Carnet de Extranjería</option>
            <option value="Pasaporte" <?php if(isset($document_type) && $document_type == 'Pasaporte') echo 'selected'; ?>>Pasaporte</option>
            <option value="Otros" <?php if(isset($document_type) && $document_type == 'Otros') echo 'selected'; ?>>Otros</option>
        </select>
    </div>

    <div>
        <label for="document_number">Número de Documento:</label>
        <input type="text" id="document_number" name="document_number" value="<?php echo htmlspecialchars($document_number); ?>" required>
        <small id="dni-message" class="dni-message"></small> <?php if(!empty($document_err)) echo '<div class="alert alert-error" style="margin-top: 5px;">' . htmlspecialchars($document_err) . '</div>'; ?>
    </div>
    
    <div>
        <label for="full_name">Nombre Completo:</label>
        <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
        <?php if(!empty($full_name_err)) echo '<div class="alert alert-error" style="margin-top: 5px;">' . htmlspecialchars($full_name_err) . '</div>'; ?>
    </div>
    
    <div>
        <label for="email">Correo Electrónico:</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
        <?php if(!empty($email_err)) echo '<div class="alert alert-error" style="margin-top: 5px;">' . htmlspecialchars($email_err) . '</div>'; ?>
    </div>

    <div>
        <label for="password">Contraseña (mínimo 6 caracteres):</label>
        <input type="password" id="password" name="password" required>
        <?php if(!empty($password_err)) echo '<div class="alert alert-error" style="margin-top: 5px;">' . htmlspecialchars($password_err) . '</div>'; ?>
    </div>

    <div>
        <label for="confirm_password">Confirmar Contraseña:</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
        <?php if(!empty($confirm_password_err)) echo '<div class="alert alert-error" style="margin-top: 5px;">' . htmlspecialchars($confirm_password_err) . '</div>'; ?>
    </div>

    <div>
        <input type="submit" value="Continuar" class="btn">
    </div>
</form>

<p>¿Ya tienes una cuenta? <a href="login.php">Inicia sesión aquí</a>.</p>

<style>
    .dni-message { display: block; margin-top: 5px; font-size: 0.9rem; }
    .dni-message.loading { color: #0056A0; }
    .dni-message.success { color: #155724; }
    .dni-message.error { color: #721c24; }
</style>

<?php
include_once '../templates/footer.php';
?>