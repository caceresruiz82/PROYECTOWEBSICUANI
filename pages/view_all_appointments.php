<?php
/**
 * Página para ver un listado completo y filtrable de todas las citas del sistema.
 */

require_once '../includes/auth_guard.php';
require_once '../includes/db_connection.php';

// Guardia de Rol
if ($_SESSION['user_role'] !== 'Admision' && $_SESSION['user_role'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

// --- LÓGICA DE FILTRADO ---

// 1. Obtener datos para los menús desplegables de los filtros
try {
    $specialties = $pdo->query("SELECT specialty_id, specialty_name FROM specialties ORDER BY specialty_name")->fetchAll();
    $doctors = $pdo->query("SELECT user_id, full_name FROM users WHERE role_id = 5 AND status = 'activo' ORDER BY full_name")->fetchAll();
    $statuses = ['pendiente', 'confirmada', 'cancelada', 'completada', 'reprogramada', 'en_espera_recita', 'cancelada_institucional'];
} catch (PDOException $e) {
    die("Error al cargar datos para los filtros: " . $e->getMessage());
}

// 2. Definir las variables de filtro a partir de la URL ($_GET)
$f_specialty = $_GET['specialty'] ?? '';
$f_start_date = $_GET['start_date'] ?? '';
$f_end_date = $_GET['end_date'] ?? '';
$f_doctor = $_GET['doctor'] ?? '';
$f_doc_number = $_GET['doc_number'] ?? '';
$f_status = $_GET['status'] ?? '';

// 3. Construir la consulta SQL dinámicamente
$sql = "SELECT 
            a.appointment_id, a.appointment_date, a.appointment_time, a.status,
            p.full_name as patient_name, p.document_type, p.document_number,
            s.specialty_name,
            m.full_name as doctor_name
        FROM appointments a
        JOIN users p ON a.patient_id = p.user_id
        JOIN appointment_slots sl ON a.slot_id = sl.slot_id
        JOIN specialties s ON sl.specialty_id = s.specialty_id
        LEFT JOIN users m ON a.doctor_id_moderator = m.user_id
        WHERE 1=1";

$params = [];
if (!empty($f_specialty)) { $sql .= " AND s.specialty_id = :specialty"; $params[':specialty'] = $f_specialty; }
if (!empty($f_start_date)) { $sql .= " AND a.appointment_date >= :start_date"; $params[':start_date'] = $f_start_date; }
if (!empty($f_end_date)) { $sql .= " AND a.appointment_date <= :end_date"; $params[':end_date'] = $f_end_date; }
if (!empty($f_doctor)) { $sql .= " AND a.doctor_id_moderator = :doctor"; $params[':doctor'] = $f_doctor; }
if (!empty($f_doc_number)) { $sql .= " AND p.document_number LIKE :doc_number"; $params[':doc_number'] = "%" . $f_doc_number . "%"; }
if (!empty($f_status)) { $sql .= " AND a.status = :status"; $params[':status'] = $f_status; }
$sql .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";

// 4. Ejecutar la consulta
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
} catch (PDOException $e) {
    $appointments = [];
    $error_message = "Error al cargar el listado de citas: " . $e->getMessage();
}

$page_title = 'Listado General de Citas';
include_once '../templates/header.php';
?>

<h2><?php echo htmlspecialchars($page_title); ?></h2>
<p>Utilice los filtros para refinar su búsqueda y seleccione las citas para generar un reporte.</p>

<div class="filter-form">
    <form method="GET" action="view_all_appointments.php">
        <div class="form-grid">
            <div>
                <label for="specialty">Especialidad:</label>
                <select id="specialty" name="specialty">
                    <option value="">Todas</option>
                    <?php foreach ($specialties as $specialty): ?>
                        <option value="<?php echo $specialty['specialty_id']; ?>" <?php if ($f_specialty == $specialty['specialty_id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($specialty['specialty_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="start_date">Desde:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($f_start_date); ?>">
            </div>
            <div>
                <label for="end_date">Hasta:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($f_end_date); ?>">
            </div>
            <div>
                <label for="doctor">Médico Asignado:</label>
                <select id="doctor" name="doctor">
                    <option value="">Todos</option>
                     <?php foreach ($doctors as $doctor): ?>
                        <option value="<?php echo $doctor['user_id']; ?>" <?php if ($f_doctor == $doctor['user_id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($doctor['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="doc_number">Doc. del Paciente:</label>
                <input type="text" id="doc_number" name="doc_number" value="<?php echo htmlspecialchars($f_doc_number); ?>" placeholder="Buscar por número...">
            </div>
            <div>
                <label for="status">Estado:</label>
                <select id="status" name="status">
                    <option value="">Todos</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?php echo $status; ?>" <?php if ($f_status == $status) echo 'selected'; ?>>
                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn">Filtrar</button>
            <a href="view_all_appointments.php" class="btn btn-danger">Limpiar Filtros</a>
        </div>
    </form>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<form id="report-form" method="POST">
    <div class="table-actions" style="margin-bottom: 1rem; display:flex; flex-wrap:wrap; gap:1rem;">
        <button type="button" id="pdf-button" class="btn">Generar PDF</button>
        <button type="button" id="print-button" class="btn">Imprimir Selección</button>
        <button type="button" id="email-button" class="btn">Enviar por Email</button>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all"></th>
                    <th>Paciente</th>
                    <th>Documento</th>
                    <th>Especialidad</th>
                    <th>Fecha y Hora</th>
                    <th>Médico Asignado</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                    <tr><td colspan="7" style="text-align:center;">No se encontraron citas con los filtros aplicados.</td></tr>
                <?php else: ?>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td><input type="checkbox" name="appointment_ids[]" value="<?php echo $appointment['appointment_id']; ?>" class="appointment-checkbox"></td>
                            <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['document_type'] . ': ' . $appointment['document_number']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['specialty_name']); ?></td>
                            <td><?php echo htmlspecialchars(date("d/m/Y h:i A", strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']))); ?></td>
                            <td><?php echo htmlspecialchars($appointment['doctor_name'] ?? 'No asignado'); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo htmlspecialchars($appointment['status']); ?>">
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $appointment['status']))); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</form>

<div id="email-modal" class="modal">
    <div class="modal-content">
        <span class="modal-close-button">&times;</span>
        <h3>Enviar Reporte por Correo</h3>
        <p>Ingrese la dirección de correo del destinatario.</p>
        <div id="email-modal-form">
            <label for="recipient_email">Correo Electrónico:</label>
            <input type="email" id="recipient_email" name="recipient_email" placeholder="ejemplo@dominio.com">
            <button id="send-email-button" class="btn" style="margin-top: 1rem;">Enviar</button>
        </div>
        <div id="email-modal-message" style="margin-top: 1rem;"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Lógica para seleccionar/deseleccionar todos
    const selectAllCheckbox = document.getElementById('select-all');
    const appointmentCheckboxes = document.querySelectorAll('.appointment-checkbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            appointmentCheckboxes.forEach(checkbox => { checkbox.checked = this.checked; });
        });
    }

    // Lógica para los botones de acción
    const reportForm = document.getElementById('report-form');
    const pdfButton = document.getElementById('pdf-button');
    const printButton = document.getElementById('print-button');
    const emailButton = document.getElementById('email-button');

    // Lógica para la ventana modal
    const emailModal = document.getElementById('email-modal');
    const closeModalButton = document.querySelector('.modal-close-button');
    const sendEmailButton = document.getElementById('send-email-button');
    const recipientEmailInput = document.getElementById('recipient_email');
    const emailModalMessage = document.getElementById('email-modal-message');

    if (reportForm) {
        pdfButton.addEventListener('click', function() {
            reportForm.action = 'generate_report.php';
            reportForm.target = '_blank'; // Abrir en nueva pestaña
            reportForm.submit();
        });

        printButton.addEventListener('click', function() {
            reportForm.action = 'print_view.php';
            reportForm.target = '_blank'; // Abrir en nueva pestaña
            reportForm.submit();
        });

        emailButton.addEventListener('click', function() {
            const selected = document.querySelectorAll('.appointment-checkbox:checked');
            if (selected.length === 0) {
                alert('Por favor, seleccione al menos una cita para enviar.');
                return;
            }
            emailModal.style.display = 'block';
        });
    }
    
    // Lógica para cerrar la modal
    if (closeModalButton) {
        closeModalButton.addEventListener('click', function() {
            emailModal.style.display = 'none';
            emailModalMessage.innerHTML = '';
            recipientEmailInput.value = '';
        });
    }
    window.addEventListener('click', function(event) {
        if (event.target == emailModal) {
            emailModal.style.display = 'none';
        }
    });

    // Lógica para el botón "Enviar" de la modal
    if (sendEmailButton) {
        sendEmailButton.addEventListener('click', function() {
            const recipientEmail = recipientEmailInput.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (!emailRegex.test(recipientEmail)) {
                emailModalMessage.innerHTML = '<span style="color:red;">Por favor, ingrese un correo válido.</span>';
                return;
            }

            const formData = new FormData(reportForm);
            formData.append('recipient_email', recipientEmail);
            
            emailModalMessage.innerHTML = '<span>Enviando reporte, por favor espere...</span>';
            sendEmailButton.disabled = true;

            fetch('send_report_email.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    emailModalMessage.innerHTML = `<span style="color:green;">${data.message}</span>`;
                    setTimeout(() => {
                        closeModalButton.click();
                        sendEmailButton.disabled = false;
                    }, 2500);
                } else {
                    throw new Error(data.message || 'Error desconocido.');
                }
            })
            .catch(error => {
                console.error('Error al enviar el correo:', error);
                emailModalMessage.innerHTML = `<span style="color:red;">Error: ${error.message}</span>`;
                sendEmailButton.disabled = false;
            });
        });
    }
});
</script>

<style>
    .filter-form { background-color: #f9f9f9; padding: 1.5rem; border-radius: var(--border-radius); margin-bottom: 2rem; }
    .filter-form .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; }
    .filter-actions { margin-top: 1rem; display: flex; gap: 1rem; }
    .modal {
        display: none; position: fixed; z-index: 1001; left: 0; top: 0;
        width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);
    }
    .modal-content {
        background-color: #fefefe; margin: 15% auto; padding: 20px;
        border: 1px solid #888; width: 80%; max-width: 500px;
        border-radius: var(--border-radius); position: relative;
    }
    .modal-close-button {
        color: #aaa; float: right; font-size: 28px; font-weight: bold;
    }
    .modal-close-button:hover, .modal-close-button:focus {
        color: black; text-decoration: none; cursor: pointer;
    }
</style>

<?php
include_once '../templates/footer.php';
?>