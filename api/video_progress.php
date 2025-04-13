<?php
require_once '../config/config.php';
require_once '../includes/utilities.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!CSRF::verifyToken($data['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit;
}

$lesson_id = $data['lesson_id'] ?? null;
$current_time = $data['current_time'] ?? null;
$is_completed = $data['is_completed'] ?? false;

if (!$lesson_id || !isset($current_time)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    $db = connectDB();
    
    // Check if progress record exists
    $stmt = $db->prepare("
        SELECT id, current_time, is_completed 
        FROM video_progress 
        WHERE user_id = ? AND lesson_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $lesson_id]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($progress) {
        // Only update if new time is greater or marking as completed
        if ($current_time > $progress['current_time'] || ($is_completed && !$progress['is_completed'])) {
            $stmt = $db->prepare("
                UPDATE video_progress 
                SET current_time = ?, is_completed = ?
                WHERE id = ?
            ");
            $stmt->execute([$current_time, $is_completed, $progress['id']]);
        }
    } else {
        $stmt = $db->prepare("
            INSERT INTO video_progress (user_id, lesson_id, current_time, is_completed)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $lesson_id,
            $current_time,
            $is_completed
        ]);
    }

    // If completed, award XP points
    if ($is_completed) {
        $xp_points = 50; // Award 50 XP for completing a video
        
        $stmt = $db->prepare("
            UPDATE user_progress 
            SET xp_points = xp_points + ?,
                level = FLOOR(xp_points / 1000) + 1
            WHERE user_id = ?
        ");
        $stmt->execute([$xp_points, $_SESSION['user_id']]);
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
