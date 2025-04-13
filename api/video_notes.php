<?php
require_once '../config/config.php';
require_once '../includes/utilities.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$db = connectDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $lesson_id = $_GET['lesson_id'] ?? null;
    
    if (!$lesson_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Lesson ID is required']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            SELECT * FROM video_notes
            WHERE user_id = ? AND lesson_id = ?
            ORDER BY timestamp ASC
        ");
        $stmt->execute([$_SESSION['user_id'], $lesson_id]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'notes' => $notes
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!CSRF::verifyToken($data['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit;
    }

    $lesson_id = $data['lesson_id'] ?? null;
    $timestamp = $data['timestamp'] ?? null;
    $note_text = $data['note_text'] ?? null;

    if (!$lesson_id || !isset($timestamp) || !$note_text) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO video_notes (user_id, lesson_id, timestamp, note_text)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $lesson_id,
            $timestamp,
            $note_text
        ]);

        echo json_encode([
            'success' => true,
            'note_id' => $db->lastInsertId()
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
?>
