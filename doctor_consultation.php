<?php
session_start();
require_once 'db_connection.php';

$server_ip = $_SERVER['SERVER_ADDR'] === '::1' ? '127.0.0.1' : $_SERVER['SERVER_ADDR'];
$doctor_id = $_SESSION['user_id']; define('SIGNALING_SERVER', 'ws://localhost:8080');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: doctor_login_page.html?error=" . urlencode("Unauthorized access."));
    exit();
}

$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
$doctor_id = $_SESSION['user_id']; 

if (!$appointment_id) {
    header("Location: doctor_portal.php?error=" . urlencode("Invalid appointment."));
    exit();
}

$stmt = $conn->prepare("
    SELECT 
        a.*, 
        p.name AS patient_name, 
        d.name AS doctor_name,
        p.medical_history,
        p.user_id AS patient_user_id,
        d.user_id AS doctor_user_id
    FROM 
        appointments a
    JOIN 
        patients p ON a.patient_id = p.patient_id
    JOIN 
        doctors d ON a.doctor_id = d.doctor_id
    WHERE 
        a.appointment_id = ? AND d.user_id = ?
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("ii", $appointment_id, $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: doctor_portal.php?error=" . urlencode("Invalid appointment or unauthorized access."));
    exit();
}

$consultation = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Consultation - MediTouch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style/d_consultation.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-heart-pulse"></i> MediTouch
            </a>
            <a href="doctor_portal.php" class="btn btn-outline-light">
                <i class="bi bi-arrow-left"></i> Back to Portal
            </a>
        </div>
    </nav>

    <div class="main-container">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <div class="video-container">
                        <div class="video-wrapper remote-video">
                            <video id="remoteVideo" autoplay playsinline></video>
                        </div>
                        <div class="video-wrapper local-video">
                            <video id="localVideo" autoplay playsinline muted></video>
                        </div>
                    </div>
                    <div id="connection-status" class="connection-status"></div>
                    <div class="video-controls">
                        <button id="toggleVideo"><i class="bi bi-camera-video"></i> Video</button>
                        <button id="toggleAudio"><i class="bi bi-mic"></i> Audio</button>
                        <button id="endCall" class="end-call"><i class="bi bi-telephone-x"></i> End Call</button>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="consultation-info">
                        <h5><i class="bi bi-info-circle"></i> Patient Information</h5>
                        <p><strong>Patient:</strong> <?= htmlspecialchars($consultation['patient_name']) ?></p>
                        <p><strong>Date:</strong> <?= htmlspecialchars(date('F j, Y g:i A', strtotime($consultation['appointment_date']))) ?></p>
                        <p><strong>Reason:</strong> <?= htmlspecialchars($consultation['reason']) ?></p>
                        <p class="mb-0">
                            <strong>Status:</strong> 
                            <span class="badge bg-success">
                                <?= htmlspecialchars($consultation['status']) ?>
                            </span>
                        </p>
                    </div>
                    <div class="notes-container">
                        <h5><i class="bi bi-journal-text"></i> Consultation Notes</h5>
                        <textarea id="consultation-notes" class="form-control" 
                                placeholder="Enter your consultation notes here..."></textarea>
                        <div class="text-end mt-3">
                            <button class="btn btn-primary" onclick="saveNotes()">
                                <i class="bi bi-save"></i> Save Notes
                            </button>
                        </div>
                    </div>
                    <div class="prescription-container">
                        <h5><i class="bi bi-file-medical"></i> Prescription</h5>
                        <button class="btn btn-primary w-100 mt-3" data-bs-toggle="modal" data-bs-target="#prescriptionModal">
                            <i class="bi bi-plus-circle"></i> Write Prescription
                        </button>
                    </div>
                    <div class="chat-container mt-4">
                        <div class="chat-header">
                            <i class="bi bi-chat-dots"></i> Chat
                        </div>
                        <div id="chat-messages" class="chat-messages"></div>
                        <div class="chat-input">
                            <form id="chat-form">
                                <div class="input-group">
                                    <input type="text" id="chat-input" class="form-control" placeholder="Type your message...">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-send"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="modal fade" id="prescriptionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Write Prescription</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="prescriptionForm">
                        <div id="prescription-list">
                            <div class="prescription-item mb-3">
                                <div class="row">
                                    <div class="col-md-3">
                                        <input type="text" class="form-control medication" placeholder="Medication" required>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control dosage" placeholder="Dosage" required>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control frequency" placeholder="Frequency" required>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="text" class="form-control duration" placeholder="Duration" required>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-danger btn-sm" onclick="removePrescriptionItem(this)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary mb-3" onclick="addPrescriptionItem()">
                            Add Medication
                        </button>
                        <div class="form-group">
                            <label for="prescriptionNotes">Additional Notes</label>
                            <textarea class="form-control" id="prescriptionNotes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="savePrescription()">Save Prescription</button>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" id="appointmentId" value="<?= htmlspecialchars($appointment_id) ?>">
    <input type="hidden" id="userId" value="<?= htmlspecialchars($_SESSION['user_id']) ?>">
    <input type="hidden" id="patientId" value="<?= htmlspecialchars($consultation['patient_user_id']) ?>">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let localStream;
        let peerConnection;
        let ws;
        const wsUrl = '<?= SIGNALING_SERVER ?>';
        const configuration = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' },
                { urls: 'stun:stun2.l.google.com:19302' },
                { urls: 'stun:stun3.l.google.com:19302' },
                { urls: 'stun:stun4.l.google.com:19302' }
            ]
        };

        async function startCall() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        width: { min: 320, ideal: 1280 },
                        height: { min: 240, ideal: 720 },
                        facingMode: { ideal: 'user' },
                        aspectRatio: { ideal: 1.7777777778 }
                    },
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true
                    }
                });
                
                const localVideo = document.getElementById('localVideo');
                localVideo.srcObject = localStream;
                localVideo.playsInline = true;
                
               
                try {
                    await localVideo.play();
                    console.log('Local video playing');
                } catch (e) {
                    console.error('Error playing local video:', e);
                }

                await createPeerConnection();

                
                localStream.getTracks().forEach(track => {
                    console.log('Adding track to peer connection:', track.kind);
                    peerConnection.addTrack(track, localStream);
                });

                
                await createAndSendOffer();

            } catch (error) {
                console.error('Error accessing media devices:', error);
                updateConnectionStatus('Error accessing camera/microphone. Please ensure permissions are granted.', true);
            }
        }

        async function createPeerConnection() {
            try {
                if (peerConnection) {
                    console.log('Cleaning up old peer connection');
                    peerConnection.close();
                }

                peerConnection = new RTCPeerConnection(configuration);
                console.log('Created peer connection with config:', configuration);

                peerConnection.onicecandidate = (event) => {
                    if (event.candidate) {
                        console.log('Sending ICE candidate:', event.candidate);
                        ws.send(JSON.stringify({
                            type: 'ice-candidate',
                            candidate: event.candidate,
                            appointmentId: '<?= $consultation['appointment_id'] ?>'
                        }));
                    }
                };

                peerConnection.ontrack = (event) => {
                    console.log('Received remote track:', event.track.kind);
                    const [remoteStream] = event.streams;
                    if (remoteStream) {
                        const remoteVideo = document.getElementById('remoteVideo');
                        remoteVideo.srcObject = remoteStream;
                        remoteVideo.playsInline = true;
                        
                      
                        remoteVideo.play().catch(e => {
                            console.error('Error playing remote video:', e);
                           
                            remoteVideo.muted = true;
                            return remoteVideo.play();
                        }).then(() => {
                            console.log('Remote video playing');
                            updateConnectionStatus('Connected to patient', false);
                        });

                        
                        remoteStream.onaddtrack = () => console.log('Remote stream: track added');
                        remoteStream.onremovetrack = () => console.log('Remote stream: track removed');
                        
                       
                        remoteVideo.onloadedmetadata = () => console.log('Remote video: metadata loaded');
                        remoteVideo.onresize = () => console.log('Remote video: resized');
                    }
                };

                peerConnection.oniceconnectionstatechange = () => {
                    console.log('ICE connection state:', peerConnection.iceConnectionState);
                    switch(peerConnection.iceConnectionState) {
                        case 'checking':
                            updateConnectionStatus('Connecting to patient...');
                            break;
                        case 'connected':
                            updateConnectionStatus('Connected to patient');
                            break;
                        case 'disconnected':
                            updateConnectionStatus('Patient disconnected', true);
                            
                            restartIce();
                            break;
                        case 'failed':
                            updateConnectionStatus('Connection failed', true);
                           
                            restartIce();
                            break;
                    }
                };

                peerConnection.onconnectionstatechange = () => {
                    console.log('Connection state:', peerConnection.connectionState);
                    if (peerConnection.connectionState === 'failed') {
                        
                        restartIce();
                    }
                };

                peerConnection.onnegotiationneeded = async () => {
                    console.log('Negotiation needed');
                    try {
                        await createAndSendOffer();
                    } catch (err) {
                        console.error('Error during negotiation:', err);
                    }
                };

            } catch (error) {
                console.error('Error creating peer connection:', error);
                updateConnectionStatus('Error creating peer connection', true);
            }
        }

        async function restartIce() {
            try {
                if (peerConnection) {
                    console.log('Restarting ICE connection');
                    await peerConnection.restartIce();
                    await createAndSendOffer();
                }
            } catch (error) {
                console.error('Error restarting ICE:', error);
            }
        }

        async function createAndSendOffer() {
            try {
                if (!peerConnection) {
                    console.error('No peer connection exists');
                    return;
                }

                if (peerConnection.signalingState !== 'stable') {
                    console.log('Signaling state not stable, waiting...');
                    await new Promise(resolve => setTimeout(resolve, 1000));
                }

                console.log('Creating offer...');
                const offer = await peerConnection.createOffer({
                    offerToReceiveAudio: true,
                    offerToReceiveVideo: true,
                    iceRestart: true
                });

                console.log('Setting local description:', offer);
                await peerConnection.setLocalDescription(offer);

                console.log('Sending offer to patient');
                ws.send(JSON.stringify({
                    type: 'offer',
                    offer: offer,
                    appointmentId: '<?= $consultation['appointment_id'] ?>'
                }));
            } catch (error) {
                console.error('Error creating offer:', error);
                updateConnectionStatus('Error creating offer', true);
            }
        }

        async function handleAnswer(data) {
            try {
                if (!peerConnection) {
                    console.error('No peer connection exists');
                    return;
                }

                console.log('Setting remote description:', data.answer);
                await peerConnection.setRemoteDescription(new RTCSessionDescription(data.answer));
                console.log('Remote description set successfully');
                updateConnectionStatus('Connected to patient');
            } catch (error) {
                console.error('Error handling answer:', error);
                updateConnectionStatus('Error handling answer', true);
            }
        }

        async function handleIceCandidate(data) {
            try {
                if (!peerConnection) {
                    console.error('No peer connection exists');
                    return;
                }

                if (data.candidate) {
                    console.log('Adding ICE candidate:', data.candidate);
                    await peerConnection.addIceCandidate(new RTCIceCandidate(data.candidate));
                    console.log('ICE candidate added successfully');
                }
            } catch (error) {
                console.error('Error handling ICE candidate:', error);
            }
        }
        function connectWebSocket() {
            ws = new WebSocket(wsUrl);

            ws.onopen = () => {
                console.log('Connected to signaling server');
                joinRoom();
                updateConnectionStatus('Connected to signaling server');
            };

            ws.onmessage = async (event) => {
                const data = JSON.parse(event.data);
                console.log('Received message:', data);

                switch (data.type) {
                    case 'user-joined':
                        if (data.role === 'patient') {
                            updateConnectionStatus('Patient joined the call');
                            await startCall();
                        }
                        break;

                    case 'start-call':
                        await startCall();
                        break;

                    case 'answer':
                        await handleAnswer(data);
                        break;

                    case 'ice-candidate':
                        await handleIceCandidate(data);
                        break;

                    case 'chat':
                        appendChatMessage(data);
                        break;

                    case 'ping':
                        ws.send(JSON.stringify({ type: 'pong' }));
                        break;

                    case 'user-left':
                        if (data.role === 'patient') {
                            updateConnectionStatus('Patient left the call', true);
                        }
                        break;
                }
            };

            ws.onerror = (error) => {
                console.error('WebSocket error:', error);
                updateConnectionStatus('Connection error', true);
            };

            ws.onclose = () => {
                console.log('WebSocket connection closed');
                updateConnectionStatus('Connection closed', true);

                setTimeout(connectWebSocket, 5000);
            };
        }

        function joinRoom() {
            console.log('Joining room as doctor');
            ws.send(JSON.stringify({
                type: 'join',
                appointmentId: '<?= $consultation['appointment_id'] ?>',
                role: 'doctor',
                userId: '<?= $_SESSION['user_id'] ?>'
            }));
        }

        function updateConnectionStatus(message, isError = false) {
            const statusDiv = document.getElementById('connection-status');
            statusDiv.textContent = message;
            statusDiv.className = `connection-status ${isError ? 'status-error' : 'status-connecting'}`;
        }


        document.getElementById('chat-form').addEventListener('submit', function(event) {
            event.preventDefault();
            const input = document.getElementById('chat-input');
            const message = input.value.trim();
            
            if (message) {
                const chatMessage = {
                    type: 'chat',
                    appointmentId: '<?= $consultation['appointment_id'] ?>',
                    message: message,
                    sender: 'Doctor',
                    timestamp: new Date().toISOString()
                };
                
                ws.send(JSON.stringify(chatMessage));
                appendChatMessage(chatMessage);
                input.value = '';
            }
        });

        function appendChatMessage(message) {
            const messagesDiv = document.getElementById('chat-messages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${message.sender === 'Doctor' ? 'sent' : 'received'}`;
            
            const time = new Date(message.timestamp).toLocaleTimeString();
            messageDiv.innerHTML = `
                ${message.message}
                <div class="time">${time}</div>
            `;
            
            messagesDiv.appendChild(messageDiv);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }


        document.getElementById('toggleVideo').addEventListener('click', function() {
            const videoTrack = localStream?.getVideoTracks()[0];
            if (videoTrack) {
                videoTrack.enabled = !videoTrack.enabled;
                this.innerHTML = videoTrack.enabled ? 
                    '<i class="bi bi-camera-video"></i> Video' : 
                    '<i class="bi bi-camera-video-off"></i> Video Off';
            }
        });

        document.getElementById('toggleAudio').addEventListener('click', function() {
            const audioTrack = localStream?.getAudioTracks()[0];
            if (audioTrack) {
                audioTrack.enabled = !audioTrack.enabled;
                this.innerHTML = audioTrack.enabled ? 
                    '<i class="bi bi-mic"></i> Audio' : 
                    '<i class="bi bi-mic-mute"></i> Audio Off';
            }
        });

        document.getElementById('endCall').addEventListener('click', function() {
            if (confirm('Are you sure you want to end the call?')) {
                if (localStream) {
                    localStream.getTracks().forEach(track => track.stop());
                }
                if (peerConnection) {
                    peerConnection.close();
                }
                if (ws) {
                    ws.close();
                }
                window.location.href = 'doctor_portal.php';
            }
        });


        async function saveNotes() {
            const notes = document.getElementById('consultation-notes').value.trim();
            const appointmentId = document.getElementById('appointmentId').value;
            
            if (!notes) {
                alert('Please enter consultation notes before saving.');
                return;
            }
            
            try {
                const response = await fetch('save_consultation_notes.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        appointment_id: appointmentId,
                        notes: notes
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Consultation notes saved successfully!');
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                alert('Failed to save notes: ' + error.message);
            }
        }

        function addPrescriptionItem() {
            const prescriptionList = document.getElementById('prescription-list');
            const itemDiv = document.createElement('div');
            itemDiv.className = 'prescription-item mb-3';
            itemDiv.innerHTML = `
                <div class="row">
                    <div class="col-md-3">
                        <input type="text" class="form-control medication" placeholder="Medication" required>
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control dosage" placeholder="Dosage" required>
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control frequency" placeholder="Frequency" required>
                    </div>
                    <div class="col-md-2">
                        <input type="text" class="form-control duration" placeholder="Duration" required>
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-danger btn-sm" onclick="removePrescriptionItem(this)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            prescriptionList.appendChild(itemDiv);
        }

        function removePrescriptionItem(button) {
            button.closest('.prescription-item').remove();
        }

        function savePrescription() {
            const prescriptionItems = document.querySelectorAll('.prescription-item');
            const prescriptions = [];

            prescriptionItems.forEach(item => {
                prescriptions.push({
                    medication: item.querySelector('.medication').value,
                    dosage: item.querySelector('.dosage').value,
                    frequency: item.querySelector('.frequency').value,
                    duration: item.querySelector('.duration').value
                });
            });

            const notes = document.getElementById('prescriptionNotes').value;
            const appointmentId = <?= $consultation['appointment_id'] ?>;

            fetch('save_prescription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    appointment_id: appointmentId,
                    prescriptions: prescriptions,
                    notes: notes
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Prescription saved successfully!');
                    $('#prescriptionModal').modal('hide');
                } else {
                    alert('Error saving prescription: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving prescription. Please try again.');
            });
        }

        window.addEventListener('load', () => {
            connectWebSocket();
        });


        window.addEventListener('beforeunload', () => {
            if (localStream) {
                localStream.getTracks().forEach(track => track.stop());
            }
            if (peerConnection) {
                peerConnection.close();
            }
            if (ws) {
                ws.close();
            }
        });
    </script>
</body>
</html>
