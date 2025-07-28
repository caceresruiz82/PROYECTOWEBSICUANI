<?php
/**
 * Página de Inicio (Landing Page) de la aplicación.
 */

require_once '../includes/session_manager.php';

// Si el usuario ya está logueado, redirigirlo a su panel correspondiente.
if (isset($_SESSION['user_id'])) {
    $dashboard_page = 'dashboard.php';
    if ($_SESSION['user_role'] === 'Medico') {
        $dashboard_page = 'doctor_dashboard.php';
    }
    header("Location: " . $dashboard_page);
    exit;
}

$page_title = 'Bienvenido';
include_once '../templates/header.php';
?>

<div class="hero-section">
    <h1>Bienvenido al Portal de Teleconsultas de EsSalud Sicuani</h1>
    <p>Agende y gestione sus citas de interconsulta a distancia de manera fácil y segura.</p>
    <div class="hero-actions">
        <a href="login.php" class="btn btn-large">Iniciar Sesión</a>
        <a href="register.php" class="btn btn-large btn-secondary">Registrarse</a>
    </div>
</div>

<div class="features-section">
    <div class="feature">
        <h3>Agendamiento Rápido</h3>
        <p>Encuentre y solicite una cita en la especialidad que necesita en pocos minutos.</p>
    </div>
    <div class="feature">
        <h3>Gestión Centralizada</h3>
        <p>Vea el historial de todas sus citas, confirme su estado y gestione sus solicitudes desde un solo lugar.</p>
    </div>
    <div class="feature">
        <h3>Comunicación Segura</h3>
        <p>Reciba notificaciones por correo y SMS para no olvidar ninguna cita importante.</p>
    </div>
</div>

<style>
    .hero-section {
        text-align: center;
        padding: 4rem 1rem;
        background-color: #f4f7fc;
        border-radius: var(--border-radius);
    }
    .hero-section h1 {
        font-size: 2.5rem;
        color: var(--primary-color);
        margin-bottom: 1rem;
    }
    .hero-section p {
        font-size: 1.2rem;
        color: #555;
        max-width: 600px;
        margin: 0 auto 2rem auto;
    }
    .hero-actions .btn {
        margin: 0.5rem;
    }
    .btn.btn-large {
        padding: 15px 30px;
        font-size: 1.1rem;
    }
    .btn-secondary {
        background-color: var(--white);
        color: var(--primary-color);
        border: 2px solid var(--primary-color);
    }
    .btn-secondary:hover {
        background-color: var(--primary-color);
        color: var(--white);
    }
    .features-section {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 2rem;
        margin-top: 3rem;
        text-align: center;
    }
    .feature h3 {
        color: var(--primary-color);
    }
</style>

<?php
include_once '../templates/footer.php';
?>