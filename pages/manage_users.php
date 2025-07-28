<?php
/**
 * Página de Gestión de Usuarios para Administradores.
 * Permite ver, activar y cambiar roles de todos los usuarios del sistema.
 */

require_once '../includes/auth_guard.php';
require_once '../includes/db_connection.php';

// Guardia de Rol: Solo los administradores pueden acceder a esta página.
if ($_SESSION['user_role'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

$message = $_GET['message'] ?? '';
$message_type = $_GET['type'] ?? '';

try {
    // Obtener todos los usuarios y sus roles
    $sql_users = "SELECT u.user_id, u.full_name, u.email, u.status, r.role_name 
                  FROM users u 
                  JOIN roles r ON u.role_id = r.role_id 
                  ORDER BY u.registration_date DESC";
    $stmt_users = $pdo->query($sql_users);
    $users = $stmt_users->fetchAll();

    // Obtener todos los roles disponibles para el dropdown de cambio de rol
    $sql_roles = "SELECT role_id, role_name FROM roles ORDER BY role_name";
    $stmt_roles = $pdo->query($sql_roles);
    $roles = $stmt_roles->fetchAll();

} catch (PDOException $e) {
    $users = [];
    $roles = [];
    $message = "Error fatal al cargar los datos de usuarios. Contacte a soporte.";
    $message_type = 'error';
    // error_log($e->getMessage());
}

$page_title = 'Gestionar Usuarios';
include_once '../templates/header.php';
?>

<h2><?php echo htmlspecialchars($page_title); ?></h2>
<p>Desde esta página puede activar nuevos usuarios y administrar sus roles en el sistema.</p>

<?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
        <?php echo htmlspecialchars(urldecode($message)); ?>
    </div>
<?php endif; ?>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Nombre Completo</th>
                <th>Email</th>
                <th>Rol Actual</th>
                <th>Estado Actual</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="5">No hay usuarios para mostrar.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo htmlspecialchars($user['status']); ?>">
                                <?php echo htmlspecialchars(ucfirst($user['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <?php // No se puede modificar al propio administrador desde aquí para evitar auto-bloqueo
                            if ($user['user_id'] == $_SESSION['user_id']): ?>
                                N/A (Usted)
                            <?php else: ?>
                                <form action="update_user_status_role.php" method="POST" class="form-inline">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    
                                    <select name="new_status" aria-label="Nuevo estado">
                                        <option value="activo" <?php if ($user['status'] == 'activo') echo 'selected'; ?>>Activo</option>
                                        <option value="inactivo" <?php if ($user['status'] == 'inactivo') echo 'selected'; ?>>Inactivo</option>
                                        <option value="pendiente" <?php if ($user['status'] == 'pendiente') echo 'selected'; ?>>Pendiente</option>
                                    </select>
                                    
                                    <select name="new_role_id" aria-label="Nuevo rol">
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role['role_id']; ?>" <?php if ($role['role_name'] == $user['role_name']) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($role['role_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <button type="submit" class="btn">Actualizar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
/* Estilos para la tabla y los badges de estado (reutilizados) */
.table-container { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
th, td { padding: 12px; border: 1px solid #ddd; text-align: left; vertical-align: middle; }
th { background-color: #f2f2f2; }
tr:nth-child(even) { background-color: #f9f9f9; }
.status-badge { padding: 5px 10px; border-radius: 15px; color: white; font-size: 0.85rem; font-weight: 600; }
.status-activo { background-color: #28a745; }
.status-inactivo { background-color: #6c757d; }
.status-pendiente { background-color: #ffc107; color: #333; }
.form-inline { display: flex; gap: 10px; align-items: center; }
.form-inline select, .form-inline button { padding: 8px; font-size: 0.9rem; }
.form-inline button { flex-shrink: 0; }
</style>

<?php
include_once '../templates/footer.php';
?>