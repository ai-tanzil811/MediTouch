<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: patient_login_page.html?error=Please login to schedule an appointment.");
    exit();
}


$stmt = $conn->prepare("
    SELECT name, medical_history, allergies 
    FROM patients 
    WHERE user_id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();


$stmt = $conn->prepare("
    SELECT 
        d.doctor_id,
        d.name AS doctor_name,
        u.email,
        d.specialization,
        d.consultation_fee,
        d.consultation_hours,
        d.availability_status,
        d.years_of_experience,
        d.qualifications,
        d.rating
    FROM 
        doctors d
        JOIN users u ON d.user_id = u.user_id
    WHERE 
        u.is_active = 1 
        AND u.role = 'doctor'
        AND d.availability_status = 'available'
    ORDER BY 
        d.rating DESC, d.years_of_experience DESC
");
$stmt->execute();
$doctors = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Appointment - MediTouch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2D5A27;
            --secondary-color: #4A90E2;
            --accent-color: hsl(176, 96.40%, 56.10%);
        }
        body {
            background-color: var(--accent-color);
            min-height: 100vh;
        }
        .navbar {
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            background-color: rgba(255, 255, 255, 0.95);
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-primary:hover {
            background-color: #234a1f;
            border-color: #234a1f;
        }
        .doctor-info {
            font-size: 0.9rem;
            color: #666;
            margin-top: 5px;
        }
        .doctor-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: white;
        }
        .rating-stars {
            color:rgb(219, 204, 160);
        }
        #loadingSpinner {
            display: none;
        }
        .error-feedback {
            color: #dc3545;
            font-size: 0.875em;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="images/logo.png" alt="MediTouch Logo" height="40">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="patient_portal.php">
                            <i class="bi bi-house-door"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_appointments.php">
                            <i class="bi bi-calendar-check"></i> My Appointments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title text-center mb-4">Schedule an Appointment</h3>
                        
                        <form id="appointmentForm" onsubmit="scheduleAppointment(event)">
                            <div class="mb-3">
                                <label for="doctor" class="form-label">Select Doctor</label>
                                <select class="form-select" id="doctor" name="doctor_id" required>
                                    <option value="">Choose a doctor...</option>
                                    <?php while($doctor = $doctors->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($doctor['doctor_id']); ?>" 
                                            data-fee="<?php echo htmlspecialchars($doctor['consultation_fee']); ?>"
                                            data-hours="<?php echo htmlspecialchars($doctor['consultation_hours']); ?>"
                                            data-specialization="<?php echo htmlspecialchars($doctor['specialization']); ?>"
                                            data-experience="<?php echo htmlspecialchars($doctor['years_of_experience']); ?>"
                                            data-rating="<?php echo htmlspecialchars($doctor['rating']); ?>">
                                        Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?> - 
                                        <?php echo htmlspecialchars($doctor['specialization']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                                <div id="doctorInfo" class="doctor-info mt-2"></div>
                            </div>

                            <div class="mb-3">
                                <label for="date_time" class="form-label">Appointment Date & Time</label>
                                <input type="text" class="form-control" id="date_time" name="date_time" required>
                                <div class="form-text">Available hours: 9 AM to 5 PM, Monday to Friday</div>
                            </div>

                            <div class="mb-3">
                                <label for="consultation_type" class="form-label">Consultation Type</label>
                                <select class="form-select" id="consultation_type" name="consultation_type" required>
                                    <option value="regular">Regular Checkup</option>
                                    <option value="follow_up">Follow-up Visit</option>
                                    <option value="emergency">Emergency</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="consultation_mode" class="form-label">Consultation Mode</label>
                                <select class="form-select" id="consultation_mode" name="consultation_mode" required>
                                    <option value="online">Online</option>
                                    <option value="offline">In-Person</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="reason" class="form-label">Reason for Visit</label>
                                <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="symptoms" class="form-label">Current Symptoms</label>
                                <textarea class="form-control" id="symptoms" name="symptoms" rows="3"></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Medical History</label>
                                <p class="form-text"><?php echo htmlspecialchars($patient['medical_history'] ?? 'No medical history recorded'); ?></p>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Allergies</label>
                                <p class="form-text"><?php echo htmlspecialchars($patient['allergies'] ?? 'No allergies recorded'); ?></p>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    Schedule Appointment
                                </button>
                                <div id="loadingSpinner" class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        
        flatpickr("#date_time", {
            enableTime: true,
            minTime: "09:00",
            maxTime: "17:00",
            minDate: "today",
            dateFormat: "Y-m-d H:i",
            disable: [
                function(date) {
                    return (date.getDay() === 0 || date.getDay() === 6);
                }
            ],
            time_24hr: true
        });

        
        document.getElementById('doctor').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const doctorInfo = document.getElementById('doctorInfo');
            
            if (this.value) {
                const fee = selectedOption.dataset.fee;
                const hours = selectedOption.dataset.hours;
                const specialization = selectedOption.dataset.specialization;
                const experience = selectedOption.dataset.experience;
                const rating = parseFloat(selectedOption.dataset.rating);

                const stars = '⭐'.repeat(Math.round(rating));
                
                doctorInfo.innerHTML = `
                    <div class="doctor-card">
                        <div class="rating-stars">${stars} (${rating.toFixed(1)})</div>
                        <p><strong>Specialization:</strong> ${specialization}</p>
                        <p><strong>Experience:</strong> ${experience} years</p>
                        <p><strong>Consultation Fee:</strong> $${fee}</p>
                        <p><strong>Available Hours:</strong> ${hours}</p>
                    </div>
                `;
            } else {
                doctorInfo.innerHTML = '';
            }
        });

        
        async function scheduleAppointment(event) {
            event.preventDefault();
            
            const form = event.target;
            const submitBtn = document.getElementById('submitBtn');
            const loadingSpinner = document.getElementById('loadingSpinner');
            
   
            submitBtn.disabled = true;
            loadingSpinner.style.display = 'inline-block';
            
            try {
                const formData = new FormData(form);
                const response = await fetch('schedule_appointment.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Appointment scheduled successfully!');
                    window.location.href = 'patient_portal.php';
                } else {
                    alert(result.message || 'Failed to schedule appointment');
                }
            } catch (error) {
                alert('An error occurred. Please try again.');
                console.error('Error:', error);
            } finally {
        
                submitBtn.disabled = false;
                loadingSpinner.style.display = 'none';
            }
        }
    </script>
</body>
</html>
