<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    /**
     * Constructor - ensure user is authenticated
     */
    // public function __construct()
    // {
    //     $this->middleware('auth');
    // }

    /**
     * Show chat interface with specific user
     */
    public function index($userId)
    {
        // Get the other user's information
        $otherUser = User::findOrFail($userId);
        
        // Get current logged in user
        $currentUser = Auth::user();
        
        // Get conversation between these two users
        $messages = Message::betweenUsers($currentUser->id, $userId)
                          ->with(['sender', 'receiver'])
                          ->get();
        
        // Get all users for sidebar (except current user)
        $users = User::where('id', '!=', $currentUser->id)->get();
        
        // Return view with data
        return view('chat.index', compact('otherUser', 'messages', 'users'));
    }

    /**
     * Send a new message
     */
    public function sendMessage(Request $request)
    {
        // Validate request
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string|max:1000'
        ]);

        // Create message
        $message = Message::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $request->receiver_id,
            'message' => $request->message,
            'is_read' => false  // New messages start as unread
        ]);

        // Load sender information
        $message->load('sender');

        // Return JSON response with clean data
        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'sender_id' => $message->sender_id,
                'receiver_id' => $message->receiver_id,
                'message' => $message->message,
                'is_read' => $message->is_read,
                'created_at' => $message->created_at->toISOString()
            ]
        ]);
    }

    /**
     * Get messages with specific user (WITHOUT marking as read)
     */
    public function getMessages($userId)
    {
        $messages = Message::betweenUsers(Auth::id(), $userId)
                          ->with(['sender', 'receiver'])
                          ->get()
                          ->map(function($message) {
                              return [
                                  'id' => $message->id,
                                  'sender_id' => $message->sender_id,
                                  'receiver_id' => $message->receiver_id,
                                  'message' => $message->message,
                                  'is_read' => $message->is_read,
                                  'created_at' => $message->created_at->toISOString()
                              ];
                          });

        // DO NOT mark as read here - let the frontend control this
        return response()->json($messages);
    }

    /**
     * Mark specific messages as read (called from frontend when messages are visible)
     */
    public function markAsRead(Request $request, $userId)
    {
        // Validate that we have message IDs
        $request->validate([
            'message_ids' => 'required|array',
            'message_ids.*' => 'integer|exists:messages,id'
        ]);

        // Only mark the specific messages that are visible to the user
        $updated = Message::whereIn('id', $request->message_ids)
                         ->where('sender_id', $userId)  // Messages sent by the other user
                         ->where('receiver_id', Auth::id())  // Received by current user
                         ->where('is_read', false)  // Only unread messages
                         ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'marked_count' => $updated,
            'message_ids' => $request->message_ids
        ]);
    }

    /**
     * Get recent conversations
     */
    public function getConversations()
    {
        $user = Auth::user();
        $messages = Message::where('sender_id', $user->id)
                          ->orWhere('receiver_id', $user->id)
                          ->with(['sender', 'receiver'])
                          ->get()
                          ->groupBy(function($message) use ($user) {
                              return $message->sender_id == $user->id 
                                  ? $message->receiver_id 
                                  : $message->sender_id;
                          });

        $conversations = [];
        foreach ($messages as $userId => $userMessages) {
            $otherUser = User::find($userId);
            $lastMessage = $userMessages->last();
            $unreadCount = Message::where('sender_id', $userId)
                                 ->where('receiver_id', $user->id)
                                 ->where('is_read', false)
                                 ->count();

            $conversations[] = [
                'user' => $otherUser,
                'last_message' => $lastMessage,
                'unread_count' => $unreadCount
            ];
        }

        return response()->json($conversations);
    }
}