<?php
require_once '../config/config.php';
require_once '../includes/utilities.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('/auth/login.php');
}

$db = connectDB();

// Get AI configuration
$stmt = $db->prepare("SELECT * FROM ai_config LIMIT 1");
$stmt->execute();
$ai_config = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ai_config || empty($ai_config['api_key'])) {
    $_SESSION['error'] = 'AI chat is not configured. Please contact the administrator.';
    redirect('/dashboard/student.php');
}

// Get chat history
$stmt = $db->prepare("
    SELECT * FROM ai_chat_history 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 50
");
$stmt->execute([$_SESSION['user_id']]);
$chat_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "AI Academic Assistant";

ob_start();
?>

<div class="max-w-4xl mx-auto" x-data="{ message: '', loading: false }">
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <!-- Chat Header -->
        <div class="bg-blue-600 p-4">
            <h1 class="text-xl font-bold text-white">AI Academic Assistant</h1>
            <p class="text-blue-100 text-sm">
                Ask any academic questions and get instant help
            </p>
        </div>

        <!-- Chat Messages -->
        <div class="h-[600px] overflow-y-auto p-4 space-y-4" id="chatMessages">
            <?php foreach (array_reverse($chat_history) as $chat): ?>
                <!-- User Message -->
                <div class="flex justify-end mb-4">
                    <div class="bg-blue-100 rounded-lg p-3 max-w-[80%]">
                        <p class="text-gray-800"><?php echo nl2br(htmlspecialchars($chat['query'])); ?></p>
                        <p class="text-xs text-gray-500 mt-1">
                            <?php echo date('g:i a', strtotime($chat['created_at'])); ?>
                        </p>
                    </div>
                </div>

                <!-- AI Response -->
                <div class="flex justify-start mb-4">
                    <div class="bg-gray-100 rounded-lg p-3 max-w-[80%]">
                        <p class="text-gray-800"><?php echo nl2br(htmlspecialchars($chat['response'])); ?></p>
                        <p class="text-xs text-gray-500 mt-1">
                            <?php echo date('g:i a', strtotime($chat['created_at'])); ?>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Message Input -->
        <div class="border-t p-4">
            <form @submit.prevent="sendMessage" 
                  class="flex space-x-2"
                  id="chatForm">
                <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">
                <textarea x-model="message"
                          class="flex-1 rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                          rows="2"
                          placeholder="Type your question here..."
                          :disabled="loading"></textarea>
                <button type="submit"
                        class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                        :disabled="loading || !message.trim()">
                    <span x-show="!loading">Send</span>
                    <span x-show="loading">
                        <i class="fas fa-spinner fa-spin"></i>
                    </span>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('chatMessages', () => ({
        message: '',
        loading: false,

        async sendMessage() {
            if (!this.message.trim() || this.loading) return;

            this.loading = true;
            const formData = new FormData();
            formData.append('query', this.message);
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

            try {
                const response = await fetch('<?php echo BASE_URL; ?>/api/ai_chat.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) throw new Error('Network response was not ok');
                
                const data = await response.json();
                
                if (data.success) {
                    // Add messages to chat
                    const chatMessages = document.getElementById('chatMessages');
                    
                    // User message
                    const userDiv = document.createElement('div');
                    userDiv.className = 'flex justify-end mb-4';
                    userDiv.innerHTML = `
                        <div class="bg-blue-100 rounded-lg p-3 max-w-[80%]">
                            <p class="text-gray-800">${this.message}</p>
                            <p class="text-xs text-gray-500 mt-1">Just now</p>
                        </div>
                    `;
                    chatMessages.appendChild(userDiv);

                    // AI response
                    const aiDiv = document.createElement('div');
                    aiDiv.className = 'flex justify-start mb-4';
                    aiDiv.innerHTML = `
                        <div class="bg-gray-100 rounded-lg p-3 max-w-[80%]">
                            <p class="text-gray-800">${data.response}</p>
                            <p class="text-xs text-gray-500 mt-1">Just now</p>
                        </div>
                    `;
                    chatMessages.appendChild(aiDiv);

                    // Clear input and scroll to bottom
                    this.message = '';
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                } else {
                    alert(data.error || 'Failed to get response');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to send message');
            } finally {
                this.loading = false;
            }
        }
    }));
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>
