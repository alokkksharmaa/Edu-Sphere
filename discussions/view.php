<?php
require_once '../config/config.php';
require_once '../includes/utilities.php';

if (!isLoggedIn()) {
    redirect('/auth/login.php');
}

$lesson_id = $_GET['lesson_id'] ?? null;
if (!$lesson_id) {
    redirect('/dashboard/student.php');
}

$db = connectDB();

// Get lesson and course info
$stmt = $db->prepare("
    SELECT l.*, c.title as course_title
    FROM lessons l
    JOIN courses c ON l.course_id = c.id
    WHERE l.id = ?
");
$stmt->execute([$lesson_id]);
$lesson = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lesson) {
    $_SESSION['error'] = 'Lesson not found';
    redirect('/dashboard/student.php');
}

// Get discussions
$stmt = $db->prepare("
    SELECT d.*, u.username,
           COUNT(DISTINCT r.id) as reply_count
    FROM discussions d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN discussions r ON r.parent_id = d.id
    WHERE d.lesson_id = ? AND d.parent_id IS NULL
    GROUP BY d.id
    ORDER BY d.created_at DESC
");
$stmt->execute([$lesson_id]);
$discussions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid form submission';
        redirect("/discussions/view.php?lesson_id=$lesson_id");
    }

    try {
        $content = $_POST['content'];
        $parent_id = $_POST['parent_id'] ?? null;

        if (empty($content)) {
            throw new Exception('Content is required');
        }

        $stmt = $db->prepare("
            INSERT INTO discussions (lesson_id, user_id, content, parent_id)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $lesson_id,
            $_SESSION['user_id'],
            $content,
            $parent_id
        ]);

        $_SESSION['message'] = 'Comment posted successfully';
        redirect("/discussions/view.php?lesson_id=$lesson_id");

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

$pageTitle = "Discussion - {$lesson['title']}";

ob_start();
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
        <div class="mb-6">
            <h1 class="text-2xl font-bold dark:text-white">
                <?php echo htmlspecialchars($lesson['title']); ?>
            </h1>
            <p class="text-gray-600 dark:text-gray-400">
                <?php echo htmlspecialchars($lesson['course_title']); ?>
            </p>
        </div>

        <!-- New Discussion Form -->
        <form action="<?php echo BASE_URL; ?>/discussions/view.php?lesson_id=<?php echo $lesson_id; ?>" 
              method="POST"
              class="mb-8">
            
            <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">

            <div>
                <label for="content" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Start a Discussion
                </label>
                <textarea name="content" 
                          id="content" 
                          rows="3"
                          required
                          class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                          placeholder="Share your thoughts or ask a question..."></textarea>
            </div>

            <div class="mt-3 text-right">
                <button type="submit" 
                        class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Post Discussion
                </button>
            </div>
        </form>

        <!-- Discussions List -->
        <div class="space-y-6">
            <?php foreach ($discussions as $discussion): ?>
                <div class="border dark:border-gray-700 rounded-lg p-4" 
                     x-data="{ showReplyForm: false }">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <span class="font-medium dark:text-white">
                                <?php echo htmlspecialchars($discussion['username']); ?>
                            </span>
                            <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">
                                <?php echo timeAgo($discussion['created_at']); ?>
                            </span>
                        </div>
                        <button @click="showReplyForm = !showReplyForm"
                                class="text-blue-500 hover:text-blue-600">
                            Reply
                        </button>
                    </div>

                    <div class="prose dark:prose-invert max-w-none">
                        <?php echo nl2br(htmlspecialchars($discussion['content'])); ?>
                    </div>

                    <?php if ($discussion['reply_count'] > 0): ?>
                        <div class="mt-2">
                            <a href="<?php echo BASE_URL; ?>/discussions/replies.php?discussion_id=<?php echo $discussion['id']; ?>"
                               class="text-sm text-blue-500 hover:text-blue-600">
                                View <?php echo $discussion['reply_count']; ?> 
                                <?php echo $discussion['reply_count'] === 1 ? 'reply' : 'replies'; ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Reply Form -->
                    <div x-show="showReplyForm" 
                         x-cloak
                         class="mt-4 pl-4 border-l-2 border-gray-200 dark:border-gray-700">
                        <form action="<?php echo BASE_URL; ?>/discussions/view.php?lesson_id=<?php echo $lesson_id; ?>" 
                              method="POST">
                            
                            <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">
                            <input type="hidden" name="parent_id" value="<?php echo $discussion['id']; ?>">

                            <textarea name="content" 
                                      rows="2"
                                      required
                                      class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                      placeholder="Write your reply..."></textarea>

                            <div class="mt-2 text-right">
                                <button type="button" 
                                        @click="showReplyForm = false"
                                        class="mr-2 text-gray-600 dark:text-gray-400 hover:text-gray-800">
                                    Cancel
                                </button>
                                <button type="submit" 
                                        class="bg-blue-500 text-white px-3 py-1 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Post Reply
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($discussions)): ?>
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    No discussions yet. Be the first to start one!
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>
