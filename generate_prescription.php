<?php
session_start();
require_once 'db_connection.php';
require_once 'vendor/autoload.php';


error_reporting(E_ALL);
ini_set('display_errors', 1);


if (!isset($_SESSION['user_id']) || !isset($_GET['appointment_id'])) {
    die("Unauthorized access or missing appointment ID");
}

$appointment_id = intval($_GET['appointment_id']);


$stmt = $conn->prepare("
    SELECT 
        a.appointment_date,
        p.name AS patient_name,
        p.gender,
        p.date_of_birth,
        TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
        d.name AS doctor_name,
        d.specialization,
        GROUP_CONCAT(
            CONCAT(
                pr.medication, '|',
                pr.dosage, '|',
                pr.frequency, '|',
                pr.duration
            ) SEPARATOR '||'
        ) as prescriptions,
        pr.notes
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN patients p ON a.patient_id = p.patient_id
    LEFT JOIN prescriptions pr ON a.appointment_id = pr.appointment_id
    WHERE a.appointment_id = ? AND (p.user_id = ? OR d.user_id = ?)
    GROUP BY a.appointment_id
");

$stmt->bind_param("iii", $appointment_id, $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Prescription not found");
}

$data = $result->fetch_assoc();


$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('MediTouch');
$pdf->SetAuthor('Dr. ' . $data['doctor_name']);
$pdf->SetTitle('Medical Prescription');

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();


$pdf->SetFont('helvetica', 'B', 24);
$pdf->SetTextColor(41, 128, 185);
$pdf->Cell(0, 10, 'MediTouch', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 12);
$pdf->SetTextColor(127, 140, 141);
$pdf->Cell(0, 8, 'Digital Healthcare Solutions', 0, 1, 'C');
$pdf->Ln(5);


$pdf->SetTextColor(44, 62, 80);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'PRESCRIPTION', 0, 1, 'C');
$pdf->Ln(5);


$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(95, 7, 'Patient Information:', 0);
$pdf->Cell(95, 7, 'Doctor Information:', 0);
$pdf->Ln();

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(95, 7, 'Name: ' . $data['patient_name'], 0);
$pdf->Cell(95, 7, 'Dr. ' . $data['doctor_name'], 0);
$pdf->Ln();

$pdf->Cell(95, 7, 'Gender: ' . $data['gender'], 0);
$pdf->Cell(95, 7, 'Specialization: ' . $data['specialization'], 0);
$pdf->Ln();

$pdf->Cell(95, 7, 'Age: ' . $data['age'], 0);
$pdf->Cell(95, 7, 'Date: ' . date('F j, Y', strtotime($data['appointment_date'])), 0);
$pdf->Ln(15);

$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'MEDICATIONS', 0, 1, 'L');
$pdf->Ln(2);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor(241, 246, 249);
$pdf->Cell(50, 7, 'Medication', 1, 0, 'C', true);
$pdf->Cell(45, 7, 'Dosage', 1, 0, 'C', true);
$pdf->Cell(45, 7, 'Frequency', 1, 0, 'C', true);
$pdf->Cell(45, 7, 'Duration', 1, 1, 'C', true);

$pdf->SetFont('helvetica', '', 10);
if ($data['prescriptions']) {
    $prescriptions = explode('||', $data['prescriptions']);
    foreach ($prescriptions as $prescription) {
        list($medication, $dosage, $frequency, $duration) = explode('|', $prescription);
        $pdf->Cell(50, 7, $medication, 1);
        $pdf->Cell(45, 7, $dosage, 1);
        $pdf->Cell(45, 7, $frequency, 1);
        $pdf->Cell(45, 7, $duration, 1);
        $pdf->Ln();
    }
}


if (!empty($data['notes'])) {
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'NOTES', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->MultiCell(0, 7, $data['notes'], 0, 'L');
}

$pdf->Ln(15);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 7, 'Digital Signature', 0, 1, 'R');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 7, 'Dr. ' . $data['doctor_name'], 0, 1, 'R');
$pdf->Cell(0, 7, $data['specialization'], 0, 1, 'R');


$pdf->Output('prescription_' . $appointment_id . '.pdf', 'I');
exit();
