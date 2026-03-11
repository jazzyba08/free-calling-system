@extends('layouts.app')

@section('title', 'Call with ' . $otherUser->name)

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" 
                             style="width: 50px; height: 50px;">
                            <span class="text-white fw-bold">
                                {{ strtoupper(substr($otherUser->name, 0, 1)) }}
                            </span>
                        </div>
                        <div class="ms-3">
                            <h4 class="mb-0">{{ $otherUser->name }}</h4>
                            <small class="text-muted" id="callStatus">{{ $call->status }}</small>
                        </div>
                    </div>
                    <div id="callTimer" class="h5 mb-0">00:00</div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="row">
                    <!-- Remote Video (Other User) -->
                    <div class="col-md-8 mb-3">
                        <div class="position-relative bg-dark rounded" style="height: 400px;">
                            <video id="remoteVideo" class="w-100 h-100 rounded" autoplay playsinline></video>
                            <div class="position-absolute top-50 start-50 translate-middle text-white" id="remoteWaiting">
                                <h5>Waiting for {{ $otherUser->name }} to join...</h5>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Local Video (Self) -->
                    <div class="col-md-4 mb-3">
                        <div class="position-relative bg-dark rounded" style="height: 200px;">
                            <video id="localVideo" class="w-100 h-100 rounded" autoplay playsinline muted></video>
                            <div class="position-absolute bottom-0 start-0 p-2 text-white">
                                <small>You</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Call Controls -->
                <div class="row mt-4">
                    <div class="col-12 text-center">
                        <div class="btn-group btn-group-lg">
                            <button class="btn btn-outline-secondary" id="muteBtn" title="Mute">
                                <i class="bi bi-mic"></i>
                            </button>
                            <button class="btn btn-outline-secondary" id="videoBtn" title="Toggle Video">
                                <i class="bi bi-camera-video"></i>
                            </button>
                            <button class="btn btn-danger" id="endCallBtn" title="End Call">
                                <i class="bi bi-telephone-x"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Call Data -->
