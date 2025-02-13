<?php
session_start();
require_once 'db_connection.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}


$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;


$stmt = $conn->prepare("
    SELECT 
        p.patient_id,
        p.name,
        p.date_of_birth,
        p.gender,
        p.contact_number,
        p.medical_history,
        p.allergies,
        p.emergency_contact_name,
        p.emergency_contact_number,
        p.insurance_provider,
        u.email,
        (SELECT COUNT(*) FROM appointments WHERE patient_id = p.patient_id) as total_appointments,
        (SELECT COUNT(*) FROM prescriptions pr 
         JOIN appointments a ON pr.appointment_id = a.appointment_id 
         WHERE a.patient_id = p.patient_id) as total_prescriptions
    FROM 
        patients p
    JOIN 
        users u ON p.user_id = u.user_id
    WHERE 
        p.patient_id = ?
");

$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Patient not found']);
    exit();
}

$patient = $result->fetch_assoc();


$stmt_appointments = $conn->prepare("
    SELECT 
        a.appointment_id,
        a.appointment_date,
        a.status,
        d.name AS doctor_name,
        d.specialization
    FROM 
        appointments a
    JOIN 
        doctors d ON a.doctor_id = d.doctor_id
    WHERE 
        a.patient_id = ?
    ORDER BY 
        a.appointment_date DESC
    LIMIT 5
");
$stmt_appointments->bind_param("i", $patient_id);
$stmt_appointments->execute();
$appointments_result = $stmt_appointments->get_result();
$recent_appointments = [];

while ($appointment = $appointments_result->fetch_assoc()) {
    $recent_appointments[] = $appointment;
}


$stmt_prescriptions = $conn->prepare("
    SELECT 
        pr.prescription_id,
        pr.medication,
        pr.dosage,
        pr.frequency,
        pr.duration,
        pr.notes,
        d.name AS prescribed_by,
        a.appointment_date
    FROM 
        prescriptions pr
    JOIN 
        appointments a ON pr.appointment_id = a.appointment_id
    JOIN 
        doctors d ON pr.doctor_id = d.doctor_id
    WHERE 
        pr.patient_id = ?
    ORDER BY 
        a.appointment_date DESC
    LIMIT 5
");
$stmt_prescriptions->bind_param("i", $patient_id);
$stmt_prescriptions->execute();
$prescriptions_result = $stmt_prescriptions->get_result();
$recent_prescriptions = [];

while ($prescription = $prescriptions_result->fetch_assoc()) {
    $recent_prescriptions[] = $prescription;
}


$patient_details = [
    'personal_info' => $patient,
    'recent_appointments' => $recent_appointments,
    'recent_prescriptions' => $recent_prescriptions
];

echo json_encode($patient_details);
?>