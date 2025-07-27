<?php
/**
 * patient_dashboard.php
 *
 * Panel personal del paciente para el sistema web de EsSalud Sicuani.
 * Esta página solo es accesible para usuarios autenticados con el rol de Paciente.
 * Muestra información relevante y opciones para que el paciente gestione sus servicios.
 *
 * @package EssaludSicuaniWeb
 * @subpackage Pages
 * @author Yudelvis Caceres
 * @version 1.0.1 // Versión actualizada para incluir enlace a request_appointment.php y my_appointments.php
 * @date 2025-07-26
 */

// Iniciar la sesión PHP al principio de la página
session_start();

// Incluir la conexión a la base de datos
require_once '../includes/db_connection.php';
// Incluir funciones de ayuda para autenticación y redirección (crearemos esto pronto)
// require_once '../includes/auth_helper.php'; // Lo usaremos para verificar el rol

// --- Protección de la Página ---
// 1. Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['user_id'])) {
    // Si no ha iniciado sesión, redirigir a la página de login
    header("Location: login.php");
    exit();
}

// 2. Verificar si el usuario tiene el rol correcto (Paciente)
// Asumimos que el role_id para 'Paciente' es 6, según nuestra inserción inicial.
if ($_SESSION['role_id'] != 6) { // Si el ID del rol no es 6 (Paciente)
    // Redirigir a una página de "Acceso Denegado" o al dashboard general
    header("Location: dashboard.php"); // Crearás dashboard.php más adelante para redirección general
    exit();
}

// Si llega hasta aquí, el usuario es un paciente autenticado.
$full_name = htmlspecialchars($_SESSION['full_name']); // Obtener el nombre del usuario de la sesión

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel del Paciente - EsSalud Sicuani</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .navbar {
            background-color: #007bff; /* Color primario de Bootstrap para la barra de navegación */
        }
        .navbar .nav-link {
            color: #ffffff !important;
        }
        .navbar .nav-link:hover {
            color: #e2e6ea !important;
        }
        .dashboard-container {
            padding: 30px;
            margin-top: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .welcome-section {
            background-color: #e9ecef;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            text-align: center;
        }
        .feature-card {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            height: 100%; /* Para que todas las tarjetas tengan la misma altura */
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .feature-card h5 {
            margin-top: 15px;
            font-weight: bold;
            color: #007bff;
        }
        .feature-card .icon {
            font-size: 3rem;
            color: #007bff;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="patient_dashboard.php">
                <img src="../assets/images/logo_essalud.png" alt="Logo EsSalud" width="40" class="d-inline-block align-text-top me-2">
                Panel Paciente
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="patient_dashboard.php">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="request_appointment.php">Solicitar Cita</a> </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_appointments.php">Mis Citas</a> </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Mesa de Partes</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="logout.php">Cerrar Sesión</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="dashboard-container">
            <div class="welcome-section">
                <h3>¡Bienvenido al Panel del Paciente, <?php echo $full_name; ?>!</h3>
                <p>Aquí puedes gestionar tus servicios con EsSalud Sicuani.</p>
            </div>

            <div class="row text-center">
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="bi bi-calendar-plus icon"></i>
                        <h5>Solicitar Cita</h5>
                        <p>Agenda tu próxima teleconsulta de manera rápida.</p>
                        <a href="request_appointment.php" class="btn btn-primary mt-auto">Agendar Cita</a> </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="bi bi-file-earmark-text icon"></i>
                        <h5>Mesa de Partes Virtual</h5>
                        <p>Envía documentos y sigue el estado de tus trámites.</p>
                        <a href="#" class="btn btn-primary mt-auto">Mis Trámites</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="bi bi-info-circle icon"></i>
                        <h5>Información y Campañas</h5>
                        <p>Mantente informado sobre salud preventiva y campañas.</p>
                        <a href="#" class="btn btn-primary mt-auto">Ver Noticias</a>
                    </div>
                </div>
            </div>

            </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>