<?php
/**
 * Página de Verificación de Email - Paso 2: El usuario introduce el código.
 * AHORA GUARDA EL TIPO Y NÚMERO DE DOCUMENTO.
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.save_path', realpath(dirname(__FILE__) . '/../includes/sessions'));
    session_start();
}

// Si no hay datos de registro en la sesión, redirigir al inicio del registro.
if (!isset($_SESSION['registration_data'])) {
    header('Location: register.php');
    exit;
}

require_once '../includes/db_connection.php';
require_once '../includes/mail_sender.php';

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $submitted_code = trim($_POST['verification_code']);
    $session_data = $_SESSION['registration_data'];

    if (empty($submitted_code)) {
        $error_message = 'Por favor, ingrese el código de verificación.';
    } elseif ($submitted_code == $session_data['verification_code']) {
        // ¡Código correcto! Proceder a crear el usuario en la base de datos.
        try {
            // --- CORRECCIÓN CLAVE ---
            // Se añaden document_type y document_number a la consulta INSERT.
            $sql = "INSERT INTO users (full_name, email, password_hash, role_id, status, document_type, document_number) 
                    VALUES (:full_name, :email, :password_hash, :role_id, :status, :document_type, :document_number)";
            
            $stmt = $pdo->prepare($sql);
            
            $stmt->execute([
                ':full_name' => $session_data['full_name'],
                ':email' => $session_data['email'],
                ':password_hash' => $session_data['password_hash'],
                ':role_id' => 6, // Rol de Paciente
                ':status' => 'pendiente', // Estado pendiente de activación por admin
                ':document_type' => $session_data['document_type'],
                ':document_number' => $session_data['document_number']
            ]);
            
            // Limpiar los datos de la sesión para seguridad
            unset($_SESSION['registration_data']);
            
            // Enviar correo de bienvenida final
            $subject = "Bienvenido a " . APP_NAME;
            $body = "<h1>¡Hola, " . htmlspecialchars($session_data['full_name']) . "!</h1>
                     <p>Tu correo ha sido verificado y tu registro se ha completado con éxito.</p>
                     <p>Tu cuenta está actualmente <strong>pendiente de activación</strong> por parte de nuestro personal. Recibirás otra notificación cuando tu cuenta esté activa.</p>";
            send_email($session_data['email'], $session_data['full_name'],  $subject, $body);

            // Redirigir al login con mensaje de éxito
            header("location: login.php?registration=success");
            exit();

        } catch (PDOException $e) {
            // Verificar si el error es por duplicado de documento
            if ($e->errorInfo[1] == 1062) {
                 $error_message = 'Este número de documento ya ha sido registrado. Por favor, verifique sus datos.';
            } else {
                 $error_message = 'Hubo un error al crear tu cuenta. Por favor, intenta registrarte de nuevo.';
            }
            unset($_SESSION['registration_data']); // Limpiar sesión en caso de error
        }
    } else {
        // Código incorrecto
        $error_message = 'El código de verificación es incorrecto. Por favor, inténtelo de nuevo.';
    }
}


$page_title = 'Registro - Verificar Correo';
include_once '../templates/header.php';
?>

<h2><?php echo htmlspecialchars($page_title); ?></h2>
<p>Hemos enviado un código de 6 dígitos a <strong><?php echo htmlspecialchars($_SESSION['registration_data']['email']); ?></strong>. Por favor, ingréselo a continuación para completar su registro.</p>

<?php if ($error_message): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<form action="verify_email.php" method="POST">
    <div>
        <label for="verification_code">Código de Verificación:</label>
        <input type="text" id="verification_code" name="verification_code" required maxlength="6" pattern="\d{6}" title="Debe ser un código de 6 dígitos.">
    </div>
    <div>
        <input type="submit" value="Verificar y Crear Cuenta" class="btn">
    </div>
</form>
<p>¿No recibiste el código? <a href="register.php">Intenta registrarte de nuevo</a>.</p>

<?php
include_once '../templates/footer.php';
?>