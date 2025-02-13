<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['reset_verified']) || !isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: reset_password.php");
        exit();
    }

    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    try {
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashed_password, $_SESSION['reset_email']);
        
        if ($stmt->execute()) {
          
            session_destroy();
            header("Location: index.html");
            exit();
        } else {
            throw new Exception("Failed to update password");
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "An error occurred while resetting your password.";
        header("Location: reset_password.php");
        exit();
    }
} else {
    header("Location: reset_password.php");
    exit();
}
?>
