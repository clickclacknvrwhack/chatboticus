(function() {
    const chatToggle = document.getElementById('alc-chat-toggle');
    const chatBox = document.getElementById('alc-chat-box');
    const chatClose = document.getElementById('alc-chat-close');
    const chatMessages = document.getElementById('alc-chat-messages');
    const chatInput = document.getElementById('alc-chat-input');
    const chatSend = document.getElementById('alc-chat-send');
    
    let conversationHistory = [];
    let hasNotified = false;
    let pollInterval = null;
    let humanTookOver = false;
    
    // Toggle chat
    chatToggle.onclick = () => {
        const isHidden = chatBox.style.display === 'none';
        chatBox.style.display = isHidden ? 'flex' : 'none';
        
        if (isHidden) {
            if (conversationHistory.length === 0) {
                addBotMessage(alcSettings.greeting);
            }
            // Start polling for human replies
            startPolling();
        } else {
            // Stop polling when chat closed
            stopPolling();
        }
    };
    
    chatClose.onclick = () => {
        chatBox.style.display = 'none';
        stopPolling();
    };
    
    // Add messages
    function addMessage(text, type) {
        const msg = document.createElement('div');
        msg.className = `alc-message alc-${type}-message`;
        msg.textContent = text;
        chatMessages.appendChild(msg);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    function addBotMessage(text) {
        addMessage(text, 'bot');
        conversationHistory.push({role: 'assistant', content: text});
    }
    
    function addUserMessage(text) {
        addMessage(text, 'user');
        conversationHistory.push({role: 'user', content: text});
    }
    
    function addHumanMessage(text) {
        addMessage(text, 'human');
        conversationHistory.push({role: 'assistant', content: text});
        humanTookOver = true;
    }
    
    function showTyping() {
        const typing = document.createElement('div');
        typing.className = 'alc-typing';
        typing.id = 'alc-typing';
        typing.textContent = humanTookOver ? 'Typing...' : 'Thinking...';
        chatMessages.appendChild(typing);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    function hideTyping() {
        const typing = document.getElementById('alc-typing');
        if (typing) typing.remove();
    }
    
    // Check if should notify
    function shouldNotify(message) {
        if (hasNotified) return false;
        
        const triggers = [
            'interested', 'want to talk', 'speak with', 'call me',
            'yes please', 'sounds good', 'lets talk', 'let\'s talk',
            'budget', 'timeline', 'pricing', 'price', 'cost'
        ];
        
        const lower = message.toLowerCase();
        return triggers.some(trigger => lower.includes(trigger)) || 
               conversationHistory.length >= 8;
    }
    
    // Poll for human replies from Discord
    function startPolling() {
        if (pollInterval) return;
        
        pollInterval = setInterval(async () => {
            try {
                const formData = new FormData();
                formData.append('action', 'alc_check_replies');
                formData.append('nonce', alcSettings.nonce);
                
                const response = await fetch(alcSettings.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success && data.data.hasReply) {
                    hideTyping();
                    addHumanMessage(data.data.message);
                }
            } catch (error) {
                console.error('Polling error:', error);
            }
        }, 2000); // Check every 2 seconds
    }
    
    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }
    
    // Send message
    async function sendMessage() {
        const message = chatInput.value.trim();
        if (!message) return;
        
        addUserMessage(message);
        chatInput.value = '';
        showTyping();
        
        // If human took over, don't notify again
        const notify = !humanTookOver && shouldNotify(message);
        if (notify) hasNotified = true;
        
        try {
            const formData = new FormData();
            formData.append('action', 'alc_chat');
            formData.append('nonce', alcSettings.nonce);
            formData.append('messages', JSON.stringify(conversationHistory));
            formData.append('shouldNotify', notify);
            
            const response = await fetch(alcSettings.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            hideTyping();
            
            if (data.success) {
                // Only show bot message if human hasn't taken over
                if (!humanTookOver) {
                    addBotMessage(data.data.message);
                }
            } else {
                addBotMessage("Sorry, I had a hiccup. Can you try again?");
            }
            
        } catch (error) {
            hideTyping();
            addBotMessage("Sorry, something went wrong. Please try again.");
        }
    }
    
    // Event listeners
    chatInput.onkeypress = (e) => {
        if (e.key === 'Enter') sendMessage();
    };
    chatSend.onclick = sendMessage;
    
    // Clean up on page unload
    window.addEventListener('beforeunload', stopPolling);
})();