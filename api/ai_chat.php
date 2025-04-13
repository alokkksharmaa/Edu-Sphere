<?php
require_once '../config/config.php';
require_once '../includes/utilities.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isStudent()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit;
}

$query = $_POST['query'] ?? '';
if (empty($query)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Query is required']);
    exit;
}

try {
    $db = connectDB();
    
    // Get AI configuration
    $stmt = $db->prepare("SELECT * FROM ai_config LIMIT 1");
    $stmt->execute();
    $ai_config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ai_config || empty($ai_config['api_key'])) {
        throw new Exception('AI chat is not configured');
    }

    // Call OpenAI API
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    
    $data = [
        'model' => $ai_config['model'],
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a helpful academic assistant. Provide clear, accurate, and educational responses to student questions. Focus on explaining concepts thoroughly and providing examples when relevant.'
            ],
            [
                'role' => 'user',
                'content' => $query
            ]
        ],
        'max_tokens' => $ai_config['max_tokens'],
        'temperature' => $ai_config['temperature'],
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $ai_config['api_key']
        ]
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        throw new Exception('Failed to get response from AI service');
    }

    $result = json_decode($response, true);
    if (!isset($result['choices'][0]['message']['content'])) {
        throw new Exception('Invalid response from AI service');
    }

    $ai_response = $result['choices'][0]['message']['content'];

    // Save to chat history
    $stmt = $db->prepare("
        INSERT INTO ai_chat_history (user_id, query, response)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $query, $ai_response]);

    echo json_encode([
        'success' => true,
        'response' => $ai_response
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
