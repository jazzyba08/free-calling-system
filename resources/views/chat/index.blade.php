@extends('layouts.app')

@section('title', 'Chat with ' . $otherUser->name)

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Contacts</h5>
            </div>
            <div class="card-body p-0" style="height: 500px; overflow-y: auto;">
                <div class="list-group list-group-flush">
                    @foreach($users as $user)
                        <a href="{{ route('chat.index', $user->id) }}" 
                           class="list-group-item list-group-item-action {{ $otherUser->id == $user->id ? 'active' : '' }}">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="bg-{{ $otherUser->id == $user->id ? 'light' : 'primary' }} rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 40px; height: 40px;">
                                        <span class="text-{{ $otherUser->id == $user->id ? 'primary' : 'white' }} fw-bold">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-0">{{ $user->name }}</h6>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-8" 
         data-current-user-id="{{ Auth::id() }}"
         data-other-user-id="{{ $otherUser->id }}">
        <div class="card">
            <div class="card-header">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" 
                             style="width: 40px; height: 40px;">
                            <span class="text-white fw-bold">
                                {{ strtoupper(substr($otherUser->name, 0, 1)) }}
                            </span>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="mb-0">{{ $otherUser->name }}</h5>
                        <small class="text-muted" id="userStatus">Online</small>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-outline-primary me-2" id="voiceCallBtn" data-user-id="{{ $otherUser->id }}">
                            <i class="bi bi-telephone"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-success" id="videoCallBtn" data-user-id="{{ $otherUser->id }}">
                            <i class="bi bi-camera-video"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="card-body" id="chat-messages" style="height: 400px; overflow-y: auto; background-color: #f8f9fa; padding: 15px;">
                @foreach($messages as $message)
                    @include('chat.partials.message', ['message' => $message])
                @endforeach
            </div>
            
            <div class="card-footer">
                <form id="messageForm" class="d-flex">
                    @csrf
                    <input type="hidden" id="receiver_id" value="{{ $otherUser->id }}">
                    <input type="text" 
                           id="messageInput" 
                           class="form-control me-2" 
                           placeholder="Type your message..." 
                           autocomplete="off">
                    <button type="submit" class="btn btn-primary" id="sendBtn">
                        Send
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    #chat-messages {
        min-height: 400px;
    }
    
    .message {
        margin-bottom: 15px;
        max-width: 70%;
        clear: both;
        animation: fadeIn 0.3s;
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
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 3px;
    }
    
    .message.sent .message-time {
        color: rgba(255,255,255,0.8);
    }
    
    .message.received .message-time {
        color: #6c757d;
    }
    
    .message-text {
        line-height: 1.4;
    }
    
    .read-receipt {
        font-size: 0.8rem;
        margin-left: 3px;
        display: inline-block;
    }
    
    .read-receipt.single {
        opacity: 0.7;
    }
    
    .read-receipt.double {
        color: #4CAF50;
        font-weight: bold;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    #messageInput:focus {
        box-shadow: none;
        border-color: #80bdff;
    }
    
    #chat-messages::-webkit-scrollbar {
        width: 6px;
    }
    
    #chat-messages::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    
    #chat-messages::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 3px;
    }
    
    #chat-messages::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
</style>
@endpush

