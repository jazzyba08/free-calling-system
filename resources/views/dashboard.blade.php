@extends('layouts.app')

@section('title', 'Dashboard - Video Chat System')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4>Welcome, {{ Auth::user()->name }}!</h4>
            </div>
            <div class="card-body">
                <h5>Available Users</h5>
                
                @if($users->count() > 0)
                    <div class="row mt-4">
                        @foreach($users as $user)
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-3">
                                            <!-- User Avatar -->
                                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" 
                                                 style="width: 50px; height: 50px;">
                                                <span class="text-white fw-bold">
                                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                                </span>
                                            </div>
                                            <div class="ms-3">
                                                <h6 class="mb-0">{{ $user->name }}</h6>
                                                <small class="text-muted">
                                                    {{ $user->email }}
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between">
                                            <!-- CHAT BUTTON - This is what you need -->
                                            <a href="{{ route('chat.index', $user->id) }}" 
                                               class="btn btn-primary btn-sm">
                                                <i class="bi bi-chat"></i> Chat
                                            </a>
                                            <button class="btn btn-success btn-sm call-btn" 
                                                    data-user-id="{{ $user->id }}">
                                                <i class="bi bi-telephone"></i> Call
                                            </button>
                                            <button class="btn btn-info btn-sm video-call-btn"
                                                    data-user-id="{{ $user->id }}">
                                                <i class="bi bi-camera-video"></i> Video
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="alert alert-info">
                        <strong>No other users found.</strong> 
                        <p class="mt-2 mb-0">Share this platform with friends to start chatting!</p>
                        <hr>
                        <p class="mb-0">Quick tip: 
                            <a href="{{ route('register') }}" target="_blank">Register another user</a> 
                            in a different browser or incognito window.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('styles')
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    .card {
        transition: transform 0.2s;
    }
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .btn {
        transition: all 0.3s;
    }
    .btn:hover {
        transform: scale(1.05);
    }
    .btn i {
        margin-right: 5px;
    }
</style>
@endpush
@push('scripts')
<script>
    // Call button functionality
    document.querySelectorAll('.call-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.dataset.userId;
            
            // Check if browser supports WebRTC
            if (!navigator.mediaDevices || !window.RTCPeerConnection) {
                alert('Your browser does not support calling. Please use Chrome, Firefox, or Edge.');
                return;
            }
            
            // Start voice call
            window.location.href = `/call/${userId}?type=voice`;
        });
    });

    // Video call button functionality
    document.querySelectorAll('.video-call-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.dataset.userId;
            
            // Check if browser supports WebRTC
            if (!navigator.mediaDevices || !window.RTCPeerConnection) {
                alert('Your browser does not support calling. Please use Chrome, Firefox, or Edge.');
                return;
            }
            
            // Start video call
            window.location.href = `/call/${userId}?type=video`;
        });
    });

    // ============================================
    // CHECK FOR INCOMING CALLS (POLLING)
    // ============================================
    
    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    
    // Check for incoming calls every 3 seconds
    function checkForIncomingCalls() {
        fetch('/call/incoming', {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log('Incoming call check:', data); // Debug log
            if (data.has_incoming_call) {
                showIncomingCallModal(data.call);
            }
        })
        .catch(error => console.debug('Error checking calls:', error));
    }
    
    // Show incoming call modal
    function showIncomingCallModal(call) {
        console.log('Showing modal for call:', call); // Debug log
        
        // Check if modal already showing
        if (document.getElementById('incomingCallModal')) {
            return;
        }
        
        // Create modal
        const modalHtml = `
            <div class="modal fade show" id="incomingCallModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050;">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-${call.type === 'video' ? 'info' : 'success'} text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-${call.type === 'video' ? 'camera-video' : 'telephone'} me-2"></i>
                                Incoming ${call.type === 'video' ? 'Video' : 'Voice'} Call
                            </h5>
                        </div>
                        <div class="modal-body text-center py-4">
                            <div class="mb-3">
                                <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center mx-auto" 
                                     style="width: 80px; height: 80px;">
                                    <span class="text-white fw-bold fs-1">
                                        ${call.caller.name ? call.caller.name.charAt(0).toUpperCase() : '?'}
                                    </span>
                                </div>
                            </div>
                            <h4>${call.caller.name || 'Unknown'}</h4>
                            <p class="text-muted">is calling you...</p>
                            
                            <div class="mt-4">
                                <audio id="ringtone" loop autoplay>
                                    <source src="https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3" type="audio/mpeg">
                                </audio>
                            </div>
                        </div>
                        <div class="modal-footer justify-content-center">
                            <button class="btn btn-success btn-lg rounded-circle mx-2" onclick="acceptCall(${call.id}, ${call.caller_id}, '${call.type}')" style="width: 60px; height: 60px;">
                                <i class="bi bi-telephone"></i>
                            </button>
                            <button class="btn btn-danger btn-lg rounded-circle mx-2" onclick="rejectCall(${call.id})" style="width: 60px; height: 60px;">
                                <i class="bi bi-telephone-x"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Play ringtone
        setTimeout(() => {
            const ringtone = document.getElementById('ringtone');
            if (ringtone) {
                ringtone.play().catch(e => console.log('Audio play failed:', e));
            }
        }, 100);
    }
    
    // Accept call
    window.acceptCall = function(callId, callerId, callType) {
        console.log('Accepting call:', callId, callerId, callType); // Debug log
        
        // Stop ringtone
        const ringtone = document.getElementById('ringtone');
        if (ringtone) ringtone.pause();
        
        // Remove modal
        const modal = document.getElementById('incomingCallModal');
        if (modal) modal.remove();
        
        // Accept call via AJAX
        fetch('/call/accept', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ call_id: callId })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Accept response:', data); // Debug log
            if (data.success) {
                // Redirect to call page with the caller's user ID
                window.location.href = `/call/${callerId}?type=${callType}`;
            }
        })
        .catch(error => console.error('Error:', error));
    };
    
    // Reject call
    window.rejectCall = function(callId) {
        console.log('Rejecting call:', callId); // Debug log
        
        // Stop ringtone
        const ringtone = document.getElementById('ringtone');
        if (ringtone) ringtone.pause();
        
        // Remove modal
        const modal = document.getElementById('incomingCallModal');
        if (modal) modal.remove();
        
        // Reject call via AJAX
        fetch('/call/reject', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ call_id: callId })
        });
    };
    
    // Start checking for incoming calls
    setInterval(checkForIncomingCalls, 3000);
    
    // Initial check
    setTimeout(checkForIncomingCalls, 1000);
</script>
@endpush
@endsection