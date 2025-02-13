<?php
session_start();

if (!isset($_SESSION['otp']) || !isset($_SESSION['email'])) {
    $_SESSION['error'] = "No verification in progress. Please register again.";
    header("Location: doctor_login_page.html");
    exit();
}

$error = '';
$email = $_SESSION['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enteredOtp = '';
    for ($i = 1; $i <= 6; $i++) {
        if (isset($_POST['otp_' . $i])) {
            $enteredOtp .= $_POST['otp_' . $i];
        }
    }
    
    if ($enteredOtp == $_SESSION['otp']) {
        
        unset($_SESSION['otp']);
        $_SESSION['verified'] = true;
        
        
        header("Location: doctor_login_page.html");
        exit();
    } else {
        $error = "Invalid verification code. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - MediTouch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="password_pages.css">
    <style>
        .otp-input-container {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 2rem 0;
        }

        .otp-input {
            width: 45px;
            height: 45px;
            padding: 0;
            text-align: center;
            font-size: 1.2rem;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            background: white;
            transition: all 0.3s ease;
        }

        .otp-input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
            outline: none;
        }

        .timer {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 1rem;
        }

        .resend-button {
            color: #007bff;
            background: none;
            border: none;
            padding: 0;
            font: inherit;
            cursor: pointer;
            text-decoration: underline;
        }

        .resend-button:disabled {
            color: #6c757d;
            cursor: not-allowed;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="reset-container card shadow">
            <div class="text-center mb-4">
                <img src="images/logo.png" alt="MediTouch Logo" class="brand-logo">
                <h4>Email Verification</h4>
                <p class="text-muted">We've sent a verification code to<br><?php echo htmlspecialchars($email); ?></p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger text-center" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="otpForm">
                <div class="otp-input-container">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <input type="text" 
                               class="otp-input" 
                               name="otp_<?php echo $i; ?>" 
                               id="otp_<?php echo $i; ?>" 
                               maxlength="1" 
                               pattern="[0-9]" 
                               inputmode="numeric"
                               required>
                    <?php endfor; ?>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    Verify Email
                </button>
            </form>

            <div class="text-center mt-3">
                <div class="timer" id="timer">
                    Resend code in <span id="countdown">02:00</span>
                </div>
                <button class="resend-button mt-2" id="resendButton" disabled>
                    Resend verification code
                </button>
            </div>

            <a href="doctor_register.html" class="btn-home mt-3">
                <i class="bi bi-arrow-left"></i>
                Back to Registration
            </a>
        </div>
    </div>

    <script>
        
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.otp-input');
            
            inputs.forEach((input, index) => {
                
                if (index === 0) input.focus();

                input.addEventListener('input', function(e) {
                    if (e.inputType === "deleteContentBackward") return;
                    
                    const value = this.value;
                    if (value.length === 1) {
                        if (index < inputs.length - 1) {
                            inputs[index + 1].focus();
                        }
                    }
                });

                input.addEventListener('keydown', function(e) {
                    if (e.key === "Backspace" && !this.value) {
                        if (index > 0) {
                            inputs[index - 1].focus();
                        }
                    }
                });

                
                input.addEventListener('keypress', function(e) {
                    if (e.key < '0' || e.key > '9') {
                        e.preventDefault();
                    }
                });
            });
        });

        
        function startTimer(duration, display) {
            let timer = duration;
            const resendButton = document.getElementById('resendButton');
            
            let interval = setInterval(function () {
                const minutes = parseInt(timer / 60, 10);
                const seconds = parseInt(timer % 60, 10);

                display.textContent = minutes.toString().padStart(2, '0') + ":" + 
                                    seconds.toString().padStart(2, '0');

                if (--timer < 0) {
                    clearInterval(interval);
                    display.parentElement.style.display = 'none';
                    resendButton.disabled = false;
                }
            }, 1000);
        }

        window.onload = function () {
            const display = document.querySelector('#countdown');
            startTimer(120, display);
        };
    </script>
</body>
</html>