<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CallController;

/*
|--------------------------------------------------------------------------
| Public Routes (Anyone can access)
|--------------------------------------------------------------------------
*/

// Home page - redirect to dashboard if logged in, else show welcome
Route::get('/', function () {
    /** @var \Illuminate\Contracts\Auth\Guard|\Illuminate\Auth\SessionGuard $auth */
    $auth = auth();
    
    // Check if user is already logged in
    if ($auth->check()) {
        return redirect()->route('dashboard');
    }
    return view('welcome');
});

// Guest routes - only accessible when NOT logged in
Route::middleware('guest')->group(function () {
    // Show registration form
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    
    // Handle registration form submission
    Route::post('/register', [AuthController::class, 'register']);
    
    // Show login form
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    
    // Handle login form submission
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected Routes (Only logged in users can access)
Route::middleware('auth')->group(function () {
    // Logout route
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Chat routes
    Route::get('/chat/{user}', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat/send', [ChatController::class, 'sendMessage'])->name('chat.send');
    Route::get('/chat/messages/{user}', [ChatController::class, 'getMessages'])->name('chat.messages');
    Route::post('/chat/mark-read/{user}', [ChatController::class, 'markAsRead'])->name('chat.mark-read');
    
    // ============ CALL ROUTES ============
    // IMPORTANT: Specific routes must come before parameterized routes!
    Route::get('/call/incoming', [CallController::class, 'incoming'])->name('call.incoming');
    Route::get('/call/{user}', [CallController::class, 'index'])->name('call.index');
    
    // Call signaling routes
    Route::post('/call/start', [CallController::class, 'start'])->name('call.start');
    Route::post('/call/accept', [CallController::class, 'accept'])->name('call.accept');
    Route::post('/call/reject', [CallController::class, 'reject'])->name('call.reject');
    Route::post('/call/end', [CallController::class, 'end'])->name('call.end');
    
    // WebRTC signaling routes
    Route::post('/call/offer', [CallController::class, 'offer'])->name('call.offer');
    Route::post('/call/answer', [CallController::class, 'answer'])->name('call.answer');
    Route::post('/call/ice-candidate', [CallController::class, 'iceCandidate'])->name('call.ice-candidate');
    Route::get('/call/signaling/{call}', [CallController::class, 'getSignalingData'])->name('call.signaling');
});