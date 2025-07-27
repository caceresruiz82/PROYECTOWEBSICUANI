<?php
/**
 * dashboard.php
 *
 * Página de redirección general o dashboard por defecto.
 * Si el usuario ya está logueado, redirige a su panel específico según su rol.
 * Si no está logueado, redirige al login.
 *
 * @package EssaludSicuaniWeb
 * @subpackage Pages
 * @author Yudelvis Caceres
 * @version 1.0.0
 * @date 2025-07-25
 */

session_start();

// Si no hay sesión iniciada, redirigir al login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Redirigir según el rol del usuario
switch ($_SESSION['role_id']) {
    case 1: // Administrador
        header("Location: admin_dashboard.php");
        break;
    case 2: // Director
        header("Location: director_dashboard.php");
        break;
    case 3: // Secretario
        header("Location: secretary_dashboard.php");
        break;
    case 4: // Admision
        header("Location: admission_dashboard.php");
        break;
    case 5: // Medico
        header("Location: doctor_dashboard.php");
        break;
    case 6: // Paciente
        header("Location: patient_dashboard.php");
        break;
    default:
        // Si el rol no es reconocido o no tiene un dashboard específico,
        // mostrar un mensaje de error o una página de acceso denegado.
        echo "<h1>Acceso Denegado o Rol No Reconocido</h1>";
        echo "<p>Su rol no tiene un panel asignado o no tiene permisos para acceder a esta sección.</p>";
        echo "<p><a href='logout.php'>Cerrar Sesión</a></p>";
        break;
}
exit(); // Importante para detener la ejecución después de la redirección

?>