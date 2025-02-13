<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: patient_login_page.html?error=" . urlencode("Please login to access the portal."));
    exit();
}

$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
$patient_id = $_SESSION['user_id'];

if (!$appointment_id) {
    header("Location: patient_portal.php?error=" . urlencode("Invalid appointment."));
    exit();
}


$stmt = $conn->prepare("
    SELECT 
        a.*, 
        d.name AS doctor_name,
        d.specialization,
        p.name AS patient_name,
        d.user_id AS doctor_user_id,
        p.user_id AS patient_user_id
    FROM 
        appointments a
    JOIN 
        doctors d ON a.doctor_id = d.doctor_id
    JOIN 
        patients p ON a.patient_id = p.patient_id
    WHERE 
        a.appointment_id = ? AND p.user_id = ?
");

$stmt->bind_param("ii", $appointment_id, $patient_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: patient_portal.php?error=" . urlencode("Invalid appointment or unauthorized access."));
    exit();
}

$consultation = $result->fetch_assoc();


$stmt_prescriptions = $conn->prepare("
    SELECT * FROM prescriptions 
    WHERE appointment_id = ? 
    ORDER BY created_at DESC
");
$stmt_prescriptions->bind_param("i", $appointment_id);
$stmt_prescriptions->execute();
$prescriptions = $stmt_prescriptions->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Consultation - MediTouch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
   <link rel="stylesheet" href="style/p_consultation.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-heart-pulse"></i> MediTouch
            </a>
            <a href="patient_portal.php" class="btn btn-outline-light">
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
                        <h5><i class="bi bi-info-circle"></i> Consultation Details</h5>
                        <p><strong>Doctor:</strong> Dr. <?= htmlspecialchars($consultation['doctor_name']) ?></p>
                        <p><strong>Date:</strong> <?= htmlspecialchars(date('F j, Y g:i A', strtotime($consultation['appointment_date']))) ?></p>
                        <p><strong>Reason:</strong> <?= htmlspecialchars($consultation['reason']) ?></p>
                        <p class="mb-0">
                            <strong>Status:</strong> 
                            <span class="badge bg-success">
                                <?= htmlspecialchars($consultation['status']) ?>
                            </span>
                        </p>
                    </div>
                    <div class="chat-container">
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

 
    <div class="prescription-section mt-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-file-medical"></i> Prescriptions</h5>
            </div>
            <div class="card-body">
                <?php if ($prescriptions->num_rows > 0): ?>
                    <?php while ($prescription = $prescriptions->fetch_assoc()): ?>
                        <div class="prescription-item mb-4 p-3 border rounded">
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Medication:</strong><br>
                                    <?= htmlspecialchars($prescription['medication']) ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Dosage:</strong><br>
                                    <?= htmlspecialchars($prescription['dosage']) ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Frequency:</strong><br>
                                    <?= htmlspecialchars($prescription['frequency']) ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Duration:</strong><br>
                                    <?= htmlspecialchars($prescription['duration']) ?>
                                </div>
                            </div>
                            <div class="mt-3">
                                <strong>Notes:</strong><br>
                                <?= nl2br(htmlspecialchars($prescription['notes'])) ?>
                            </div>
                            <div class="mt-3">
                                <a href="download_prescription.php?appointment_id=<?= $appointment_id ?>" 
                                   class="btn btn-primary btn-sm" target="_blank">
                                    <i class="bi bi-download"></i> Download PDF
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        No prescriptions available yet. The doctor will generate one during or after the consultation.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <input type="hidden" id="appointmentId" value="<?= htmlspecialchars($appointment_id) ?>">
    <input type="hidden" id="userId" value="<?= htmlspecialchars($_SESSION['user_id']) ?>">
    <input type="hidden" id="doctorId" value="<?= htmlspecialchars($consultation['doctor_user_id']) ?>">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let localStream;
        let peerConnection;
        let ws;
        const wsUrl = 'ws://localhost:8080';
        const configuration = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' },
                { urls: 'stun:stun2.l.google.com:19302' },
                { urls: 'stun:stun3.l.google.com:19302' },
                { urls: 'stun:stun4.l.google.com:19302' }
            ]
        };

        
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
                        if (data.role === 'doctor') {
                            updateConnectionStatus('Doctor joined the call');
                            await startCall();
                        }
                        break;

                    case 'offer':
                        await handleOffer(data);
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

        function updateConnectionStatus(message, isError = false) {
            const statusDiv = document.getElementById('connection-status');
            statusDiv.textContent = message;
            statusDiv.className = `connection-status ${isError ? 'status-error' : 'status-connecting'}`;
        }

        async function startCall() {
            try {
                
                localStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
                        facingMode: 'user'
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
                            updateConnectionStatus('Connected to doctor', false);
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
                            updateConnectionStatus('Connecting to doctor...');
                            break;
                        case 'connected':
                            updateConnectionStatus('Connected to doctor');
                            break;
                        case 'disconnected':
                            updateConnectionStatus('Doctor disconnected', true);
                            
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
                }
            } catch (error) {
                console.error('Error restarting ICE:', error);
            }
        }

        async function handleOffer(data) {
            try {
                if (!peerConnection) {
                    await startCall();
                }

                if (peerConnection.signalingState !== 'stable') {
                    console.log('Signaling state not stable, waiting...');
                    await new Promise(resolve => setTimeout(resolve, 1000));
                }

                console.log('Setting remote description:', data.offer);
                await peerConnection.setRemoteDescription(new RTCSessionDescription(data.offer));

                console.log('Creating answer...');
                const answer = await peerConnection.createAnswer();

                console.log('Setting local description:', answer);
                await peerConnection.setLocalDescription(answer);

                console.log('Sending answer to doctor');
                ws.send(JSON.stringify({
                    type: 'answer',
                    answer: answer,
                    appointmentId: '<?= $consultation['appointment_id'] ?>'
                }));
            } catch (error) {
                console.error('Error handling offer:', error);
                updateConnectionStatus('Error handling offer', true);
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

        function joinRoom() {
            console.log('Joining room as patient');
            ws.send(JSON.stringify({
                type: 'join',
                appointmentId: '<?= $consultation['appointment_id'] ?>',
                role: 'patient',
                userId: '<?= $_SESSION['user_id'] ?>'
            }));
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
                    sender: 'Patient',
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
            messageDiv.className = `message ${message.sender === 'Patient' ? 'sent' : 'received'}`;
            
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
                cleanupCall();
                window.location.href = 'patient_portal.php';
            }
        });

        function cleanupCall() {
            if (localStream) {
                localStream.getTracks().forEach(track => track.stop());
            }
            if (peerConnection) {
                peerConnection.close();
            }
            if (ws) {
                ws.close();
            }
        }

        function downloadPrescription() {
            window.location.href = `generate_prescription.php?appointment_id=<?= $appointment_id ?>`;
        }

        
        window.addEventListener('load', () => {
            connectWebSocket();
        });

        
        window.addEventListener('beforeunload', () => {
            cleanupCall();
        });
    </script>
</body>
</html>
