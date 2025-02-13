<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

try {
    
    $availability = isset($_POST['availability']) ? filter_var($_POST['availability'], FILTER_VALIDATE_BOOLEAN) : null;
    
    if ($availability === null) {
        throw new Exception('Invalid availability status');
    }

    
    $stmt = $conn->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Doctor profile not found');
    }
    
    $doctor = $result->fetch_assoc();
    $doctor_id = $doctor['doctor_id'];

    
    $stmt = $conn->prepare("UPDATE doctors SET availability_status = ? WHERE doctor_id = ?");
    $status = $availability ? 'available' : 'unavailable';
    $stmt->bind_param("si", $status, $doctor_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update availability status');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Availability status updated successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    $conn->close();
}
?>
