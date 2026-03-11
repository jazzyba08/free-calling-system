<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Call;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CallController extends Controller
{
    /**
     * Constructor - ensure user is authenticated
     */
    // public function __construct()
    // {
    //     $this->middleware('auth');
    // }

    /**
     * Show call interface
     */
    public function index(Request $request, $userId)
    {
        $otherUser = User::findOrFail($userId);
        $callType = $request->query('type', 'video'); // Default to video
        
        // Check if there's an existing active call
        $existingCall = Call::betweenUsers(Auth::id(), $userId)
            ->active()
            ->first();
        
        if ($existingCall) {
            $call = $existingCall;
        } else {
            // Create new call
            $call = Call::create([
                'caller_id' => Auth::id(),
                'receiver_id' => $userId,
                'type' => $callType,
                'status' => 'ringing'
            ]);
        }
        
        return view('call.index', compact('otherUser', 'call'));
    }

    /**
     * Check for incoming calls
     */
    public function incoming(Request $request)
    {
        $currentUser = Auth::user();
        
        // Find ringing calls where current user is receiver
        $incomingCall = Call::where('receiver_id', $currentUser->id)
                        ->where('status', 'ringing')
                        ->with(['caller'])
                        ->first();
        
        if ($incomingCall) {
            return response()->json([
                'has_incoming_call' => true,
                'call' => [
                    'id' => $incomingCall->id,
                    'type' => $incomingCall->type,
                    'caller_id' => $incomingCall->caller_id,
                    'caller' => [
                        'id' => $incomingCall->caller->id,
                        'name' => $incomingCall->caller->name
                    ]
                ]
            ]);
        }
        
        return response()->json([
            'has_incoming_call' => false
        ]);
    }

    /**
     * Start a call (API endpoint)
     */
    public function start(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'type' => 'required|in:video,voice'
        ]);

        // Check if there's already an active call
        $existingCall = Call::where(function($q) use ($request) {
            $q->where('caller_id', Auth::id())
              ->where('receiver_id', $request->receiver_id);
        })->orWhere(function($q) use ($request) {
            $q->where('caller_id', $request->receiver_id)
              ->where('receiver_id', Auth::id());
        })->whereIn('status', ['ringing', 'ongoing'])
          ->first();

        if ($existingCall) {
            return response()->json([
                'success' => false,
                'message' => 'There is already an active call with this user',
                'call' => $existingCall
            ]);
        }

        // Create new call
        $call = Call::create([
            'caller_id' => Auth::id(),
            'receiver_id' => $request->receiver_id,
            'type' => $request->type,
            'status' => 'ringing'
        ]);

        // Load relationships
        $call->load(['caller', 'receiver']);

        return response()->json([
            'success' => true,
            'call' => $call
        ]);
    }

    /**
     * Accept a call
     */
    public function accept(Request $request)
    {
        $request->validate([
            'call_id' => 'required|exists:calls,id'
        ]);

        $call = Call::findOrFail($request->call_id);
        
        // Only receiver can accept
        if ($call->receiver_id != Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $call->accept();
        
        // Load relationships
        $call->load(['caller', 'receiver']);

        return response()->json([
            'success' => true,
            'call' => $call
        ]);
    }

    /**
     * Reject a call
     */
    public function reject(Request $request)
    {
        $request->validate([
            'call_id' => 'required|exists:calls,id'
        ]);

        $call = Call::findOrFail($request->call_id);
        
        // Only receiver can reject
        if ($call->receiver_id != Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $call->reject();

        return response()->json([
            'success' => true,
            'call' => $call
        ]);
    }

    /**
     * End a call
     */
    public function end(Request $request)
    {
        $request->validate([
            'call_id' => 'required|exists:calls,id'
        ]);

        $call = Call::findOrFail($request->call_id);
        
        // Both caller and receiver can end
        if ($call->caller_id != Auth::id() && $call->receiver_id != Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $call->end();

        // Clean up cache
        Cache::forget('call_offer_' . $call->id);
        Cache::forget('call_answer_' . $call->id);
        Cache::forget('call_candidates_' . $call->id);

        return response()->json([
            'success' => true,
            'call' => $call
        ]);
    }

    /**
     * Handle call offer
     */
    public function offer(Request $request)
    {
        $request->validate([
            'call_id' => 'required|exists:calls,id',
            'offer' => 'required'
        ]);
        
        $call = Call::find($request->call_id);
        
        // Store offer in cache with a unique key
        Cache::put('call_offer_' . $call->id, json_encode($request->offer), now()->addMinutes(5));
        
        Log::info('Offer stored for call: ' . $call->id);
        
        return response()->json(['success' => true]);
    }

    /**
     * Handle call answer
     */
    public function answer(Request $request)
    {
        $request->validate([
            'call_id' => 'required|exists:calls,id',
            'answer' => 'required'
        ]);
        
        $call = Call::find($request->call_id);
        
        // Update call status to ongoing
        if ($call->status == 'ringing') {
            $call->accept();
        }
        
        // Store answer in cache with a unique key
        Cache::put('call_answer_' . $call->id, json_encode($request->answer), now()->addMinutes(5));
        
        Log::info('Answer stored for call: ' . $call->id);
        
        return response()->json(['success' => true]);
    }

    /**
     * Handle ICE candidate
     */
    public function iceCandidate(Request $request)
    {
        $request->validate([
            'call_id' => 'required|exists:calls,id',
            'candidate' => 'required'
        ]);
        
        $call = Call::find($request->call_id);
        
        // Store ICE candidate
        $candidates = Cache::get('call_candidates_' . $call->id, []);
        $candidates[] = json_encode($request->candidate);
        Cache::put('call_candidates_' . $call->id, $candidates, now()->addMinutes(5));
        
        Log::info('ICE candidate stored for call: ' . $call->id);
        
        return response()->json(['success' => true]);
    }

    /**
     * Get signaling data for a call
     */
    public function getSignalingData($callId)
    {
        $call = Call::findOrFail($callId);
        
        // Get stored data from cache
        $offer = Cache::get('call_offer_' . $callId);
        $answer = Cache::get('call_answer_' . $callId);
        $candidates = Cache::get('call_candidates_' . $callId, []);
        
        $data = [
            'offer' => $offer ? json_decode($offer) : null,
            'answer' => $answer ? json_decode($answer) : null,
            'candidate' => null,
            'call_status' => $call->status
        ];
        
        // Get the next pending candidate
        if (!empty($candidates)) {
            $data['candidate'] = json_decode(array_shift($candidates));
            Cache::put('call_candidates_' . $callId, $candidates, now()->addMinutes(5));
        }
        
        Log::info('Signaling data retrieved for call: ' . $callId, [
            'has_offer' => !is_null($offer),
            'has_answer' => !is_null($answer),
            'candidates_count' => count($candidates)
        ]);
        
        return response()->json($data);
    }
}