<input type="hidden" id="callId" value="{{ $call->id }}">
<input type="hidden" id="otherUserId" value="{{ $otherUser->id }}">
<input type="hidden" id="callType" value="{{ $call->type }}">
<input type="hidden" id="callStatus" value="{{ $call->status }}">
<input type="hidden" id="currentUserId" value="{{ Auth::id() }}">

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    video {
        background-color: #2d2d2d;
        object-fit: cover;
    }
    #remoteWaiting {
        left: 50%;
        transform: translateX(-50%);
        text-align: center;
        white-space: nowrap;
    }
    .btn-group .btn {
        margin: 0 5px;
        border-radius: 50% !important;
        width: 60px;
        height: 60px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .btn i {
        font-size: 1.5rem;
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get call data
        const callId = document.getElementById('callId').value;
        const otherUserId = document.getElementById('otherUserId').value;
        const callType = document.getElementById('callType').value;
        const currentUserId = document.getElementById('currentUserId').value;
        let callStatus = document.getElementById('callStatus').value;
        
        // DOM elements
        const localVideo = document.getElementById('localVideo');
        const remoteVideo = document.getElementById('remoteVideo');
        const remoteWaiting = document.getElementById('remoteWaiting');
        const callStatusEl = document.getElementById('callStatus');
        const muteBtn = document.getElementById('muteBtn');
        const videoBtn = document.getElementById('videoBtn');
        const endCallBtn = document.getElementById('endCallBtn');
        const callTimer = document.getElementById('callTimer');
        
        // WebRTC variables
        let localStream = null;
        let peerConnection = null;
        let isMuted = false;
        let isVideoEnabled = true;
        let callStartTime = null;
        let timerInterval = null;
        let pollingInterval = null;
        let isCaller = (callStatus === 'ringing'); // If status is ringing, current user is caller
        
        console.log('Call page loaded:', { 
            callId, 
            otherUserId, 
            callType, 
            currentUserId, 
            callStatus, 
            isCaller 
        });
        
        // WebRTC Configuration
        const configuration = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' },
                { urls: 'stun:stun2.l.google.com:19302' }
            ]
        };
        
        // Get CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        
        if (!csrfToken) {
            console.error('CSRF token not found');
        }
        
        // Initialize - start local stream
        startLocalStream();
        
        // Start local stream (camera/mic)
        async function startLocalStream() {
            try {
                const constraints = {
                    audio: true,
                    video: callType === 'video'
                };
                
                console.log('Requesting media with constraints:', constraints);
                localStream = await navigator.mediaDevices.getUserMedia(constraints);
                localVideo.srcObject = localStream;
                console.log('Local stream started');
                
                // Create peer connection after stream is ready
                createPeerConnection();
                
                // Start polling for signaling data
                startPolling();
                
            } catch (error) {
                console.error('Error accessing media devices:', error);
                alert('Could not access camera/microphone. Please check permissions.');
                endCall();
            }
        }
        
        // Create Peer Connection
        function createPeerConnection() {
            console.log('Creating peer connection');
            peerConnection = new RTCPeerConnection(configuration);
            
            // Add local stream tracks to connection
            localStream.getTracks().forEach(track => {
                peerConnection.addTrack(track, localStream);
                console.log('Added track:', track.kind);
            });
            
            // Handle ICE candidates
            peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    console.log('ICE candidate generated');
                    sendIceCandidate(event.candidate);
                }
            };
            
            // Handle remote stream
            peerConnection.ontrack = (event) => {
                console.log('Remote stream received');
                remoteVideo.srcObject = event.streams[0];
                remoteWaiting.style.display = 'none';
            };
            
            // Handle connection state changes
            peerConnection.onconnectionstatechange = () => {
                console.log('Connection state:', peerConnection.connectionState);
                if (peerConnection.connectionState === 'connected') {
                    callStatus = 'ongoing';
                    callStatusEl.textContent = 'ongoing';
                    startTimer();
                }
            };
            
            peerConnection.oniceconnectionstatechange = () => {
                console.log('ICE connection state:', peerConnection.iceConnectionState);
            };
            
            peerConnection.onsignalingstatechange = () => {
                console.log('Signaling state:', peerConnection.signalingState);
            };
            
            // If caller, create offer after connection is created
            if (isCaller) {
                setTimeout(() => createOffer(), 1000);
            }
        }
        
        // Create Offer (Caller)
        async function createOffer() {
            try {
                console.log('Creating offer as caller');
                const offer = await peerConnection.createOffer({
                    offerToReceiveAudio: true,
                    offerToReceiveVideo: callType === 'video'
                });
                await peerConnection.setLocalDescription(offer);
                console.log('Offer created:', offer);
                
                // Send offer to server
                const response = await fetch('/call/offer', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        call_id: callId,
                        offer: peerConnection.localDescription
                    })
                });
                
                const data = await response.json();
                console.log('Offer sent response:', data);
                
            } catch (error) {
                console.error('Error creating offer:', error);
            }
        }
        
        // Create Answer (Receiver)
        async function createAnswer(offer) {
            try {
                console.log('Creating answer as receiver with offer:', offer);
                await peerConnection.setRemoteDescription(new RTCSessionDescription(offer));
                console.log('Remote description set');
                
                const answer = await peerConnection.createAnswer();
                await peerConnection.setLocalDescription(answer);
                console.log('Answer created:', answer);
                
                // Send answer to server
                const response = await fetch('/call/answer', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        call_id: callId,
                        answer: peerConnection.localDescription
                    })
                });
                
                const data = await response.json();
                console.log('Answer sent response:', data);
                
            } catch (error) {
                console.error('Error creating answer:', error);
            }
        }
        
        // Send ICE candidate
        function sendIceCandidate(candidate) {
            console.log('Sending ICE candidate');
            fetch('/call/ice-candidate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    call_id: callId,
                    candidate: candidate
                })
            }).catch(error => console.error('Error sending ICE candidate:', error));
        }
        
        // Start polling for signaling data
        function startPolling() {
            console.log('Starting signaling poll');
            pollingInterval = setInterval(pollSignaling, 2000);
        }
        
        // Poll for signaling data
        function pollSignaling() {
            fetch(`/call/signaling/${callId}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                console.log('Signaling data received:', data);
                
                // Handle offer (for receiver)
                if (data.offer && !isCaller && peerConnection.signalingState === 'stable') {
                    console.log('Received offer as receiver');
                    createAnswer(data.offer);
                }
                
                // Handle answer (for caller)
                if (data.answer && isCaller && peerConnection.signalingState === 'have-local-offer') {
                    console.log('Received answer as caller');
                    peerConnection.setRemoteDescription(new RTCSessionDescription(data.answer))
                        .catch(error => console.error('Error setting remote description:', error));
                }
                
                // Handle ICE candidate
                if (data.candidate && peerConnection.remoteDescription) {
                    console.log('Adding ICE candidate');
                    peerConnection.addIceCandidate(new RTCIceCandidate(data.candidate))
                        .catch(error => console.error('Error adding ICE candidate:', error));
                }
                
                // Handle call status changes
                if (data.call_status && data.call_status !== callStatus) {
                    callStatus = data.call_status;
                    callStatusEl.textContent = callStatus;
                    
                    if (callStatus === 'ended' || callStatus === 'rejected') {
                        endCall();
                    }
                }
            })
            .catch(error => console.debug('Polling error:', error));
        }
        
        // Start call timer
        function startTimer() {
            callStartTime = Date.now();
            timerInterval = setInterval(() => {
                const seconds = Math.floor((Date.now() - callStartTime) / 1000);
                const mins = Math.floor(seconds / 60);
                const secs = seconds % 60;
                callTimer.textContent = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            }, 1000);
        }
        
        // Mute toggle
        muteBtn.addEventListener('click', function() {
            if (localStream) {
                const audioTracks = localStream.getAudioTracks();
                audioTracks.forEach(track => {
                    track.enabled = isMuted;
                });
                isMuted = !isMuted;
                this.innerHTML = isMuted ? '<i class="bi bi-mic-mute"></i>' : '<i class="bi bi-mic"></i>';
                console.log('Mute toggled:', isMuted ? 'muted' : 'unmuted');
            }
        });
        
        // Video toggle
        videoBtn.addEventListener('click', function() {
            if (localStream) {
                const videoTracks = localStream.getVideoTracks();
                videoTracks.forEach(track => {
                    track.enabled = !isVideoEnabled;
                });
                isVideoEnabled = !isVideoEnabled;
                this.innerHTML = isVideoEnabled ? '<i class="bi bi-camera-video"></i>' : '<i class="bi bi-camera-video-off"></i>';
                console.log('Video toggled:', isVideoEnabled ? 'enabled' : 'disabled');
            }
        });
        
        // End call
        endCallBtn.addEventListener('click', endCall);
        
        function endCall() {
            console.log('Ending call');
            
            // Stop all tracks
            if (localStream) {
                localStream.getTracks().forEach(track => {
                    track.stop();
                });
            }
            
            // Close peer connection
            if (peerConnection) {
                peerConnection.close();
            }
            
            // Stop timer
            if (timerInterval) {
                clearInterval(timerInterval);
            }
            
            // Stop polling
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }
            
            // Notify server
            fetch('/call/end', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ call_id: callId })
            }).finally(() => {
                // Redirect to dashboard after 1 second
                setTimeout(() => {
                    window.location.href = '/dashboard';
                }, 1000);
            });
        }
        
        // Handle page unload
        window.addEventListener('beforeunload', function() {
            if (callStatus === 'ongoing' || callStatus === 'ringing') {
                endCall();
            }
        });
        
        // Handle visibility change (tab switch)
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                console.log('Tab became visible');
            }
        });
    });
</script>
@endpush
@endsection