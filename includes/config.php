<?php
/**
 * Archivo de Configuración de la Aplicación
 */
// --- CONFIGURACIÓN DE ZONA HORARIA ---
// Se establece la zona horaria para toda la aplicación para evitar problemas con las fechas.
date_default_timezone_set('America/Lima');
// --- Configuración de la Base de Datos ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'essaluds_sicuani_db');
define('DB_USER', 'essaluds_essaluds'); // Asumo que este es tu usuario real
define('DB_PASS', 'Ycr82061714622*'); // Asumo que aquí tienes tu contraseña real
define('DB_CHARSET', 'utf8mb4');

// --- Configuración del Sitio ---
define('APP_URL', 'https://essaludsicuani.com/essaludsicuani_web');
define('APP_NAME', 'EsSalud Sicuani - Gestión de Citas');

// --- Configuración de Correo (PHPMailer) ---

// --- CORRECCIÓN CLAVE ---
// Cambiamos el host a 'localhost'
define('SMTP_HOST', 'mail.essaludsicuani.com'); 

define('SMTP_USER', 'notificaciones@essaludsicuani.com');
define('SMTP_PASS', 'Ycr82061714622*');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');

// --- API DE CONSULTA DE DNI ---
define('DNI_API_TOKEN', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjoxNzYsImV4cCI6MTc1NDI3MDg3MH0.cNr5NrIPEF3AS3R7OazRxNyURupPIUCafK7w5droACs');
define('DNI_API_URL', 'https://miapi.cloud/v1/dni/');
// --- NUEVO: INFORMACIÓN DE SOPORTE ---
define('SUPPORT_EMAIL', 'soporte@essaludsicuani.com');
define('SUPPORT_WHATSAPP', '+51974362354');

// --- NUEVO: CONFIGURACIÓN DE LA API DE SMS ---
define('SMS_API_URL', 'http://144.126.228.11/index.php');
define('SMS_API_USER', 'conorige111');
define('SMS_API_TOKEN', 'd4c0b0b9578d112f93546b74ef8f9f6c');

?>