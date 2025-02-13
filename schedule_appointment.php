<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    echo json_encode([
        'success' => false,
        'message' => 'Please login as a patient to schedule appointments.'
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit();
}

try {
   
    $doctor_id = isset($_POST['doctor_id']) ? filter_var($_POST['doctor_id'], FILTER_VALIDATE_INT) : null;
    $date_time = isset($_POST['date_time']) ? trim($_POST['date_time']) : null;
    $reason = isset($_POST['reason']) ? trim(filter_var($_POST['reason'], FILTER_SANITIZE_STRING)) : null;
    $consultation_mode = isset($_POST['consultation_mode']) ? trim($_POST['consultation_mode']) : 'online';
    $consultation_type = isset($_POST['consultation_type']) ? trim($_POST['consultation_type']) : 'regular';
    $symptoms = isset($_POST['symptoms']) ? trim(filter_var($_POST['symptoms'], FILTER_SANITIZE_STRING)) : null;

   
    if (!$doctor_id || !$date_time || !$reason) {
        throw new Exception('Please fill in all required fields');
    }

    
    if (!in_array($consultation_mode, ['online', 'offline'])) {
        $consultation_mode = 'online';
    }
    if (!in_array($consultation_type, ['regular', 'follow_up', 'emergency'])) {
        $consultation_type = 'regular';
    }

    
    try {
        $date = new DateTime($date_time);
        $now = new DateTime();
        
        if ($date <= $now) {
            throw new Exception('Please select a future date and time');
        }

        $hour = (int)$date->format('H');
        if ($hour < 9 || $hour >= 17) {
            throw new Exception('Please select a time between 9 AM and 5 PM');
        }

   
        $dayOfWeek = (int)$date->format('w');
        if ($dayOfWeek === 0 || $dayOfWeek === 6) {
            throw new Exception('Appointments are not available on weekends');
        }

        $date_time = $date->format('Y-m-d H:i:s');
        $end_time = (clone $date)->modify('+1 hour')->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        throw new Exception('Invalid date or time format');
    }

    
    $conn->begin_transaction();

   
    $stmt = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Patient profile not found');
    }
    
    $patient = $result->fetch_assoc();
    $patient_id = $patient['patient_id'];

   
    $stmt = $conn->prepare("
        SELECT d.doctor_id, d.consultation_fee, d.availability_status 
        FROM doctors d
        JOIN users u ON d.user_id = u.user_id
        WHERE d.doctor_id = ? AND u.is_active = 1
        AND d.availability_status = 'available'
    ");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $doctor_result = $stmt->get_result();
    
    if ($doctor_result->num_rows === 0) {
        throw new Exception('Selected doctor is not available');
    }

    $doctor_data = $doctor_result->fetch_assoc();

    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE doctor_id = ? 
        AND (
            (appointment_date BETWEEN ? AND ?) 
            OR (end_time BETWEEN ? AND ?)
        )
        AND status NOT IN ('cancelled', 'completed')
    ");
    $stmt->bind_param("issss", $doctor_id, $date_time, $end_time, $date_time, $end_time);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['count'];

    if ($count > 0) {
        throw new Exception('This time slot is already booked. Please select a different time.');
    }


    $stmt = $conn->prepare("
        INSERT INTO appointments (
            doctor_id, patient_id, appointment_date, end_time,
            status, reason, symptoms, consultation_mode,
            consultation_type, payment_status
        ) VALUES (?, ?, ?, ?, 'scheduled', ?, ?, ?, ?, 'pending')
    ");
    $stmt->bind_param(
        "iissssss",
        $doctor_id, $patient_id, $date_time, $end_time,
        $reason, $symptoms, $consultation_mode, $consultation_type
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to schedule appointment');
    }

    $appointment_id = $conn->insert_id;

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Appointment scheduled successfully',
        'appointment_id' => $appointment_id,
        'consultation_fee' => $doctor_data['consultation_fee']
    ]);

} catch (Exception $e) {
    try {
        $conn->rollback();
    } catch (Exception $rollbackError) {
        
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>