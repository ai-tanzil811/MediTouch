<?php 
session_start();
require_once 'db_connection.php';


if (!isset($_SESSION['reset_verified']) || !isset($_SESSION['reset_email'])) {
    $_SESSION['error'] = "Please complete the verification process first.";
    header("Location: forgot_password.html");
    exit();
}

$error = '';
$success = '';
$email = $_SESSION['reset_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        try {
            $conn->begin_transaction();
            
           
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
              
                $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $updateStmt->bind_param("si", $hashed_password, $user['user_id']);
                
                if ($updateStmt->execute()) {
                   
                    $cleanupStmt = $conn->prepare("UPDATE password_reset_attempts SET used = 1 WHERE user_id = ?");
                    $cleanupStmt->bind_param("i", $user['user_id']);
                    $cleanupStmt->execute();
                    
                    $conn->commit();
                    $success = "Password reset successful! You can now login with your new password.";
                    
                    
                    session_unset();
                    session_destroy();
                } else {
                    throw new Exception("Failed to update password");
                }
            } else {
                throw new Exception("Invalid user account");
            }
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Password update error: " . $e->getMessage());
            $error = "An error occurred. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - MediTouch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="password_pages.css">
 </head>
<body>
    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="reset-container card shadow">
            <div class="text-center mb-4">
                <img src="images/logo.png" alt="MediTouch Logo" class="brand-logo">
                <h4>Reset Password</h4>
                <p class="text-muted">Create a new secure password</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form id="resetForm" method="POST" onsubmit="return validateForm(event)">
                <div class="mb-3">
                    <div class="input-group">
                        <input type="password" 
                               class="form-control" 
                               placeholder="New password"
                               id="password" 
                               name="password" 
                               required>
                        <button class="btn" type="button" onclick="togglePassword('password')">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="input-group">
                        <input type="password" 
                               class="form-control" 
                               placeholder="Confirm password"
                               id="confirm_password" 
                               name="confirm_password" 
                               required>
                        <button class="btn" type="button" onclick="togglePassword('confirm_password')">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    Reset Password
                </button>
            </form>
            <a href="index.html" class="btn-home">
                <i class="bi bi-house-door"></i>
                Back to Home
            </a>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            input.type = input.type === 'password' ? 'text' : 'password';
        }

        function validateForm(event) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (password !== confirmPassword) {
                event.preventDefault();
                alert('Passwords do not match');
                return false;
            }
        }
    </script>
</body>
</html>
