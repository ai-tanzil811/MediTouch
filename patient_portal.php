<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: patient_login_page.html?error=Please login to access the portal.");
    exit();
}

$stmt = $conn->prepare("
    SELECT d.doctor_id, d.name, d.specialization, d.availability_status 
    FROM doctors d
");
$stmt->execute();
$doctors = $stmt->get_result();

$stmt_patient = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt_patient->bind_param("i", $_SESSION['user_id']);
$stmt_patient->execute();
$patient_result = $stmt_patient->get_result();
$patient_data = $patient_result->fetch_assoc();
$patient_id = $patient_data['patient_id'];

$stmt_patient_details = $conn->prepare("SELECT name, profile_photo FROM patients WHERE patient_id = ?");
$stmt_patient_details->bind_param("i", $patient_id);
$stmt_patient_details->execute();
$patient_details_result = $stmt_patient_details->get_result();
$patient_details = $patient_details_result->fetch_assoc();

$stmt_appointments = $conn->prepare("
    SELECT 
        a.appointment_id,
        a.appointment_date,
        a.status,
        a.reason,
        a.prescription_file,
        d.name AS doctor_name,
        d.specialization AS doctor_specialization
    FROM 
        appointments a
    JOIN 
        doctors d ON a.doctor_id = d.doctor_id
    WHERE 
        a.patient_id = ?
    ORDER BY 
        a.appointment_date DESC
");

$stmt_appointments->bind_param("i", $patient_id);
$stmt_appointments->execute();
$appointments = $stmt_appointments->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Portal - MediTouch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style/patient_portal.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="#">MediTouch</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="patient_portal.php">
                            <i class="bi bi-house-door"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="schedule_appointment_form.php">
                            <i class="bi bi-calendar-plus"></i> Schedule Appointment
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.html">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        
        <div class="profile-section">
            <div class="profile-header">
                <div class="profile-avatar position-relative">
                    <?php if (!empty($patient_details['profile_photo'])): ?>
                        <img src="<?= htmlspecialchars($patient_details['profile_photo']) ?>" 
                             alt="Profile Photo" 
                             class="img-fluid rounded-circle"
                             style="width: 80px; height: 80px; object-fit: cover;">
                    <?php else: ?>
                        <i class="bi bi-person"></i>
                    <?php endif; ?>
                    <button type="button" 
                            class="btn btn-sm btn-primary position-absolute bottom-0 end-0" 
                            style="border-radius: 50%; width: 32px; height: 32px; padding: 0;"
                            onclick="document.getElementById('photoInput').click()">
                        <i class="bi bi-camera"></i>
                    </button>
                </div>
                <div>
                    <h4 class="welcome-text">Welcome, <?= htmlspecialchars($patient_details['name']) ?>!</h4>
                    <p class="text-muted mb-0">Patient ID: <?= htmlspecialchars($patient_id) ?></p>
                </div>
            </div>

          
            <input type="file" 
                   id="photoInput" 
                   accept="image/*" 
                   style="display: none;" 
                   onchange="uploadProfilePhoto(this)">
        </div>

        
        <div class="dashboard-stats">
            <div class="row">
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="bi bi-calendar-check"></i>
                        <div class="stat-value"><?= $appointments->num_rows ?></div>
                        <div class="stat-label">Total Appointments</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="bi bi-calendar2-week"></i>
                        <div class="stat-value"><?= $appointments->num_rows ?></div>
                        <div class="stat-label">Upcoming Appointments</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="bi bi-check-circle"></i>
                        <div class="stat-value"><?= $appointments->num_rows ?></div>
                        <div class="stat-label">Completed Appointments</div>
                    </div>
                </div>
            </div>
        </div>

  
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4>Your Appointments</h4>
                    <a href="schedule_appointment_form.php" class="btn btn-primary">Schedule New Appointment</a>
                </div>

                <?php if ($appointments->num_rows > 0): ?>
                    <?php while ($appointment = $appointments->fetch_assoc()): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <h6 class="mb-1">Dr. <?= htmlspecialchars($appointment['doctor_name']) ?></h6>
                                        <p class="mb-0 text-muted">
                                            <?= htmlspecialchars($appointment['doctor_specialization']) ?>
                                        </p>
                                    </div>
                                    <div class="col-md-4">
                                        <p class="mb-1">
                                            <i class="bi bi-calendar-event"></i>
                                            <?= date('F j, Y', strtotime($appointment['appointment_date'])) ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="bi bi-clock"></i>
                                            <?= date('g:i A', strtotime($appointment['appointment_date'])) ?>
                                        </p>
                                        <p class="mb-0">
                                            <i class="bi bi-journal-text"></i>
                                            <?= htmlspecialchars($appointment['reason']) ?>
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-md-end">
                                        <span class="appointment-status status-<?= strtolower($appointment['status']) ?> mb-2 d-inline-block">
                                            <?= ucfirst($appointment['status']) ?>
                                        </span>
                                        <?php if ($appointment['status'] === 'scheduled'): ?>
                                            <div class="mt-2">
                                                <a href="patient_consultation.php?appointment_id=<?= $appointment['appointment_id'] ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="bi bi-camera-video"></i> Join Consultation
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($appointment['prescription_file'])): ?>
    <div class="mt-2">
        <a href="download_prescription.php?appointment_id=<?= $appointment['appointment_id'] ?>" 
           class="btn btn-primary btn-sm">
            <i class="bi bi-file-earmark-medical"></i> Download Prescription
        </a>
    </div>
<?php endif; ?>

                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="empty-state">
                            <i class="bi bi-calendar-x" style="font-size: 2rem; color: var(--primary-color);"></i>
                            <h5 class="mt-3">No Appointments Found</h5>
                            <p class="text-muted">Schedule your first appointment to get started</p>
                            <a href="schedule_appointment_form.php" class="btn btn-primary">
                                Schedule Now
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        async function uploadProfilePhoto(input) {
            if (!input.files || !input.files[0]) return;

            const file = input.files[0];
            const formData = new FormData();
            formData.append('photo', file);

            try {
                const response = await fetch('upload_profile_photo.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                
                if (result.success) {
                    
                    location.reload();
                } else {
                    alert(result.message || 'Failed to upload photo');
                }
            } catch (error) {
                alert('An error occurred while uploading the photo');
            }
        }
    </script>
</body>
</html>