<?php
require_once 'db_connection.php';
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
       
        $conn->begin_transaction();

       
        $username = $conn->real_escape_string(trim($_POST['username']));
        $email = $conn->real_escape_string(trim($_POST['email']));
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    
        $name = $conn->real_escape_string(trim($_POST['name']));
        $date_of_birth = $conn->real_escape_string(trim($_POST['date_of_birth']));
        $gender = $conn->real_escape_string(trim($_POST['gender']));
        $contact_number = $conn->real_escape_string(trim($_POST['contact_number']));

    
        $medical_history = $conn->real_escape_string(trim($_POST['medical_history']));
        $allergies = $conn->real_escape_string(trim($_POST['allergies']));

        
        $emergency_contact_name = $conn->real_escape_string(trim($_POST['emergency_contact_name']));
        $emergency_contact_number = $conn->real_escape_string(trim($_POST['emergency_contact_number']));

       
        $insurance_provider = $conn->real_escape_string(trim($_POST['insurance_provider']));
        $insurance_policy_number = $conn->real_escape_string(trim($_POST['insurance_policy_number']));

   
        $checkEmailQuery = "SELECT * FROM users WHERE email = '$email'";
        $result = $conn->query($checkEmailQuery);

        if ($result->num_rows > 0) {
            throw new Exception("The email address '$email' is already registered. Please use a different email.");
        }

        
        $sqlUser = "INSERT INTO users (username, email, password, role) 
                    VALUES ('$username', '$email', '$password', 'patient')";
        
        if (!$conn->query($sqlUser)) {
            throw new Exception("Error creating user account: " . $conn->error);
        }

        $userId = $conn->insert_id;

        
        $sqlPatient = "INSERT INTO patients (user_id, name, date_of_birth, gender, contact_number, 
                                           medical_history, allergies, emergency_contact_name, 
                                           emergency_contact_number, insurance_provider, 
                                           insurance_policy_number) 
                       VALUES ($userId, '$name', '$date_of_birth', '$gender', '$contact_number', 
                              '$medical_history', '$allergies', '$emergency_contact_name', 
                              '$emergency_contact_number', '$insurance_provider', 
                              '$insurance_policy_number')";

        if (!$conn->query($sqlPatient)) {
            throw new Exception("Error creating patient profile: " . $conn->error);
        }

       
        $otp = rand(100000, 999999);
        $_SESSION['otp'] = $otp;
        $_SESSION['user_id'] = $userId;
        $_SESSION['email'] = $email;

        
        $emailTemplate = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <img src='images/logo.png' alt='MediTouch Logo' style='max-width: 150px;'>
            </div>
            
            <h2 style='color: #007bff; text-align: center;'>Welcome to MediTouch!</h2>
            
            <p style='color: #444; font-size: 16px;'>Dear $name,</p>
            
            <p style='color: #444; line-height: 1.6;'>Thank you for choosing MediTouch for your healthcare needs. We're excited to have you join our platform. To complete your registration, please verify your email address using the OTP below:</p>
            
            <div style='background-color: #f8f9fa; padding: 15px; text-align: center; border-radius: 5px; margin: 20px 0;'>
                <h3 style='color: #007bff; margin: 0; font-size: 24px;'>$otp</h3>
            </div>
            
            <p style='color: #444; line-height: 1.6;'>Your account details:</p>
            <ul style='color: #444; line-height: 1.6;'>
                <li>Name: $name</li>
                <li>Username: $username</li>
                <li>Contact Number: $contact_number</li>
            </ul>
            
            <p style='color: #444; line-height: 1.6;'>Please enter this OTP on the verification page to activate your account. For security reasons, this OTP will expire in 10 minutes.</p>
            
            <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666;'>
                <p style='margin: 5px 0;'>If you didn't request this registration, please ignore this email.</p>
                <p style='margin: 5px 0;'>Need help? Contact us at support@meditouch.com</p>
            </div>
        </div>";

      
        $mail = new PHPMailer();
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'meditouchofficial@gmail.com';
            $mail->Password = 'gxpviklzyqfpurph';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('meditouchofficial@gmail.com', 'MediTouch');
            $mail->addAddress($email, $name);
            $mail->isHTML(true);
            $mail->Subject = 'Welcome to MediTouch - Email Verification';
            $mail->Body = $emailTemplate;

            $mail->send();
            $conn->commit();
            
            header("Location: verify_patient_otp.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error sending verification email. Please try again later.";
            header("Location: patient_register.html");
            exit();
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: patient_register.html");
        exit();
    }
} else {
    header("Location: patient_register.html");
    exit();
}
?>