@push('scripts')
<script>
    // ============================================
    // CHAT SYSTEM WITH PROPER READ RECEIPTS
    // ============================================
    
    document.addEventListener('DOMContentLoaded', function() {
        // Get DOM elements
        const chatMessages = document.getElementById('chat-messages');
        const messageForm = document.getElementById('messageForm');
        const messageInput = document.getElementById('messageInput');
        const receiverId = document.getElementById('receiver_id').value;
        const sendBtn = document.getElementById('sendBtn');
        
        // Get current user info
        const currentUserId = parseInt('{{ Auth::id() }}');
        const otherUserId = parseInt('{{ $otherUser->id }}');
        
        // Track which messages have been marked as read (to prevent duplicates)
        const markedAsRead = new Set();
        
        // Track sent messages that are pending read receipt
        const pendingMessages = new Set();
        
        // Initialize - scroll to bottom
        chatMessages.scrollTop = chatMessages.scrollHeight;
        
        // Mark messages as read on initial load (only once)
        setTimeout(() => {
            markVisibleMessagesAsRead();
        }, 1000);
        
        // Track scroll position
        let isAtBottom = true;
        let scrollTimeout = null;
        
        chatMessages.addEventListener('scroll', function() {
            const tolerance = 10;
            isAtBottom = chatMessages.scrollTop + chatMessages.clientHeight >= chatMessages.scrollHeight - tolerance;
            
            // Debounce scroll events to prevent too many requests
            if (scrollTimeout) {
                clearTimeout(scrollTimeout);
            }
            
            scrollTimeout = setTimeout(() => {
                if (isAtBottom) {
                    markVisibleMessagesAsRead();
                }
            }, 300);
        });
        
        // Form submission
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            sendMessage();
        });
        
        // Enter key to send
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        
        // Poll for new messages (every 3 seconds)
        setInterval(fetchNewMessages, 3000);
        
        /**
         * Send a new message
         */
        function sendMessage() {
            const message = messageInput.value.trim();
            if (!message) return;
            
            // Disable input while sending
            messageInput.disabled = true;
            sendBtn.disabled = true;
            
            // Get CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            
            if (!csrfToken) {
                console.error('CSRF token not found');
                enableInputs();
                return;
            }
            
            // Send AJAX request
            fetch('{{ route("chat.send") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    receiver_id: receiverId,
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.message) {
                    // Add to pending messages (waiting for read receipt)
                    pendingMessages.add(data.message.id);
                    
                    // Append message to chat
                    appendMessage(data.message);
                    
                    // Clear input
                    messageInput.value = '';
                    
                    // Scroll to bottom
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to send message. Please try again.');
            })
            .finally(() => {
                enableInputs();
            });
        }
        
        /**
         * Enable message inputs
         */
        function enableInputs() {
            messageInput.disabled = false;
            sendBtn.disabled = false;
            messageInput.focus();
        }
        
        /**
         * Append a message to the chat
         */
        function appendMessage(message) {
            if (!chatMessages || !message) return;
            
            // Check if message already exists
            if (document.getElementById(`message-${message.id}`)) {
                return;
            }
            
            const messageDiv = document.createElement('div');
            
            // Determine if message is sent or received
            const isSent = message.sender_id == currentUserId;
            messageDiv.className = `message ${isSent ? 'sent' : 'received'}`;
            messageDiv.id = `message-${message.id}`;
            messageDiv.dataset.messageId = message.id;
            messageDiv.dataset.isRead = message.is_read ? 'true' : 'false';
            
            // Format time
            let time = '';
            if (message.created_at) {
                const date = new Date(message.created_at);
                time = date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            }
            
            // Create read receipt HTML (ONLY for sent messages)
            let readReceipt = '';
            if (isSent) {
                if (message.is_read || markedAsRead.has(message.id) || !pendingMessages.has(message.id)) {
                    // Double tick (read)
                    readReceipt = '<span class="read-receipt double">✓✓</span>';
                } else {
                    // Single tick (sent but not read)
                    readReceipt = '<span class="read-receipt single">✓</span>';
                }
            }
            
            // Escape message text to prevent XSS
            const escapedMessage = escapeHtml(message.message);
            
            messageDiv.innerHTML = `
                <div class="message-content">
                    <div class="message-text">${escapedMessage}</div>
                    <div class="message-time">
                        ${time}
                        ${readReceipt}
                    </div>
                </div>
            `;
            
            chatMessages.appendChild(messageDiv);
        }
        
        /**
         * Escape HTML to prevent XSS
         */
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        /**
         * Check if element is in viewport
         */
        function isElementInViewport(el) {
            const rect = el.getBoundingClientRect();
            const chatRect = chatMessages.getBoundingClientRect();
            
            return (
                rect.top >= chatRect.top &&
                rect.bottom <= chatRect.bottom
            );
        }
        
        /**
         * Mark visible messages as read
         */
        function markVisibleMessagesAsRead() {
            const messagesToMark = [];
            
            // Find all received messages that are visible and not marked as read
            document.querySelectorAll('.message.received').forEach(el => {
                const messageId = parseInt(el.id.replace('message-', ''));
                const isRead = el.dataset.isRead === 'true';
                
                // Check if message is visible in the chat container
                if (isElementInViewport(el) && !isRead && !markedAsRead.has(messageId)) {
                    messagesToMark.push(messageId);
                }
            });
            
            if (messagesToMark.length > 0) {
                markMessagesAsRead(messagesToMark);
            }
        }
        
        /**
         * Mark specific messages as read
         */
        function markMessagesAsRead(messageIds) {
            if (!messageIds || messageIds.length === 0) return;
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            
            if (!csrfToken) return;
            
            fetch(`/chat/mark-read/${otherUserId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    message_ids: messageIds
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mark these messages as read locally
                    messageIds.forEach(id => {
                        markedAsRead.add(id);
                        
                        // Update UI for these messages
                        const messageEl = document.getElementById(`message-${id}`);
                        if (messageEl) {
                            messageEl.dataset.isRead = 'true';
                            
                            // Update the read receipt (for received messages, we don't show ticks)
                            // But we need to update the sender's view, which will happen via polling
                        }
                    });
                    
                    // Also update any sent messages (for the sender's view)
                    // This will be handled by the next poll
                }
            })
            .catch(error => console.debug('Error marking as read:', error));
        }
        
        /**
         * Fetch new messages
         */
        function fetchNewMessages() {
            if (!receiverId) return;
            
            fetch(`/chat/messages/${otherUserId}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(messages => {
                // Get current message IDs
                const currentIds = new Set();
                document.querySelectorAll('.message').forEach(el => {
                    const id = parseInt(el.id.replace('message-', ''));
                    if (!isNaN(id)) currentIds.add(id);
                });
                
                // Check for new messages or updated read status
                messages.forEach(message => {
                    if (!currentIds.has(message.id)) {
                        // New message
                        appendMessage(message);
                        
                        // If it's a received message and visible, mark as read
                        if (message.sender_id != currentUserId) {
                            const newEl = document.getElementById(`message-${message.id}`);
                            if (newEl && isElementInViewport(newEl)) {
                                markMessagesAsRead([message.id]);
                            }
                        }
                    } else {
                        // Existing message - check if read status changed
                        const existingEl = document.getElementById(`message-${message.id}`);
                        if (existingEl && existingEl.dataset.isRead !== 'true' && message.is_read) {
                            // Message was marked as read - update UI
                            existingEl.dataset.isRead = 'true';
                            
                            // Update the read receipt (only for sent messages)
                            if (message.sender_id == currentUserId) {
                                const timeEl = existingEl.querySelector('.message-time');
                                if (timeEl) {
                                    const singleTick = timeEl.querySelector('.read-receipt.single');
                                    if (singleTick) {
                                        singleTick.className = 'read-receipt double';
                                        singleTick.textContent = '✓✓';
                                    } else {
                                        // Replace any existing content with double tick
                                        timeEl.innerHTML = timeEl.innerHTML.replace(/✓+<\/span>/, '<span class="read-receipt double">✓✓</span>');
                                    }
                                }
                            }
                            
                            // Remove from pending
                            pendingMessages.delete(message.id);
                            markedAsRead.add(message.id);
                        }
                    }
                });
                
                // Scroll to bottom if user was at bottom
                if (isAtBottom) {
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }
            })
            .catch(error => console.debug('Polling error:', error));
        }
        
        // Mark messages as read when window gains focus
        window.addEventListener('focus', function() {
            markVisibleMessagesAsRead();
        });
        
        console.log('Chat initialized with fixed read receipts');
    });

    // Voice call button
    const voiceCallBtn = document.getElementById('voiceCallBtn');
    if (voiceCallBtn) {
        voiceCallBtn.addEventListener('click', function() {
            const userId = this.dataset.userId;
            window.location.href = `/call/${userId}?type=voice`;
        });
    }

    // Video call button
    const videoCallBtn = document.getElementById('videoCallBtn');
    if (videoCallBtn) {
        videoCallBtn.addEventListener('click', function() {
            const userId = this.dataset.userId;
            window.location.href = `/call/${userId}?type=video`;
        });
    }
</script>
@endpush
