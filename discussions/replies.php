<?php
require_once '../config/config.php';
require_once '../includes/utilities.php';

if (!isLoggedIn()) {
    redirect('/auth/login.php');
}

$discussion_id = $_GET['discussion_id'] ?? null;
if (!$discussion_id) {
    redirect('/dashboard/student.php');
}

$db = connectDB();

// Get parent discussion and lesson info
$stmt = $db->prepare("
    SELECT d.*, u.username, l.id as lesson_id, l.title as lesson_title
    FROM discussions d
    JOIN users u ON d.user_id = u.id
    JOIN lessons l ON d.lesson_id = l.id
    WHERE d.id = ?
");
$stmt->execute([$discussion_id]);
$parent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$parent) {
    $_SESSION['error'] = 'Discussion not found';
    redirect('/dashboard/student.php');
}

// Get replies
$stmt = $db->prepare("
    SELECT d.*, u.username
    FROM discussions d
    JOIN users u ON d.user_id = u.id
    WHERE d.parent_id = ?
    ORDER BY d.created_at ASC
");
$stmt->execute([$discussion_id]);
$replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid form submission';
        redirect("/discussions/replies.php?discussion_id=$discussion_id");
    }

    try {
        $content = $_POST['content'];

        if (empty($content)) {
            throw new Exception('Reply content is required');
        }

        $stmt = $db->prepare("
            INSERT INTO discussions (lesson_id, user_id, content, parent_id)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $parent['lesson_id'],
            $_SESSION['user_id'],
            $content,
            $discussion_id
        ]);

        $_SESSION['message'] = 'Reply posted successfully';
        redirect("/discussions/replies.php?discussion_id=$discussion_id");

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

$pageTitle = "Discussion Replies";

ob_start();
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
        <!-- Parent Discussion -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-2xl font-bold dark:text-white">Discussion</h1>
                <a href="<?php echo BASE_URL; ?>/discussions/view.php?lesson_id=<?php echo $parent['lesson_id']; ?>" 
                   class="text-blue-500 hover:text-blue-600">
                    Back to Discussions
                </a>
            </div>

            <div class="border dark:border-gray-700 rounded-lg p-4 mb-6">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <span class="font-medium dark:text-white">
                            <?php echo htmlspecialchars($parent['username']); ?>
                        </span>
                        <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">
                            <?php echo timeAgo($parent['created_at']); ?>
                        </span>
                    </div>
                </div>
                <div class="prose dark:prose-invert max-w-none">
                    <?php echo nl2br(htmlspecialchars($parent['content'])); ?>
                </div>
            </div>

            <!-- Reply Form -->
            <form action="<?php echo BASE_URL; ?>/discussions/replies.php?discussion_id=<?php echo $discussion_id; ?>" 
                  method="POST"
                  class="mb-8">
                
                <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">

                <div>
                    <label for="content" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Your Reply
                    </label>
                    <textarea name="content" 
                              id="content" 
                              rows="3"
                              required
                              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                              placeholder="Write your reply..."></textarea>
                </div>

                <div class="mt-3 text-right">
                    <button type="submit" 
                            class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Post Reply
                    </button>
                </div>
            </form>

            <!-- Replies List -->
            <div class="space-y-4">
                <h2 class="text-xl font-semibold mb-4 dark:text-white">
                    <?php echo count($replies); ?> 
                    <?php echo count($replies) === 1 ? 'Reply' : 'Replies'; ?>
                </h2>

                <?php foreach ($replies as $reply): ?>
                    <div class="border dark:border-gray-700 rounded-lg p-4">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <span class="font-medium dark:text-white">
                                    <?php echo htmlspecialchars($reply['username']); ?>
                                </span>
                                <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">
                                    <?php echo timeAgo($reply['created_at']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="prose dark:prose-invert max-w-none">
                            <?php echo nl2br(htmlspecialchars($reply['content'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($replies)): ?>
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        No replies yet. Be the first to reply!
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>
