<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Show the dashboard
     * WHY: Display all users and main interface
     */
    public function index()
    {
        // Get all users except current logged in user
        $users = User::where('id', '!=', Auth::id())->get();
        
        // Return the dashboard view with users data
        return view('dashboard', compact('users'));
    }
}