<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}


$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['appointment_id']) || !isset($data['notes'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

$appointment_id = $data['appointment_id'];
$notes = $data['notes'];

try {
    
    $conn->begin_transaction();

   
    $stmt = $conn->prepare("
        SELECT a.appointment_id
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.doctor_id
        WHERE a.appointment_id = ? AND d.user_id = ?
    ");

    $stmt->bind_param("ii", $appointment_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Unauthorized access to this appointment');
    }

    $appointment = $result->fetch_assoc();
    
    $stmt = $conn->prepare("
        SELECT notes_id 
        FROM consultation_notes 
        WHERE appointment_id = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $notes_result = $stmt->get_result();

    if ($notes_result->num_rows > 0) {
        
        $stmt = $conn->prepare("
            UPDATE consultation_notes 
            SET notes = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE appointment_id = ?
        ");
        $stmt->bind_param("si", $notes, $appointment_id);
    } else {
        
        $stmt = $conn->prepare("
            INSERT INTO consultation_notes (appointment_id, notes) 
            VALUES (?, ?)
        ");
        $stmt->bind_param("is", $appointment_id, $notes);
    }

    if (!$stmt->execute()) {
        throw new Exception('Failed to save consultation notes');
    }

   
    $stmt = $conn->prepare("
        UPDATE appointments 
        SET status = 'completed', updated_at = CURRENT_TIMESTAMP 
        WHERE appointment_id = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update appointment status');
    }

   
    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Consultation notes saved successfully'
    ]);

} catch (Exception $e) {

    try {
        $conn->rollback();
    } catch (Exception $rollbackError) {
      
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();