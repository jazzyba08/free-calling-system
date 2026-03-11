<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use App\Models\Message;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if user is online
     * WHY: To show online/offline status in real-time
     * 
     * @return bool
     */
    public function isOnline()
    {
        // Check if user has been active in last 5 minutes
        // We'll implement this properly when we add real-time features
        return Cache::has('user-is-online-' . $this->id);
    }

    /**
     * Get unread messages count
     * WHY: To show notification badges
     * 
     * @return int
     */
    public function unreadMessagesCount()
    {
        return Message::where('receiver_id', $this->id)
                     ->where('is_read', false)
                     ->count();
    }

    /**
     * Get all messages sent by this user
     */
    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Get all messages received by this user
     */
    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    /**
     * Get conversations with all users
     */
    public function conversations()
    {
        return Message::where('sender_id', $this->id)
                    ->orWhere('receiver_id', $this->id)
                    ->with(['sender', 'receiver'])
                    ->get()
                    ->groupBy(function($message) {
                        return $message->sender_id == $this->id 
                            ? $message->receiver_id 
                            : $message->sender_id;
                    });
    }

    /**
     * Get unread messages count
     */
    public function unreadCount()
    {
        return Message::where('receiver_id', $this->id)
                    ->where('is_read', false)
                    ->count();
    }

    /**
     * Get last message with specific user
     */
    public function lastMessageWith($userId)
    {
        return Message::where(function($query) use ($userId) {
            $query->where('sender_id', $this->id)
                  ->where('receiver_id', $userId);
        })->orWhere(function($query) use ($userId) {
            $query->where('sender_id', $userId)
                  ->where('receiver_id', $this->id);
        })->latest()->first();
    }

    // Add these relationships to your User model

    public function initiatedCalls()
    {
        return $this->hasMany(Call::class, 'caller_id');
    }

    public function receivedCalls()
    {
        return $this->hasMany(Call::class, 'receiver_id');
    }

    public function activeCall()
    {
        return Call::where(function($q) {
            $q->where('caller_id', $this->id)
            ->orWhere('receiver_id', $this->id);
        })->whereIn('status', ['ringing', 'ongoing'])->first();
    }

    public function isInCall()
    {
        return $this->activeCall() !== null;
    }
}
