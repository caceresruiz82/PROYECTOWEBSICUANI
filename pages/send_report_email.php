<?php
/**
 * Script para generar un reporte en PDF de las citas seleccionadas,
 * guardarlo temporalmente y enviarlo como archivo adjunto por email.
 */

// Establecer la cabecera de la respuesta como JSON para comunicarnos con el JavaScript
header('Content-Type: application/json');

require_once '../includes/auth_guard.php';
require_once '../includes/db_connection.php';
require_once '../includes/fpdf/fpdf.php';
require_once '../includes/mail_sender.php';

$response = ['success' => false, 'message' => 'Error desconocido.'];

// 1. VERIFICACIONES INICIALES
if ($_SESSION['user_role'] !== 'Admision' && $_SESSION['user_role'] !== 'Administrador') {
    $response['message'] = 'Acceso no permitido.';
    echo json_encode($response);
    exit;
}

if (!isset($_POST['appointment_ids']) || !is_array($_POST['appointment_ids']) || empty($_POST['appointment_ids'])) {
    $response['message'] = 'No se seleccionó ninguna cita para enviar.';
    echo json_encode($response);
    exit;
}

$recipient_email = filter_input(INPUT_POST, 'recipient_email', FILTER_VALIDATE_EMAIL);
if (!$recipient_email) {
    $response['message'] = 'La dirección de correo del destinatario no es válida.';
    echo json_encode($response);
    exit;
}

// 2. OBTENER DATOS DE LA BASE DE DATOS
try {
    $appointment_ids = array_map('intval', $_POST['appointment_ids']);
    $placeholders = implode(',', array_fill(0, count($appointment_ids), '?'));
    
    $sql = "SELECT 
                a.appointment_date, a.appointment_time, a.status,
                p.full_name as patient_name,
                s.specialty_name,
                m.full_name as doctor_name
            FROM appointments a
            JOIN users p ON a.patient_id = p.user_id
            JOIN appointment_slots sl ON a.slot_id = sl.slot_id
            JOIN specialties s ON sl.specialty_id = s.specialty_id
            LEFT JOIN users m ON a.doctor_id_moderator = m.user_id
            WHERE a.appointment_id IN ($placeholders)
            ORDER BY a.appointment_date ASC, a.appointment_time ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($appointment_ids);
    $appointments = $stmt->fetchAll();

    if (empty($appointments)) {
        throw new Exception("No se encontraron datos para las citas seleccionadas.");
    }

} catch (Exception $e) {
    $response['message'] = 'Error en la base de datos al obtener los datos del reporte.';
    echo json_encode($response);
    exit;
}

// 3. CLASE PARA GENERAR EL PDF
class PDF_Report extends FPDF
{
    function Header() {
        $this->SetFont('Arial','B',14);
        $this->Cell(0,10,utf8_decode('Reporte de Citas Programadas'),0,1,'C');
        $this->SetFont('Arial','',10);
        $this->Cell(0,10,utf8_decode('Generado el: ') . date('d/m/Y H:i:s'),0,1,'C');
        $this->Ln(10);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,utf8_decode('Página ') . $this->PageNo() . '/{nb}',0,0,'C');
    }
    function CreateTable($header, $data) {
        $this->SetFillColor(200,220,255);
        $this->SetFont('','B', 9);
        $w = [60, 50, 30, 80, 30];
        for($i=0;$i<count($header);$i++)
            $this->Cell($w[$i],7,utf8_decode($header[$i]),1,0,'C',true);
        $this->Ln();
        $this->SetFont('','', 8);
        $fill = false;
        foreach($data as $row) {
            $this->Cell($w[0],6,utf8_decode($row['patient_name']),'LR',0,'L',$fill);
            $this->Cell($w[1],6,utf8_decode($row['specialty_name']),'LR',0,'L',$fill);
            $this->Cell($w[2],6,date("d/m/Y", strtotime($row['appointment_date'])),'LR',0,'C',$fill);
            $this->Cell($w[3],6,utf8_decode($row['doctor_name'] ?? 'N/A'),'LR',0,'L',$fill);
            $this->Cell($w[4],6,utf8_decode(ucfirst($row['status'])),'LR',0,'C',$fill);
            $this->Ln();
            $fill = !$fill;
        }
        $this->Cell(array_sum($w),0,'','T');
    }
}

// 4. GENERACIÓN DEL PDF, ENVÍO Y LIMPIEZA
$temp_dir = '../includes/temp/';
$filename = 'Reporte_Citas_' . uniqid() . '.pdf';
$file_path = $temp_dir . $filename;

try {
    // 4.1. Generar y guardar el PDF en el servidor
    $pdf = new PDF_Report();
    $pdf->AliasNbPages();
    $pdf->AddPage('L','A4'); // Horizontal
    $header = ['Paciente', 'Especialidad', 'Fecha', 'Medico Asignado', 'Estado'];
    $pdf->CreateTable($header, $appointments);
    $pdf->Output('F', $file_path); // 'F' para guardar en un archivo local

    // 4.2. Verificar que el archivo fue creado
    if (!file_exists($file_path) || filesize($file_path) == 0) {
        throw new Exception("El contenido del PDF no pudo ser generado en el servidor.");
    }

    // 4.3. Preparar y enviar el correo con el archivo adjunto
    $subject = "Reporte de Citas - " . APP_NAME;
    $body = "<p>Estimado(a),</p><p>Se adjunta el reporte de citas solicitado, generado el " . date('d/m/Y H:i:s') . ".</p><p>Gracias.</p>";
    
    if (send_email($recipient_email, 'Destinatario del Reporte', $subject, $body, [$file_path])) {
        $response['success'] = true;
        $response['message'] = 'Reporte enviado exitosamente por correo.';
    } else {
        $response['message'] = 'El reporte se generó, pero hubo un error al intentar enviar el correo.';
    }

} catch (Exception $e) {
    $response['message'] = 'Hubo un error al generar el archivo PDF: ' . $e->getMessage();
} finally {
    // 4.4. Limpiar: Eliminar el archivo temporal
    if (file_exists($file_path)) {
        unlink($file_path);
    }
}

// 5. Devolver la respuesta final al JavaScript
echo json_encode($response);
?>