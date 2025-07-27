<?php
// templates/header.php
// Este archivo contendrá la cabecera HTML común a todas las páginas,
// incluyendo la barra de navegación y el recuadro de fecha/hora en tiempo real.
// NO cierra </body> ni </html>.

// Asegúrate de que session_start() se haya llamado al inicio de cada página que incluye este header.

// Si esta siendo incluido por una pagina que no define $is_admin o $is_admision, se definen aqui para evitar errores
if (!isset($is_admin)) { $is_admin = false; }
if (!isset($is_admision)) { $is_admision = false; }
if (!isset($is_logged_in)) { $is_logged_in = false; } // Asegurar que $is_logged_in esté definido
if (!isset($user_full_name)) { $user_full_name = ''; }
if (!isset($user_role_id)) { $user_role_id = $_SESSION['role_id'] ?? 0; } // Usar el de la sesión

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'EsSalud Sicuani'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .navbar {
            /* Colores dinámicos basados en el rol, si es necesario, o un color base */
            background-color: <?php 
                if ($user_role_id == 1) echo '#dc3545'; // Administrador (rojo)
                else if ($user_role_id == 4) echo '#fd7e14'; // Admision (naranja)
                else if ($user_role_id == 6) echo '#007bff'; // Paciente (azul)
                else echo '#007bff'; // Por defecto o no logueado
            ?>;
        }
        .navbar .nav-link {
            color: #ffffff !important;
        }
        .navbar .nav-link:hover {
            color: #e2e6ea !important;
        }
        .real-time-box {
            color: white;
            padding: .5rem 1rem;
            margin-left: auto; /* Empuja el div a la derecha */
            display: flex;
            align-items: center;
            font-size: 0.9em;
            white-space: nowrap; /* Evita que el texto se rompa */
        }
        /* --- Estilos personalizados para las tarjetas --- */
        .feature-card {
            border: 1px solid #dee2e6;
            border-radius: 0.5rem; /* Bordes romos */
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1); /* Sombra más visible para "flotar" */
            background-color: #fff;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            height: 100%; /* Asegura que todas las tarjetas en una fila tengan la misma altura */
            display: flex;
            flex-direction: column;
            justify-content: center; /* Centrado vertical */
            align-items: center; /* Centrado horizontal del contenido */
            text-align: center;
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; /* Animación al pasar el ratón */
        }
        .feature-card:hover {
            transform: translateY(-5px); /* Efecto de "levantar" */
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.2); /* Sombra más pronunciada al pasar el ratón */
        }
        .feature-card .icon {
            font-size: 2.5rem; /* Iconos un poco más grandes */
            margin-bottom: 1rem;
            color: <?php
                if ($user_role_id == 1) echo '#dc3545'; // Administrador (rojo)
                else if ($user_role_id == 4) echo '#fd7e14'; // Admision (naranja)
                else if ($user_role_id == 6) echo '#007bff'; // Paciente (azul)
                else echo '#007bff'; // Por defecto
            ?>;
        }
        .feature-card h5 {
            margin-bottom: 0.75rem;
            font-weight: bold; /* Asegurar negrita */
            color: #343a40; /* Color de texto más oscuro para títulos */
        }
        .feature-card p {
            font-size: 0.9rem;
            color: #6c757d;
            flex-grow: 1; /* Permite que el párrafo ocupe el espacio disponible y empuje el botón hacia abajo */
        }
        .feature-card .btn {
            margin-top: 1rem;
            width: 80%; /* Botones un poco más anchos */
        }

        /* --- Estilos para Tablas Responsivas Mejoradas (vista móvil) --- */
        @media (max-width: 768px) {
            .table-responsive.table-custom-mobile table {
                border: 0;
            }
            .table-responsive.table-custom-mobile thead {
                display: none; /* Ocultar el encabezado de la tabla en móviles */
            }
            .table-responsive.table-custom-mobile tr {
                display: block; /* Cada fila se comporta como un bloque */
                margin-bottom: 0.625em;
                border: 1px solid #dee2e6;
                border-radius: 0.5rem; /* Bordes romos para cada "fila-tarjeta" */
                background-color: #fff;
                box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); /* Sombra para cada "fila-tarjeta" */
                padding: 1rem;
            }
            .table-responsive.table-custom-mobile td {
                display: block; /* Cada celda se comporta como un bloque */
                text-align: right;
                padding-left: 50% !important; /* Espacio para la etiqueta */
                position: relative;
                border-bottom: 1px dashed #dee2e6; /* Separador sutil entre campos */
                padding-top: 0.5rem !important;
                padding-bottom: 0.5rem !important;
            }
            .table-responsive.table-custom-mobile td:last-child {
                border-bottom: 0; /* Sin borde en la última celda */
                text-align: left; /* Alineación del botón */
                padding-left: 0 !important;
                display: flex; /* Para centrar los botones si hay varios */
                justify-content: center;
                align-items: center;
                padding-top: 1rem !important;
            }
            .table-responsive.table-custom-mobile td::before {
                content: attr(data-label); /* Usar el atributo data-label como etiqueta */
                position: absolute;
                left: 0;
                width: 50%;
                padding-left: 15px;
                font-weight: bold;
                text-align: left;
                color: #495057; /* Color más oscuro para la etiqueta */
            }
            /* Asegurar que los botones en móviles se vean bien */
            .table-responsive.table-custom-mobile td .btn {
                width: auto; /* Ancho automático para botones de acción */
                margin: 0 5px; /* Espacio entre botones */
            }
            /* Alineación de formularios de filtro en móviles */
            .filter-form .row.g-3 > div {
                margin-bottom: 1rem; /* Más espacio entre campos en móviles */
            }
            .filter-form .col-12.text-end {
                text-align: center !important; /* Centrar el botón de filtrar en móviles */
            }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php 
                if ($user_role_id == 1) echo 'admin_dashboard.php';
                else if ($user_role_id == 4) echo 'admission_dashboard.php';
                else if ($user_role_id == 6) echo 'patient_dashboard.php';
                else echo 'login.php'; // Redirige al login si no está logueado o rol desconocido
            ?>">
                <img src="../assets/images/logo_essalud.png" alt="Logo EsSalud" width="40" class="d-inline-block align-text-top me-2">
                <?php 
                    if ($user_role_id == 1) echo 'Panel Admin';
                    else if ($user_role_id == 4) echo 'Panel Admisión';
                    else if ($user_role_id == 6) echo 'Panel Paciente';
                    else echo 'EsSalud Sicuani';
                ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php 
                            if ($user_role_id == 1) echo 'admin_dashboard.php';
                            else if ($user_role_id == 4) echo 'admission_dashboard.php';
                            else if ($user_role_id == 6) echo 'patient_dashboard.php';
                            else echo 'login.php';
                        ?>">Inicio</a>
                    </li>
                    <?php if ($is_admin): // Menú para Administrador ?>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">Gestión de Usuarios</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="program_slots.php">Programar Turnos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_slots.php">Gestión de Turnos Disponibles</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="manage_patient_appointments.php">Gestión de Citas Pacientes</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">Configuración</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">Reportes</a>
                        </li>
                    <?php elseif ($is_admision): // Menú para Admisión ?>
                        <li class="nav-item">
                            <a class="nav-link" href="program_slots.php">Programar Turnos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_slots.php">Supervisar Turnos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_patient_appointments.php">Gestión de Citas Pacientes</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">Lista de Espera Recitas</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">Reportes</a>
                        </li>
                    <?php elseif ($user_role_id == 6): // Menú para Paciente ?>
                        <li class="nav-item">
                            <a class="nav-link" href="request_appointment.php">Solicitar Cita</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my_appointments.php">Mis Citas</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">Mesa de Partes</a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <div class="real-time-box">
                    <span id="realTimeDateTime"></span>
                </div>

                <ul class="navbar-nav ms-auto">
                    <?php if ($is_logged_in): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Hola, <?php echo $user_full_name; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="#">Mi Perfil</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Cerrar Sesión</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div style="height: 60px;"></div>