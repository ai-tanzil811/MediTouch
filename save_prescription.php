<?php
session_start();
require_once 'db_connection.php';
require_once 'vendor/autoload.php';

header('Content-Type: application/json');


error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'prescription_save_errors.log');

function debug_log($message, $data = null) {
    $log_message = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $log_message .= "\nData: " . print_r($data, true);
    }
    file_put_contents('debug.log', $log_message . "\n", FILE_APPEND);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}


$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['appointment_id']) || !isset($data['prescriptions'])) {
    debug_log("Invalid request data", $data);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

$appointment_id = $data['appointment_id'];
$prescriptions = $data['prescriptions'];
$notes = $data['notes'] ?? '';
$doctor_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT 
        a.doctor_id, 
        a.patient_id,
        d.doctor_id as actual_doctor_id,
        d.name as doctor_name,
        d.specialization,
        p.name as patient_name,
        p.gender,
        p.date_of_birth,
        TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age
    FROM appointments a
    JOIN doctors d ON d.user_id = ?
    JOIN patients p ON a.patient_id = p.patient_id
    WHERE a.appointment_id = ?
");

$stmt->bind_param("ii", $doctor_id, $appointment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
    exit();
}

$appointment = $result->fetch_assoc();


$conn->begin_transaction();

try {
    
    $stmt = $conn->prepare("
        INSERT INTO prescriptions (
            appointment_id, patient_id, doctor_id, 
            medication, dosage, frequency, duration, notes,
            is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");

    foreach ($prescriptions as $prescription) {
        $stmt->bind_param(
            "iiisssss",
            $appointment_id,
            $appointment['patient_id'],
            $appointment['actual_doctor_id'],
            $prescription['medication'],
            $prescription['dosage'],
            $prescription['frequency'],
            $prescription['duration'],
            $notes
        );
        $stmt->execute();
    }

    $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    

    $pdf->SetCreator('MediTouch Healthcare');
    $pdf->SetAuthor('MediTouch Healthcare');
    $pdf->SetTitle('Medical Prescription');

 
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);

  
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);


    $pdf->AddPage();


    $pdf->SetFont('helvetica', '', 12);

   
    $prescriptions_html = '';
    foreach ($prescriptions as $prescription) {
        $prescriptions_html .= "
            <div class='prescription-item' style='margin-bottom: 10px;'>
                <div><strong>Medication:</strong> " . htmlspecialchars($prescription['medication']) . "</div>
                <div><strong>Dosage:</strong> " . htmlspecialchars($prescription['dosage']) . "</div>
                <div><strong>Frequency:</strong> " . htmlspecialchars($prescription['frequency']) . "</div>
                <div><strong>Duration:</strong> " . htmlspecialchars($prescription['duration']) . "</div>
            </div>
        ";
    }

    
    $html = "
    <style>
        .header { text-align: center; margin-bottom: 20px; }
        .title { font-size: 24px; font-weight: bold; color: #2c3e50; }
        .subtitle { font-size: 16px; color: #7f8c8d; }
        .info { margin: 20px 0; }
        .info-item { margin: 5px 0; }
        .prescription { margin-top: 20px; }
        .footer { margin-top: 30px; text-align: right; }
    </style>
    <div class='header'>
        <div class='title'>MediTouch Healthcare</div>
        <div class='subtitle'>Digital Healthcare Solutions</div>
    </div>
    <div class='info'>
        <div class='info-item'><strong>Patient Name:</strong> " . htmlspecialchars($appointment['patient_name']) . "</div>
        <div class='info-item'><strong>Gender:</strong> " . htmlspecialchars($appointment['gender']) . "</div>
        <div class='info-item'><strong>Age:</strong> " . htmlspecialchars($appointment['age']) . "</div>
        <div class='info-item'><strong>Date:</strong> " . date('F j, Y') . "</div>
        <div class='info-item'><strong>Doctor:</strong> Dr. " . htmlspecialchars($appointment['doctor_name']) . "</div>
        <div class='info-item'><strong>Specialization:</strong> " . htmlspecialchars($appointment['specialization']) . "</div>
    </div>
    <div class='prescription'>
        <h3>Prescription</h3>
        " . $prescriptions_html . "
        " . ($notes ? "<div class='notes'><strong>Notes:</strong> " . nl2br(htmlspecialchars($notes)) . "</div>" : "") . "
    </div>
    <div class='footer'>
        <div>Dr. " . htmlspecialchars($appointment['doctor_name']) . "</div>
        <div>" . htmlspecialchars($appointment['specialization']) . "</div>
    </div>";

    
    $upload_dir = __DIR__ . '/uploads/prescriptions/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $filename = 'prescription_' . $appointment_id . '_' . time() . '.pdf';
    $filepath = $upload_dir . $filename;

   
    $pdf->writeHTML($html, true, false, true, false, '');

    $pdf->Output($filepath, 'F');

    $stmt = $conn->prepare("
        UPDATE appointments 
        SET status = 'completed',
            prescription_file = ?
        WHERE appointment_id = ?
    ");

    $stmt->bind_param("si", $filepath, $appointment_id);
    $stmt->execute();

    $conn->commit();
    echo json_encode([
        'success' => true, 
        'message' => 'Prescription saved successfully',
        'file_path' => $filepath
    ]);

} catch (Exception $e) {
    $conn->rollback();
    debug_log("Error saving prescription", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error saving prescription: ' . $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}