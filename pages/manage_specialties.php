<?php
/**
 * Página para la gestión (CRUD) de Especialidades.
 */

require_once '../includes/auth_guard.php';
require_once '../includes/db_connection.php';

// Guardia de Rol: Solo Admision y Administradores pueden acceder.
if ($_SESSION['user_role'] !== 'Admision' && $_SESSION['user_role'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

$message = $_GET['message'] ?? '';
$message_type = $_GET['type'] ?? '';
$edit_mode = false;
$specialty_to_edit = ['specialty_id' => '', 'specialty_name' => '', 'description' => ''];

// --- LÓGICA DE PROCESAMIENTO DEL FORMULARIO (AÑADIR/EDITAR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $specialty_name = trim(filter_input(INPUT_POST, 'specialty_name', FILTER_SANITIZE_STRING));
    $description = trim(filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING));
    $specialty_id = filter_input(INPUT_POST, 'specialty_id', FILTER_VALIDATE_INT);

    if (empty($specialty_name)) {
        $message = "El nombre de la especialidad no puede estar vacío.";
        $message_type = 'error';
    } else {
        try {
            if ($specialty_id) { // Modo Edición
                $sql = "UPDATE specialties SET specialty_name = :name, description = :desc WHERE specialty_id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':name' => $specialty_name, ':desc' => $description, ':id' => $specialty_id]);
                $message = "Especialidad actualizada con éxito.";
            } else { // Modo Creación
                $sql = "INSERT INTO specialties (specialty_name, description) VALUES (:name, :desc)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':name' => $specialty_name, ':desc' => $description]);
                $message = "Nueva especialidad agregada con éxito.";
            }
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = "Error en la base de datos. Es posible que el nombre de la especialidad ya exista.";
            $message_type = 'error';
        }
    }
}

// --- LÓGICA PARA CARGAR DATOS PARA EDICIÓN O PARA ELIMINAR ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        if ($_GET['action'] === 'edit') {
            $stmt = $pdo->prepare("SELECT * FROM specialties WHERE specialty_id = :id");
            $stmt->execute([':id' => $id]);
            $specialty_to_edit = $stmt->fetch();
            if ($specialty_to_edit) $edit_mode = true;
        } elseif ($_GET['action'] === 'delete') {
            try {
                $stmt = $pdo->prepare("DELETE FROM specialties WHERE specialty_id = :id");
                $stmt->execute([':id' => $id]);
                $message = "Especialidad eliminada con éxito.";
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = "No se puede eliminar. La especialidad está en uso en alguna programación o cita.";
                $message_type = 'error';
            }
        }
    }
}

// Obtener todas las especialidades para mostrarlas en la tabla
$specialties = $pdo->query("SELECT * FROM specialties ORDER BY specialty_name")->fetchAll();

$page_title = 'Gestionar Especialidades';
include_once '../templates/header.php';
?>

<h2><?php echo htmlspecialchars($page_title); ?></h2>
<p>Añada, edite y elimine las especialidades médicas disponibles para teleconsultas.</p>

<?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="form-container" style="margin-bottom: 2rem; padding: 1.5rem; background-color: #f9f9f9; border-radius: 8px;">
    <h3><?php echo $edit_mode ? 'Editando Especialidad' : 'Añadir Nueva Especialidad'; ?></h3>
    <form action="manage_specialties.php" method="POST">
        <input type="hidden" name="specialty_id" value="<?php echo htmlspecialchars($specialty_to_edit['specialty_id']); ?>">
        <div>
            <label for="specialty_name">Nombre de la Especialidad:</label>
            <input type="text" id="specialty_name" name="specialty_name" value="<?php echo htmlspecialchars($specialty_to_edit['specialty_name']); ?>" required>
        </div>
        <div>
            <label for="description">Descripción (opcional):</label>
            <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($specialty_to_edit['description']); ?></textarea>
        </div>
        <div>
            <button type="submit" class="btn"><?php echo $edit_mode ? 'Actualizar' : 'Guardar'; ?></button>
            <?php if ($edit_mode): ?>
                <a href="manage_specialties.php" class="btn btn-danger">Cancelar Edición</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<h3>Lista de Especialidades</h3>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($specialties)): ?>
                <tr><td colspan="3">No hay especialidades registradas.</td></tr>
            <?php else: ?>
                <?php foreach ($specialties as $specialty): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($specialty['specialty_name']); ?></td>
                        <td><?php echo htmlspecialchars($specialty['description']); ?></td>
                        <td>
                            <a href="manage_specialties.php?action=edit&id=<?php echo $specialty['specialty_id']; ?>" class="btn">Editar</a>
                            <a href="manage_specialties.php?action=delete&id=<?php echo $specialty['specialty_id']; ?>" class="btn btn-danger" onclick="return confirm('¿Está seguro? Esta acción no se puede deshacer.');">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
include_once '../templates/footer.php';
?>