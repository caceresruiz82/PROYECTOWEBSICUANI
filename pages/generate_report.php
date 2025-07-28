<?php
/**
 * Script para generar un reporte en PDF de las citas seleccionadas.
 */

require_once '../includes/auth_guard.php';
require_once '../includes/db_connection.php';
require_once '../includes/fpdf/fpdf.php'; // Incluimos la biblioteca FPDF

// Guardia de Rol
if ($_SESSION['user_role'] !== 'Admision' && $_SESSION['user_role'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

// Verificar que se hayan enviado IDs de citas
if (!isset($_POST['appointment_ids']) || !is_array($_POST['appointment_ids']) || empty($_POST['appointment_ids'])) {
    die("No se ha seleccionado ninguna cita para generar el reporte. Por favor, regrese y seleccione al menos una.");
}

// Sanitizar los IDs recibidos
$appointment_ids = array_map('intval', $_POST['appointment_ids']);
$placeholders = implode(',', array_fill(0, count($appointment_ids), '?'));

try {
    // Obtener los datos completos de las citas seleccionadas
    $sql = "SELECT 
                a.appointment_date, a.appointment_time, a.status,
                p.full_name as patient_name, p.document_type, p.document_number,
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

} catch (PDOException $e) {
    die("Error en la base de datos al generar el reporte: " . $e->getMessage());
}

// --- CREACIÓN DEL PDF ---

class PDF extends FPDF
{
    // Cabecera de página
    function Header()
    {
        // Logo (opcional, si tienes un logo en assets/images/logo_essalud.png)
        // $this->Image('../assets/images/logo_essalud.png',10,6,30);
        $this->SetFont('Arial','B',14);
        $this->Cell(0,10,utf8_decode('Reporte de Citas Programadas'),0,1,'C');
        $this->SetFont('Arial','',10);
        $this->Cell(0,10,utf8_decode('Generado el: ') . date('d/m/Y H:i:s'),0,1,'C');
        $this->Ln(10);
    }

    // Pie de página
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,utf8_decode('Página ') . $this->PageNo() . '/{nb}',0,0,'C');
    }

    // Tabla de datos
    function CreateTable($header, $data)
    {
        // Colores, ancho de línea y fuente en negrita
        $this->SetFillColor(200,220,255);
        $this->SetTextColor(0);
        $this->SetDrawColor(128,0,0);
        $this->SetLineWidth(.3);
        $this->SetFont('','B');
        
        // Cabecera
        $w = array(70, 50, 30, 90, 30); // Anchos de las columnas
        for($i=0;$i<count($header);$i++)
            $this->Cell($w[$i],7,utf8_decode($header[$i]),1,0,'C',true);
        $this->Ln();
        
        // Restauración de colores y fuentes
        $this->SetFillColor(224,235,255);
        $this->SetTextColor(0);
        $this->SetFont('');
        
        // Datos
        $fill = false;
        foreach($data as $row)
        {
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

// Instanciación de la clase PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage('L','A4'); // 'L' para Landscape (horizontal)

// Definir la cabecera de la tabla
$header = array('Paciente', 'Especialidad', 'Fecha', 'Medico Asignado', 'Estado');

// Enviar los datos al método que crea la tabla
$pdf->CreateTable($header, $appointments);

// Salida del PDF
$pdf->Output('D', 'Reporte_Citas_'.date('Y-m-d').'.pdf'); // 'D' para forzar la descarga
?>