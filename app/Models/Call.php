<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Call extends Model
{
    use HasFactory;

    protected $fillable = [
        'caller_id',
        'receiver_id',
        'status',
        'type',
        'started_at',
        'ended_at'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime'
    ];

    public function caller()
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    // public function scopeActive($query)
    // {
    //     return $query->whereIn('status', ['ringing', 'ongoing']);
    // }

    // public function scopeBetweenUsers($query, $user1, $user2)
    // {
    //     return $query->where(function($q) use ($user1, $user2) {
    //         $q->where('caller_id', $user1)->where('receiver_id', $user2);
    //     })->orWhere(function($q) use ($user1, $user2) {
    //         $q->where('caller_id', $user2)->where('receiver_id', $user1);
    //     });
    // }

    public function accept()
    {
        $this->update([
            'status' => 'ongoing',
            'started_at' => now()
        ]);
        return $this;
    }

    public function reject()
    {
        $this->update(['status' => 'rejected']);
        return $this;
    }

    public function end()
    {
        $this->update([
            'status' => 'ended',
            'ended_at' => now()
        ]);
        return $this;
    }

    public function miss()
    {
        $this->update(['status' => 'missed']);
        return $this;
    }

    public function isActive()
    {
        return in_array($this->status, ['ringing', 'ongoing']);
    }

    public function getDurationAttribute()
    {
        if ($this->started_at && $this->ended_at) {
            return $this->started_at->diffInSeconds($this->ended_at);
        }
        return 0;
    }

    public function getFormattedDurationAttribute()
    {
        $seconds = $this->duration;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        if ($hours > 0) {
            return sprintf("%02d:%02d:%02d", $hours, $minutes, $secs);
        }
        return sprintf("%02d:%02d", $minutes, $secs);
    }

    /**
     * Scope for calls between two users
     */
    public function scopeBetweenUsers($query, $user1, $user2)
    {
        return $query->where(function($q) use ($user1, $user2) {
            $q->where('caller_id', $user1)
            ->where('receiver_id', $user2);
        })->orWhere(function($q) use ($user1, $user2) {
            $q->where('caller_id', $user2)
            ->where('receiver_id', $user1);
        });
    }

    /**
     * Scope for active calls (ringing or ongoing)
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['ringing', 'ongoing']);
    }
}