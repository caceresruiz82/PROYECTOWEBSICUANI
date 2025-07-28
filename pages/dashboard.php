<?php
/**
 * Panel de Control Principal (Alineado con el esquema de BD final)
 */

require_once '../includes/auth_guard.php';

$page_title = 'Panel de Control';

include_once '../templates/header.php';
?>

<h2>¡Bienvenido(a), <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
<p>Has iniciado sesión en el portal de EsSalud Sicuani como <strong><?php echo htmlspecialchars($_SESSION['user_role']); ?></strong>. Desde aquí puedes acceder a las funcionalidades disponibles para tu perfil.</p>

<div class="dashboard-links">
    <h3>Accesos Rápidos</h3>
    <ul>
        <?php
        // CORRECCIÓN: Lógica de enlaces con los nombres de rol de la BD
        $role = $_SESSION['user_role'];

        if ($role === 'Paciente') {
            echo '<li><a href="request_appointment.php" class="btn">Solicitar una Nueva Cita</a></li>';
            echo '<li><a href="my_appointments.php" class="btn">Ver Mis Citas Programadas</a></li>';
        } elseif ($role === 'Administrador') {
            echo '<li><a href="manage_users.php" class="btn">Gestionar Usuarios del Sistema</a></li>';
            echo '<li><a href="manage_slots.php" class="btn">Gestionar Horarios de Atención</a></li>';
        } elseif ($role === 'Admision') {
            echo '<li><a href="manage_patient_appointments.php" class="btn">Gestionar Citas de Pacientes</a></li>';
        }
        ?>
    </ul>
</div>

<style>
    .dashboard-links ul { list-style: none; padding: 0; display: flex; flex-wrap: wrap; gap: 1rem; }
</style>

<?php
include_once '../templates/footer.php';
?>