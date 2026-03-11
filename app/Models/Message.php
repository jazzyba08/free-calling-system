<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Message extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     * These can be filled when creating/updating
     */
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'message',
        'is_read'
    ];

    /**
     * The attributes that should be cast.
     * Convert to native types
     */
    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the sender of the message
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the receiver of the message
     */
    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * Scope to get conversation between two users
     */
    public function scopeBetweenUsers($query, $user1, $user2)
    {
        return $query->where(function($q) use ($user1, $user2) {
            $q->where('sender_id', $user1)
              ->where('receiver_id', $user2);
        })->orWhere(function($q) use ($user1, $user2) {
            $q->where('sender_id', $user2)
              ->where('receiver_id', $user1);
        })->orderBy('created_at', 'asc');
    }

    /**
     * Scope to get unread messages for a user
     */
    public function scopeUnreadForUser($query, $userId)
    {
        return $query->where('receiver_id', $userId)
                    ->where('is_read', false);
    }

    /**
     * Mark message as read
     */
    public function markAsRead()
    {
        $this->update(['is_read' => true]);
    }

    /**
     * Check if message is read
     */
    public function isRead()
    {
        return $this->is_read;
    }

    /**
     * Check if message is sent by specific user
     */
    public function isSentBy($userId)
    {
        return $this->sender_id == $userId;
    }

    /**
     * Get message time in human readable format
     */
    public function getTimeAgoAttribute()
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get message time formatted
     */
    public function getFormattedTimeAttribute()
    {
        return $this->created_at->format('h:i A');
    }

    /**
     * Get message date
     */
    public function getDateAttribute()
    {
        return $this->created_at->format('Y-m-d');
    }
}