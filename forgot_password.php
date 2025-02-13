<?php
session_start();
require_once 'db_connection.php';
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'password_reset_errors.log');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    // Validate the email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format";
        header("Location: forgot_password.html");
        exit();
    }
    
    try {
        // Begin transaction
        $conn->begin_transaction();
        // Check if email exists and get user type
        $stmt = $conn->prepare("SELECT u.user_id, u.email,
                                      CASE
                                          WHEN d.doctor_id IS NOT NULL THEN 'doctor'
                                          WHEN p.patient_id IS NOT NULL THEN 'patient'
                                      END as user_type
                               FROM users u
                               LEFT JOIN doctors d ON u.user_id = d.user_id
                               LEFT JOIN patients p ON u.user_id = p.patient_id
                               WHERE u.email = ?");
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // Generate a unique OTP
            $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            // Store OTP in database
            $stmt = $conn->prepare("INSERT INTO password_reset_attempts (user_id, email, otp) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user['user_id'], $email, $otp);
            $stmt->execute();

            $attempt_id = $conn->insert_id;
            
            // Store attempt_id and email in session for verification
            $_SESSION['reset_attempt_id'] = $attempt_id;
            $_SESSION['reset_email'] = $email;

            // Send email using PHPMailer
            $mail = new PHPMailer(true);
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'meditouchofficial@gmail.com';
                $mail->Password = 'gxpviklzyqfpurph';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                // Recipients
                $mail->setFrom('meditouchofficial@gmail.com', 'MediTouch Support');
                $mail->addAddress($email);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset OTP';
                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <div style='background-color: #00796b; padding: 20px; text-align: center;'>
                            <h1 style='color: white; margin: 0;'>MediTouch Password Reset</h1>
                        </div>
                        <div style='padding: 20px; background-color: #f9f9f9;'>
                            <p>Hello,</p>
                            <p>Your password reset verification code is:</p>
                            <div style='background-color: #fff; padding: 15px; text-align: center; margin: 20px 0;'>
                                <h2 style='color: #00796b; letter-spacing: 5px; margin: 0;'>$otp</h2>
                            </div>
                            <p>If you didn't request this password reset, please ignore this email.</p>
                            <p>Best regards,<br>MediTouch Team</p>
                        </div>
                    </div>";

                $mail->send();
                $conn->commit();
                
                header("Location: verify_otp.php");
                exit();
            } catch (Exception $e) {
                throw new Exception("Email could not be sent. Please try again later.");
            }
        } else {
            // Don't reveal if email exists or not
            header("Location: verify_otp.php");
            exit();
        }
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Password reset error: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
        header("Location: forgot_password.html");
        exit();
    }
} else {
    // If accessed directly without POST
    header("Location: forgot_password.html");
    exit();
}
?>
