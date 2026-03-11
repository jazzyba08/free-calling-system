@php
    $isSent = $message->sender_id == Auth::id();
@endphp

<div class="message {{ $isSent ? 'sent' : 'received' }}" 
    id="message-{{ $message->id }}"
    data-message-id="{{ $message->id }}"
    data-is-read="{{ $message->is_read ? 'true' : 'false' }}">
    <style>
        .message {
            margin-bottom: 15px;
            max-width: 70%;
            clear: both;
        }
        .message.sent {
            float: right;
        }
        .message.received {
            float: left;
        }
        .message-content {
            padding: 10px 15px;
            border-radius: 15px;
            position: relative;
            word-wrap: break-word;
        }
        .message.sent .message-content {
            background-color: #007bff;
            color: white;
            border-bottom-right-radius: 5px;
        }
        .message.received .message-content {
            background-color: #e9ecef;
            color: black;
            border-bottom-left-radius: 5px;
        }
        .message-time {
            font-size: 0.7rem;
            margin-top: 2px;
            opacity: 0.8;
        }
        .message.sent .message-time {
            text-align: right;
            color: rgba(255,255,255,0.8);
        }
        .message.received .message-time {
            color: #6c757d;
        }
        .message-text {
            line-height: 1.4;
        }
    </style>
    <div class="message-content">
        <div class="message-text">
            {{ $message->message }}
        </div>
        <div class="message-time">
            {{ $message->created_at->format('h:i A') }}
            @if($isSent)
                @if($message->is_read)
                    <span class="read-receipt double">✓✓</span>
                @else
                    <span class="read-receipt single">✓</span>
                @endif
            @endif
        </div>
    </div>
</div>