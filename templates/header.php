<?php
require_once realpath(dirname(__FILE__) . '/../includes/session_manager.php');
require_once realpath(dirname(__FILE__) . '/../includes/config.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - EsSalud Sicuani' : 'EsSalud Sicuani'; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <style>
        .nav-item.dropdown { position: relative; }
        .dropdown-menu {
            display: none; position: absolute; background-color: #f9f9f9;
            min-width: 240px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1000; list-style: none; padding: 0; margin-top: 5px;
            border-radius: var(--border-radius); border: 1px solid #ddd;
        }
        .dropdown-menu li a {
            color: black; padding: 12px 16px; text-decoration: none;
            display: block; text-align: left; white-space: nowrap;
        }
        .dropdown-menu li a:hover { background-color: #f1f1f1; }
        .nav-item.dropdown:hover .dropdown-menu { display: block; }
        .main-nav a.dropdown-toggle { cursor: default; }
    </style>
</head>
<body>

<header class="main-header">
    <a href="<?php echo APP_URL; ?>/pages/inicio.php" class="logo-container">
        <img src="<?php echo APP_URL; ?>/assets/images/logo_essalud.png" alt="Logo de EsSalud Sicuani" style="height: 50px;">
        <span>EsSalud Sicuani</span>
    </a>
    
    <button id="mobile-menu-toggle" class="hamburger-button">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <nav class="main-nav">
        <div class="nav-top-info">
            <div class="datetime-container">
                <span id="current-date">Cargando...</span>
                <span id="current-time"></span>
            </div>
        </div>
        <ul>
            <?php $current_page = basename($_SERVER['SCRIPT_NAME']); ?>
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($current_page !== 'inicio.php'): ?><li><a href="inicio.php">Inicio</a></li><?php endif; ?>
                <li><a href="dashboard.php">Mi Panel</a></li>
                <li><a href="profile.php">Mi Perfil</a></li>
                <?php if ($_SESSION['user_role'] === 'Paciente'): ?>
                    <li><a href="request_appointment.php">Solicitar Cita</a></li>
                    <li><a href="my_appointments.php">Mis Citas</a></li>
                <?php endif; ?>
                <?php if ($_SESSION['user_role'] === 'Medico'): ?>
                    <li><a href="doctor_dashboard.php">Mis Citas Asignadas</a></li>
                <?php endif; ?>
                <?php if ($_SESSION['user_role'] === 'Administrador'): ?>
                    <li><a href="manage_users.php">Gestionar Usuarios</a></li>
                    <li><a href="reports.php">Reportes</a></li>
                <?php endif; ?>
                <?php if ($_SESSION['user_role'] === 'Admision' || $_SESSION['user_role'] === 'Administrador'): ?>
                    <li><a href="manage_patient_appointments.php">Gestionar Citas</a></li>
                    <li class="nav-item dropdown">
                        <a href="javascript:void(0)" class="dropdown-toggle">Utilidades ▾</a>
                        <ul class="dropdown-menu">
                            <li><a href="manage_specialties.php">Gestionar Especialidades</a></li>
                            <li><a href="schedule_availability.php">Programar Disponibilidad</a></li>
                            <li><a href="view_all_appointments.php">Listado General de Citas</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
                <li><a href="logout.php" class="btn btn-danger">Cerrar Sesión</a></li>
            <?php else: ?>
                <?php if ($current_page !== 'inicio.php'): ?><li><a href="inicio.php">Inicio</a></li><?php endif; ?>
                <li><a href="login.php">Iniciar Sesión</a></li>
                <li><a href="register.php">Registrarse</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>
<div class="container">
    <main class="main-content">