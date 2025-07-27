<?php
/**
 * delete_slot.php
 *
 * Script para eliminar un bloque de disponibilidad (appointment_slot) de la base de datos.
 * Esta página solo es accesible y ejecutable por usuarios con rol de Administrador.
 * Recibe el ID del slot a eliminar y lo procesa.
 *
 * @package EssaludSicuaniWeb
 * @subpackage Pages
 * @author Yudelvis Caceres
 * @version 1.0.0
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

// 2. Verificar si el usuario tiene el rol de Administrador (role_id = 1)
if ($_SESSION['role_id'] != 1) {
    // Si no es administrador, redirigir a un dashboard genérico o de acceso denegado
    header("Location: dashboard.php");
    exit();
}

// Verificar si se recibió un ID de slot para eliminar
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $slot_id_to_delete = (int)$_GET['id'];

    // Iniciar transacción para asegurar atomicidad
    $conn->begin_transaction();

    try {
        // Opcional: Verificar si el slot tiene citas asociadas con status diferente de 'cancelada'
        // Si hay citas confirmadas o pendientes para este slot, quizás no se deba eliminar directamente.
        // Por ahora, eliminaremos el slot y las citas asociadas por CASCADE en FOREIGN KEY patient_id.
        // Pero para citas específicas de este slot (appointments.slot_id), si DELETE RESTRICT en FK no se configuró,
        // esto podría causar un error si hay citas activas.
        // Si la FOREIGN KEY slot_id en appointments es ON DELETE RESTRICT, este DELETE fallará si hay citas.
        // En nuestro caso, la FK slot_id es ON DELETE RESTRICT. Así que debemos manejarlo.

        $stmt_check_appointments = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE slot_id = ? AND status NOT IN ('cancelada', 'cancelada_institucional', 'completada')");
        $stmt_check_appointments->bind_param("i", $slot_id_to_delete);
        $stmt_check_appointments->execute();
        $stmt_check_appointments->bind_result($active_appointments_count);
        $stmt_check_appointments->fetch();
        $stmt_check_appointments->close();

        if ($active_appointments_count > 0) {
            throw new Exception("No se puede eliminar el turno. Hay citas activas asociadas a este turno. Cancele o reubique las citas primero.");
        }

        // Preparar la consulta SQL para eliminar el slot
        $stmt_delete = $conn->prepare("DELETE FROM appointment_slots WHERE slot_id = ?");
        $stmt_delete->bind_param("i", $slot_id_to_delete);

        if ($stmt_delete->execute()) {
            if ($stmt_delete->affected_rows > 0) {
                $conn->commit();
                $_SESSION['slot_message'] = '<div class="alert alert-success">Turno eliminado exitosamente.</div>';
            } else {
                throw new Exception("No se encontró el turno con el ID especificado o ya fue eliminado.");
            }
        } else {
            throw new Exception("Error al eliminar el turno: " . $stmt_delete->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['slot_message'] = '<div class="alert alert-danger">Error al eliminar el turno: ' . $e->getMessage() . '</div>';
    } finally {
        if (isset($stmt_delete) && $stmt_delete) $stmt_delete->close();
    }
} else {
    $_SESSION['slot_message'] = '<div class="alert alert-danger">ID de turno no especificado o inválido.</div>';
}

// Redirigir de vuelta a la página de gestión de slots
header("Location: manage_slots.php");
exit();

?>