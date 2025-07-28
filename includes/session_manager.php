<?php
/**
 * Gestor de Sesiones Centralizado (Versión Robusta).
 *
 * Este script verifica primero si una sesión ya está activa antes de intentar
 * configurar o iniciar una nueva. Esto lo hace compatible con servidores
 * que tienen 'session.auto_start' habilitado.
 */

// Solo intentar configurar e iniciar una sesión SI NO HAY una ya activa.
if (session_status() === PHP_SESSION_NONE) {
    
    // Establecer una ruta de guardado personalizada y segura.
    $session_path = realpath(dirname(__FILE__) . '/sessions');
    
    // Esta condición es importante para evitar errores si la carpeta no existiera.
    if ($session_path) {
        // Esta línea ahora solo se ejecutará si la sesión no ha sido iniciada por el servidor.
        ini_set('session.save_path', $session_path);
    }

    // Iniciar la sesión.
    session_start();
}