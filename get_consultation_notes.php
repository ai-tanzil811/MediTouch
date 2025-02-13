<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['appointment_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Appointment ID is required']);
    exit();
}

$appointment_id = $_GET['appointment_id'];

try {
   
    $stmt = $conn->prepare("
        SELECT cn.notes, cn.created_at, cn.updated_at,
               a.appointment_date, p.name as patient_name
        FROM consultation_notes cn
        JOIN appointments a ON cn.appointment_id = a.appointment_id
        JOIN patients p ON a.patient_id = p.patient_id
        JOIN doctors d ON a.doctor_id = d.doctor_id
        WHERE cn.appointment_id = ? AND d.user_id = ?
    ");

    $stmt->bind_param("ii", $appointment_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => true, 'notes' => null]);
        exit();
    }

    $notes = $result->fetch_assoc();
    echo json_encode(['success' => true, 'notes' => $notes]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
