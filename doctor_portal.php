<?php
session_start();

require_once __DIR__ . '/vendor/autoload.php';
require_once 'db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: doctor_login_page.html?error=Please login to access the portal.");
    exit();
}

$stmt_doctor = $conn->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
$stmt_doctor->bind_param("i", $_SESSION['user_id']);
$stmt_doctor->execute();
$doctor_result = $stmt_doctor->get_result();

if ($doctor_result->num_rows === 0) {
    die("Doctor profile not found.");
}

$doctor_data = $doctor_result->fetch_assoc();
$doctor_id = $doctor_data['doctor_id'];

$stmt_appointments = $conn->prepare("
    SELECT 
        a.appointment_id, 
        a.appointment_date, 
        a.status, 
        a.reason,
        p.patient_id,
        p.name AS patient_name,
        p.contact_number AS patient_contact
    FROM 
        appointments a
    JOIN 
        patients p ON a.patient_id = p.patient_id 
    WHERE 
        a.doctor_id = ?
    ORDER BY 
        a.appointment_date ASC
");

$stmt_appointments->bind_param("i", $doctor_id);
$stmt_appointments->execute();
$appointments_result = $stmt_appointments->get_result();

$stmt_doctor_details = $conn->prepare("
    SELECT name, specialization, availability_status, profile_photo 
    FROM doctors 
    WHERE doctor_id = ?
");
$stmt_doctor_details->bind_param("i", $doctor_id);
$stmt_doctor_details->execute();
$doctor_details = $stmt_doctor_details->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Portal - MediTouch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style/doctor_portal.css">
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
                        <a class="nav-link" href="doctor_portal.php">
                            <i class="bi bi-house-door"></i> Home
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
                <div class="profile-avatar">
                    <?php if (!empty($doctor_details['profile_photo'])): ?>
                        <img src="<?= htmlspecialchars($doctor_details['profile_photo']) ?>" 
                             alt="Profile Photo" 
                             class="img-fluid">
                    <?php else: ?>
                        <i class="bi bi-person"></i>
                    <?php endif; ?>
                    <button type="button" 
                            class="photo-upload-btn"
                            onclick="document.getElementById('photoInput').click()">
                        <i class="bi bi-camera"></i>
                    </button>
                </div>
                <div class="flex-grow-1">
                    <h4 class="welcome-text">Dr. <?= htmlspecialchars($doctor_details['name']) ?></h4>
                    <p class="text-muted mb-0"><?= htmlspecialchars($doctor_details['specialization']) ?></p>
                </div>
                <div class="text-end">
                    <label class="availability-toggle">
                        <input type="checkbox" id="availabilityToggle" 
                               <?= $doctor_details['availability_status'] === 'available' ? 'checked' : '' ?>>
                        <span class="availability-slider"></span>
                    </label>
                    <div class="mt-1">
                        <small class="text-muted" id="availabilityStatus">
                            <?= $doctor_details['availability_status'] === 'available' ? 'Available' : 'Not Available' ?>
                        </small>
                    </div>
                </div>

                
                <input type="file" 
                       id="photoInput" 
                       accept="image/*" 
                       style="display: none;" 
                       onchange="uploadProfilePhoto(this)">
            </div>
        </div>

        
        <div class="dashboard-stats">
            <div class="row">
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="bi bi-calendar-check"></i>
                        <div class="stat-value"><?= $appointments_result->num_rows ?></div>
                        <div class="stat-label">Total Appointments</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="bi bi-calendar2-week"></i>
                        <div class="stat-value"><?= $appointments_result->num_rows ?></div>
                        <div class="stat-label">Today's Appointments</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="bi bi-check-circle"></i>
                        <div class="stat-value"><?= $appointments_result->num_rows ?></div>
                        <div class="stat-label">Completed Appointments</div>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Today's Appointments</h5>
            </div>
            <div class="card-body">
                <?php if ($appointments_result->num_rows > 0): ?>
                    <?php while ($appointment = $appointments_result->fetch_assoc()): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <h6 class="mb-1"><?= htmlspecialchars($appointment['patient_name']) ?></h6>
                                        <p class="mb-0 text-muted">
                                            <?= htmlspecialchars($appointment['patient_contact']) ?>
                                        </p>
                                    </div>
                                    <div class="col-md-4">
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
                                        <div>
                                            <?php if ($appointment['status'] === 'scheduled'): ?>
                                                <a href="doctor_consultation.php?appointment_id=<?= $appointment['appointment_id'] ?>"
                                                   class="btn btn-primary">
                                                    <i class="bi bi-camera-video"></i> Start Consultation
                                                </a>
                                            <?php endif; ?>
                                            <button onclick="viewConsultationNotes(<?= $appointment['appointment_id'] ?>, '<?= htmlspecialchars($appointment['patient_name'], ENT_QUOTES) ?>')" 
                                                    class="btn btn-info">
                                                <i class="bi bi-journal-text"></i> Notes
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-calendar-x" style="font-size: 2rem; color: var(--primary-color);"></i>
                        <h6 class="mt-3">No Appointments Today</h6>
                        <p class="text-muted">You have no scheduled appointments for today</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Upcoming Appointments</h5>
            </div>
            <div class="card-body">
                <?php if ($appointments_result->num_rows > 0): ?>
                    <?php while ($appointment = $appointments_result->fetch_assoc()): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <h6 class="mb-1"><?= htmlspecialchars($appointment['patient_name']) ?></h6>
                                        <p class="mb-0 text-muted">
                                            <?= htmlspecialchars($appointment['patient_contact']) ?>
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
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-calendar-x" style="font-size: 2rem; color: var(--primary-color);"></i>
                        <h6 class="mt-3">No Upcoming Appointments</h6>
                        <p class="text-muted">You have no scheduled appointments for the future</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    
    <div class="modal fade" id="consultationNotesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Consultation Notes - <span id="patientName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="notesContent">
                        <div class="mb-3">
                            <label class="form-label">Notes:</label>
                            <div class="form-control" style="min-height: 150px; white-space: pre-wrap;" id="consultationNotes"></div>
                        </div>
                        <div id="notesMetadata" class="text-muted small"></div>
                    </div>
                    <div id="noNotesMessage" class="text-center py-4" style="display: none;">
                        <p>No consultation notes available for this appointment.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentAppointmentId = null;
        const notesModal = new bootstrap.Modal(document.getElementById('consultationNotesModal'));

        async function viewConsultationNotes(appointmentId, patientName) {
            currentAppointmentId = appointmentId;
            document.getElementById('patientName').textContent = patientName;
            
            try {
                const response = await fetch(`get_consultation_notes.php?appointment_id=${appointmentId}`);
                const data = await response.json();
                
                const notesContent = document.getElementById('notesContent');
                const noNotesMessage = document.getElementById('noNotesMessage');
                const notesDiv = document.getElementById('consultationNotes');
                const notesMetadata = document.getElementById('notesMetadata');
                
                if (data.success && data.notes) {
                    notesContent.style.display = 'block';
                    noNotesMessage.style.display = 'none';
                    notesDiv.textContent = data.notes.notes;
                    
                    const updatedAt = new Date(data.notes.updated_at || data.notes.created_at);
                    notesMetadata.textContent = `Last updated: ${updatedAt.toLocaleString()}`;
                } else {
                    notesContent.style.display = 'none';
                    noNotesMessage.style.display = 'block';
                    notesDiv.textContent = '';
                    notesMetadata.textContent = '';
                }
                
                notesModal.show();
            } catch (error) {
                alert('Failed to load consultation notes');
            }
        }

        document.getElementById('availabilityToggle').addEventListener('change', async function(e) {
            const toggle = e.target;
            const statusText = document.getElementById('availabilityStatus');
            const originalText = statusText.textContent;
            
            try {
                const formData = new FormData();
                formData.append('availability', toggle.checked);

                const response = await fetch('update_availability.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                
                if (result.success) {
                    statusText.textContent = toggle.checked ? 'Available' : 'Not Available';
                    statusText.className = toggle.checked ? 'text-success' : 'text-danger';
                } else {
                    
                    toggle.checked = !toggle.checked;
                    statusText.textContent = originalText;
                    alert(result.message || 'Failed to update availability');
                }
            } catch (error) {
               
                toggle.checked = !toggle.checked;
                statusText.textContent = originalText;
                alert('An error occurred while updating availability');
            }
        });

       
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