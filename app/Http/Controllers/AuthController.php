<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Show registration form
     * WHY: Display the page where users can register
     */
    public function showRegister()
    {
        return view('auth.register');
    }

    /**
     * Handle registration form submission
     * WHY: Process the registration data and create new user
     */
    public function register(Request $request)
    {
        // Step 1: Validate the form data
        $request->validate([
            'name' => 'required|string|max:255',
            // required: field must be filled
            // string: must be text
            // max:255: maximum 255 characters
            
            'email' => 'required|string|email|max:255|unique:users',
            // email: must be valid email format
            // unique:users: email must not exist in users table
            
            'password' => 'required|string|min:8|confirmed',
            // min:8: at least 8 characters
            // confirmed: must have password_confirmation field matching
        ]);

        // Step 2: Create the user in database
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password), // Always hash passwords!
        ]);

        // Step 3: Log the user in automatically
        Auth::login($user);

        // Step 4: Redirect to dashboard with success message
        return redirect()->route('dashboard')
            ->with('success', 'Welcome! Your account has been created successfully.');
    }

    /**
     * Show login form
     * WHY: Display the page where users can login
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Handle login form submission
     * WHY: Authenticate user credentials
     */
    public function login(Request $request)
    {
        // Step 1: Validate the form data
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        // Step 2: Attempt to log the user in
        $credentials = $request->only('email', 'password');
        // only() gets just the email and password fields

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            // Auth::attempt() checks if credentials are correct
            // $request->filled('remember') checks if "remember me" checkbox is checked
            
            // Step 3: Regenerate session to prevent session fixation
            $request->session()->regenerate();
            
            // Step 4: Redirect to dashboard
            return redirect()->intended(route('dashboard'))
                ->with('success', 'Welcome back! You are now logged in.');
        }

        // If login fails, redirect back with error
        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
        // onlyInput('email') keeps the email field filled
    }

    /**
     * Handle logout
     * WHY: Log the user out of the application
     */
    public function logout(Request $request)
    {
        Auth::logout(); // Log the user out
        
        // Invalidate the session
        $request->session()->invalidate();
        
        // Regenerate CSRF token
        $request->session()->regenerateToken();
        
        // Redirect to home page
        return redirect('/')->with('success', 'You have been logged out successfully.');
    }
}