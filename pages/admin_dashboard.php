<?php
/**
 * admin_dashboard.php
 *
 * Panel de administración para el sistema web de EsSalud Sicuani.
 * Esta página solo es accesible para usuarios autentificados con el rol de Administrador.
 * Sirve como punto central para la gestión de usuarios, configuración del sistema,
 * y otras tareas administrativas.
 *
 * @package EssaludSicuaniWeb
 * @subpackage Pages
 * @author Yudelvis Caceres
 * @version 1.0.7 // Ajuste final de integración de templates, sin HTML/Body/Scripts directos. ¡Limpio!
 * @date 2025-07-27
 */

session_start();

require_once '../includes/db_connection.php';

// --- Protección de la Página ---
// 1. Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Verificar si el usuario tiene el rol de Administrador (1)
$is_admin = ($_SESSION['role_id'] == 1); // Definir $is_admin para el header.php
$is_admision = false; // Definir $is_admision para el header.php (no es Admision)
if (!$is_admin) { // Si el ID del rol no es 1 (Administrador)
    header("Location: dashboard.php"); // Redirigir si no tiene el rol adecuado
    exit();
}

// Si llega hasta aquí, el usuario es un administrador autenticado.
$user_full_name = htmlspecialchars($_SESSION['full_name'] ?? ''); // Definir $user_full_name para el header.php
$user_role_id = $_SESSION['role_id'] ?? 0; // Definir user_role_id para header.php
$is_logged_in = true; // Definir para header.php

$page_title = 'Panel de Administración - EsSalud Sicuani'; // Definir para el header.php

// --- INCLUIR LA CABECERA COMÚN ---
include '../templates/header.php';
?>

            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h4 class="mb-0">Panel de Administración</h4>
                </div>
                <div class="card-body">
                    <div class="welcome-section">
                        <h3>¡Bienvenido al Panel de Administración, <?php echo $user_full_name; ?>!</h3>
                        <p>Aquí puedes gestionar y configurar el sistema de EsSalud Sicuani.</p>
                    </div>

                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="feature-card">
                                <i class="bi bi-people-fill icon"></i>
                                <h5>Gestión de Usuarios</h5>
                                <p>Administra pacientes, médicos y personal administrativo.</p>
                                <a href="manage_users.php" class="btn btn-danger mt-auto">Ir a Gestión</a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="feature-card">
                                <i class="bi bi-calendar-plus icon"></i>
                                <h5>Programar Turnos</h5>
                                <p>Define la disponibilidad de teleconsultas para especialidades.</p>
                                <a href="program_slots.php" class="btn btn-danger mt-auto">Programar Turnos</a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="feature-card">
                                <i class="bi bi-calendar-event icon"></i>
                                <h5>Gestión de Turnos Disponibles</h5>
                                <p>Supervisa y administra los bloques de turnos programados.</p>
                                <a href="manage_slots.php" class="btn btn-danger mt-auto">Ver Turnos</a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="feature-card">
                                <i class="bi bi-person-lines-fill icon"></i>
                                <h5>Gestión de Citas Pacientes</h5>
                                <p>Confirma, cancela y reprograma citas específicas de pacientes.</p>
                                <a href="manage_patient_appointments.php" class="btn btn-danger mt-auto">Gestionar Citas</a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="feature-card">
                                <i class="bi bi-gear-fill icon"></i>
                                <h5>Configuración del Sistema</h5>
                                <p>Ajusta parámetros generales de la plataforma.</p>
                                <a href="#" class="btn btn-danger mt-auto">Configurar</a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="feature-card">
                                <i class="bi bi-bar-chart-fill icon"></i>
                                <h5>Ver Reportes</h5>
                                <p>Accede a estadísticas y reportes de uso.</p>
                                <a href="#" class="btn btn-danger mt-auto">Ver Reportes</a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

<?php
// --- INCLUIR EL PIE DE PÁGINA COMÚN ---
include '../templates/footer.php';
?>