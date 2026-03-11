@extends('layouts.app')

@section('title', 'Welcome - Video Chat System')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 text-center">
        <div class="card">
            <div class="card-body py-5">
                <h1 class="display-4 mb-4">Welcome to Video Chat System</h1>
                
                <p class="lead mb-4">
                    A free, open-source platform for real-time communication
                </p>
                
                <div class="row mb-5">
                    <div class="col-md-4">
                        <div class="p-3">
                            <h3>💬</h3>
                            <h5>Real-time Chat</h5>
                            <p class="text-muted">Instant messaging with online status</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3">
                            <h3>📹</h3>
                            <h5>Video Calls</h5>
                            <p class="text-muted">Peer-to-peer video communication</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3">
                            <h3>🎤</h3>
                            <h5>Voice Calls</h5>
                            <p class="text-muted">Clear audio calls directly in browser</p>
                        </div>
                    </div>
                </div>
                
                @guest
                    <div>
                        <a href="{{ route('login') }}" class="btn btn-primary btn-lg mx-2">
                            Login
                        </a>
                        <a href="{{ route('register') }}" class="btn btn-success btn-lg mx-2">
                            Register
                        </a>
                    </div>
                @endguest
            </div>
        </div>
    </div>
</div>
@endsection