<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');


if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Please login to upload a profile photo'
    ]);
    exit();
}

try {
    
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }

    $file = $_FILES['photo'];
    
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Invalid file type. Please upload a JPEG, PNG, or GIF image.');
    }

    
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('File too large. Maximum size is 5MB.');
    }

    
    $upload_dir = 'uploads/profile_photos/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

  
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;


    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save uploaded file');
    }

  
    if ($_SESSION['role'] === 'patient') {
        $stmt = $conn->prepare("
            UPDATE patients 
            SET profile_photo = ? 
            WHERE user_id = ?
        ");
    } else if ($_SESSION['role'] === 'doctor') {
        $stmt = $conn->prepare("
            UPDATE doctors 
            SET profile_photo = ? 
            WHERE user_id = ?
        ");
    } else {
        throw new Exception('Invalid user role');
    }

    $stmt->bind_param("si", $filepath, $_SESSION['user_id']);
    
    if (!$stmt->execute()) {
      
        unlink($filepath);
        throw new Exception('Failed to update profile photo in database');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Profile photo updated successfully',
        'photo_url' => $filepath
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
