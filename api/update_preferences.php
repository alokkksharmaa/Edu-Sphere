<?php
require_once '../config/config.php';
require_once '../includes/utilities.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!CSRF::verifyToken($data['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit;
}

try {
    $db = connectDB();
    
    // Check if preferences exist
    $stmt = $db->prepare("SELECT user_id FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    if ($stmt->rowCount() === 0) {
        // Create new preferences
        $stmt = $db->prepare("
            INSERT INTO user_preferences (user_id, theme)
            VALUES (?, ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $data['theme']]);
    } else {
        // Update existing preferences
        $stmt = $db->prepare("
            UPDATE user_preferences 
            SET theme = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$data['theme'], $_SESSION['user_id']]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
