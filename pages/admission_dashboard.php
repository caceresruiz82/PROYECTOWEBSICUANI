<?php
/**
 * admission_dashboard.php
 *
 * Panel para el personal de Admisión del sistema web de EsSalud Sicuani.
 * Esta página solo es accesible para usuarios autenticados con el rol de Admision.
 * Servirá como punto central para sus tareas relacionadas con citas, slots y registro.
 *
 * @package EssaludSicuaniWeb
 * @subpackage Pages
 * @author Yudelvis Caceres
 * @version 1.0.1 // Actualización para incluir enlace a manage_patient_appointments.php
 * @date 2025-07-27
 */

// Iniciar la sesión PHP al principio de la página
session_start();

// Incluir la conexión a la base de datos
require_once '../includes/db_connection.php';

// --- Protección de la Página ---
// 1. Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Verificar si el usuario tiene el rol de Admision (role_id = 4)
if ($_SESSION['role_id'] != 4) { // Si el ID del rol no es 4 (Admision)
    // Redirigir a una página de "Acceso Denegado" o al dashboard general
    header("Location: dashboard.php"); // O a otro dashboard si tienen permiso
    exit();
}

// Si llega hasta aquí, el usuario es un Admisionista autenticado.
$full_name = htmlspecialchars($_SESSION['full_name']); // Obtener el nombre del usuario de la sesión

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Admisión - EsSalud Sicuani</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .navbar {
            background-color: #fd7e14; /* Color distintivo para Admisión (naranja de Bootstrap) */
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
            background-color: #fff3e6; /* Tono más claro del color admisión */
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            text-align: center;
            border-left: 5px solid #fd7e14;
        }
        .welcome-section h3 {
            color: #fd7e14;
        }
        .feature-card {
            background-color: #fff;
            border: 1px solid #fd7e14;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .feature-card h5 {
            margin-top: 15px;
            font-weight: bold;
            color: #fd7e14;
        }
        .feature-card .icon {
            font-size: 3rem;
            color: #fd7e14;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="admission_dashboard.php">
                <img src="../assets/images/logo_essalud.png" alt="Logo EsSalud" width="40" class="d-inline-block align-text-top me-2">
                Panel Admisión
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="admission_dashboard.php">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="program_slots.php">Programar Turnos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_slots.php">Supervisar Turnos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_patient_appointments.php">Gestión de Citas Pacientes</a> </li>
                     <li class="nav-item">
                        <a class="nav-link" href="#">Lista de Espera Recitas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Cerrar Sesión</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="card">
            <div class="card-header bg-warning text-white">
                <h4 class="mb-0">Panel de Admisión</h4>
            </div>
            <div class="card-body">
                <div class="welcome-section">
                    <h3>¡Bienvenido al Panel de Admisión, <?php echo $full_name; ?>!</h3>
                    <p>Aquí puedes gestionar la programación de turnos y supervisar las citas.</p>
                </div>

                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="feature-card">
                            <i class="bi bi-calendar-plus icon"></i>
                            <h5>Programar Turnos</h5>
                            <p>Define y envía a aprobación los bloques de disponibilidad de teleconsultas.</p>
                            <a href="program_slots.php" class="btn btn-warning mt-auto">Programar</a>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="feature-card">
                            <i class="bi bi-calendar-check icon"></i>
                            <h5>Supervisar Turnos</h5>
                            <p>Visualiza el estado de los turnos programados.</p>
                            <a href="manage_slots.php" class="btn btn-warning mt-auto">Supervisar</a>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="feature-card">
                            <i class="bi bi-person-bounding-box icon"></i>
                            <h5>Gestión de Citas Pacientes</h5>
                            <p>Confirma, reprograma o cancela citas de pacientes.</p>
                            <a href="manage_patient_appointments.php" class="btn btn-warning mt-auto">Gestionar Citas</a> </div>
                    </div>
                    <div class="col-md-4">
                        <div class="feature-card">
                            <i class="bi bi-list-task icon"></i>
                            <h5>Lista de Espera Recitas</h5>
                            <p>Administra las solicitudes de recitas en lista de espera.</p>
                            <a href="#" class="btn btn-warning mt-auto">Ver Lista</a>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="feature-card">
                            <i class="bi bi-file-earmark-bar-graph icon"></i>
                            <h5>Reportes</h5>
                            <p>Accede a reportes específicos de Admisión.</p>
                            <a href="#" class="btn btn-warning mt-auto">Ver Reportes</a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>