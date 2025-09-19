<?php
// Chỉ hiển thị giao diện chatbot
?>
<style>
/* CSS của chatbot */
    #chatbot-float-btn {
        position: fixed; bottom: 32px; right: 32px; width: 60px; height: 60px;
        background: #1976d2; color: #fff; border-radius: 50%;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        display: flex; align-items: center; justify-content: center;
        font-size: 2rem; cursor: pointer; z-index: 9999;
    }
    #chatbot-float-box {
        position: fixed; bottom: 100px; right: 32px; width: 350px; height: 500px;
        background: #fff; border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.18);
        display: none; flex-direction: column; overflow: hidden; z-index: 9999;
    }
    #chatbot-float-box .header {
        background: #1976d2; color: #fff; padding: 12px 16px;
        display: flex; justify-content: space-between; align-items: center;
    }
    #chatbot-messages { flex: 1; padding: 10px; overflow-y: auto; background: #f5f6f8; }
    .message { margin-bottom: 10px; padding: 8px 12px; border-radius: 10px; max-width: 80%; word-wrap: break-word; }
    .user-msg { background: #1976d2; color: white; margin-left: auto; }
    .bot-msg { background: #e0e0e0; color: #000; margin-right: auto; }
    #chatbot-input-box { display: flex; border-top: 1px solid #ddd; }
    #chatbot-input { flex: 1; padding: 10px; border: none; outline: none; font-size: 14px; resize: none; }
    #chatbot-send { background: #1976d2; color: white; border: none; padding: 0 15px; cursor: pointer; }
</style>

<div id="chatbot-float-btn" title="Chatbot AI" onclick="toggleChatbot()">💬</div>
<div id="chatbot-float-box">
    <div class="header">
        <span>💬 Chatbot AI</span>
        <button onclick="toggleChatbot()">&times;</button>
    </div>
    <div id="chatbot-messages"></div>
    <div id="chatbot-input-box">
        <textarea id="chatbot-input" placeholder="Nhập câu hỏi..." rows="1"></textarea>
        <button id="chatbot-send">➤</button>
    </div>
</div>

<script>
    function toggleChatbot() {
        const box = document.getElementById('chatbot-float-box');
        box.style.display = (box.style.display === 'flex') ? 'none' : 'flex';
    }

    function appendMessage(content, type) {
        const div = document.createElement('div');
        div.className = `message ${type}`;
        div.innerText = content;
        document.getElementById('chatbot-messages').appendChild(div);
        document.getElementById('chatbot-messages').scrollTop = document.getElementById('chatbot-messages').scrollHeight;
    }

    function sendMessage() {
        const input = document.getElementById('chatbot-input');
        const question = input.value.trim();
        if (!question) return;
        appendMessage(question, 'user-msg');
        input.value = '';

        // Trỏ đến API riêng biệt
        fetch('chat_bot_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'question=' + encodeURIComponent(question)
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                appendMessage('⚠️ ' + data.error, 'bot-msg');
            } else {
                appendMessage(data.answer, 'bot-msg');
            }
        })
        .catch(() => appendMessage('⚠️ Lỗi kết nối!', 'bot-msg'));
    }

    document.getElementById('chatbot-send').addEventListener('click', sendMessage);
    document.getElementById('chatbot-input').addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
</script>
