<?php
session_start();
require_once 'db_connection.php';


if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_attempt_id'])) {
    $_SESSION['error'] = "Please request a new password reset.";
    header("Location: forgot_password.html");
    exit();
}

$error = '';
$email = $_SESSION['reset_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 
    $enteredOtp = '';
    for ($i = 1; $i <= 6; $i++) {
        if (isset($_POST['otp_' . $i])) {
            $enteredOtp .= $_POST['otp_' . $i];
        }
    }
    

    $stmt = $conn->prepare("SELECT id FROM password_reset_attempts 
                           WHERE id = ? 
                           AND email = ? 
                           AND otp = ? 
                           AND used = 0");
                           
    $stmt->bind_param("iss", $_SESSION['reset_attempt_id'], $email, $enteredOtp);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 1) {
       
        $updateStmt = $conn->prepare("UPDATE password_reset_attempts SET used = 1 WHERE id = ?");
        $updateStmt->bind_param("i", $_SESSION['reset_attempt_id']);
        $updateStmt->execute();
        
        
        $_SESSION['reset_verified'] = true;
        header("Location: reset_password.php");
        exit();
    } else {
       
        $checkStmt = $conn->prepare("SELECT used FROM password_reset_attempts WHERE id = ? AND email = ?");
        $checkStmt->bind_param("is", $_SESSION['reset_attempt_id'], $email);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0 && $result->fetch_assoc()['used'] == 1) {
            $error = "This OTP has already been used. Please request a new one.";
        } else {
            $error = "Invalid OTP. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - MediTouch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
    background: url('images/loginbackground.jpg') no-repeat center center fixed;
    color
    background-size: cover;
    min-height: 100vh;
}

.reset-container {
    background: rgba(255, 255, 255, 0.95);
    max-width: 400px;
    width: 90%;
}

.brand-logo {
    max-width: 150px;
    height: auto;
}

.input-group-text {
    background-color: #f8f9fa;
    border-right: none;
}

.input-group .form-control {
    border-left: none;
}

.input-group .form-control:focus {
    border-color: #dee2e6;
    box-shadow: none;
}

.btn-primary {
    background-color: #00796b;
    border-color: #00796b;
    padding: 0.75rem;
    font-weight: 600;
    transition: background-color 0.3s ease;
}

.btn-primary:hover {
    background-color: #004d40;
    border-color: #004d40;
}

.form-text {
    font-size: 0.875rem;
    color: #6c757d;
}

a {
    color: #00796b;
    transition: color 0.3s ease;
}

a:hover {
    color: #004d40;
}
    </style>
</head>
<body>
    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="reset-container card p-4 shadow">
            <div class="text-center mb-4">
                <img src="images/logo.png" alt="MediTouch Logo" class="brand-logo mb-3">
                <h4>Enter Verification Code</h4>
                <p class="text-muted">We sent a code to <?php echo htmlspecialchars($email); ?></p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger mb-3">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="d-flex justify-content-between gap-2 mb-4">
                    <?php for($i = 1; $i <= 6; $i++): ?>
                        <input type="text" 
                               name="otp_<?php echo $i; ?>" 
                               class="form-control text-center fw-bold" 
                               maxlength="1" 
                               pattern="[0-9]"
                               inputmode="numeric"
                               autocomplete="off"
                               required
                               style="width: 45px; height: 45px;">
                    <?php endfor; ?>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">
                    Verify Code
                </button>
            </form>

            <div class="text-center">
                <a href="forgot_password.html" class="text-decoration-none">
                    Back to Reset Password
                </a>
            </div>
        </div>
    </div>

    <script>
        
        document.querySelector('input[name="otp_1"]').focus();

        
        document.querySelectorAll('input[type="text"]').forEach((input, index) => {
           
            input.addEventListener('input', function(e) {
               
                this.value = this.value.replace(/[^0-9]/g, '');
                
                if (this.value.length === 1) {
                    if (index < 5) {
                        document.getElementsByName(`otp_${index + 2}`)[0].focus();
                    }
                }
            });

            
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && !this.value && index > 0) {
                    document.getElementsByName(`otp_${index}`)[0].focus();
                }
            });

        
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6);
                
                document.querySelectorAll('input[type="text"]').forEach((input, i) => {
                    if (i < pastedData.length) {
                        input.value = pastedData[i];
                        if (i === 5) input.focus();
                    }
                });
            });
        });
    </script>
</body>
</html>
