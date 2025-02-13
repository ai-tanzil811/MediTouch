<?php
session_start();
require_once 'db_connection.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $email = filter_var($email, FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: doctor_login_page.html?error=Invalid email format");
        exit();
    }

    $stmt = $conn->prepare("SELECT user_id, password FROM users WHERE email = ? AND role = 'doctor'");
    if ($stmt === false) {
        header("Location: doctor_login_page.html?error=Database error");
        exit();
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = 'doctor';
            header("Location: doctor_portal.php"); 
            exit();
        } else {
            header("Location: doctor_login_page.html?error=Incorrect password");
            exit();
        }
    } else {
        header("Location: doctor_login_page.html?error=Account not found");
        exit();
    }
} else {
    header("Location: doctor_login_page.html");
    exit();
}
?>
