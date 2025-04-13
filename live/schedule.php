<?php
require_once '../config/config.php';
require_once '../includes/utilities.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('/auth/login.php');
}

$course_id = $_GET['course_id'] ?? null;
if (!$course_id) {
    redirect('/dashboard/teacher.php');
}

$db = connectDB();

// Verify teacher owns this course
$stmt = $db->prepare("SELECT id, title FROM courses WHERE id = ? AND teacher_id = ?");
$stmt->execute([$course_id, $_SESSION['user_id']]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    $_SESSION['error'] = 'Course not found or access denied';
    redirect('/dashboard/teacher.php');
}

// Get upcoming live classes
$stmt = $db->prepare("
    SELECT l.*, COUNT(a.student_id) as attendee_count
    FROM live_classes l
    LEFT JOIN live_class_attendees a ON l.id = a.live_class_id
    WHERE l.course_id = ?
    AND l.start_time >= NOW()
    GROUP BY l.id
    ORDER BY l.start_time ASC
");
$stmt->execute([$course_id]);
$upcoming_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid form submission';
        redirect("/live/schedule.php?course_id=$course_id");
    }

    try {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $start_time = $_POST['date'] . ' ' . $_POST['time'];
        $duration = $_POST['duration'];
        $meeting_link = $_POST['meeting_link'];

        // Validate start time is in future
        if (strtotime($start_time) <= time()) {
            throw new Exception('Start time must be in the future');
        }

        // Create live class
        $stmt = $db->prepare("
            INSERT INTO live_classes (course_id, teacher_id, title, description, start_time, duration, meeting_link)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $course_id,
            $_SESSION['user_id'],
            $title,
            $description,
            $start_time,
            $duration,
            $meeting_link
        ]);

        // Notify enrolled students (you would implement this)
        notifyStudentsAboutLiveClass($course_id, $title, $start_time);

        $_SESSION['message'] = 'Live class scheduled successfully';
        redirect("/live/schedule.php?course_id=$course_id");

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

$pageTitle = "Schedule Live Class - {$course['title']}";

ob_start();
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold mb-6 dark:text-white">
            Schedule Live Class for <?php echo htmlspecialchars($course['title']); ?>
        </h1>

        <!-- Upcoming Classes -->
        <?php if (!empty($upcoming_classes)): ?>
            <div class="mb-8">
                <h2 class="text-xl font-semibold mb-4 dark:text-white">Upcoming Classes</h2>
                <div class="space-y-4">
                    <?php foreach ($upcoming_classes as $class): ?>
                        <div class="border dark:border-gray-700 p-4 rounded-lg">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="font-medium dark:text-white">
                                        <?php echo htmlspecialchars($class['title']); ?>
                                    </h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        <?php echo date('F j, Y g:i A', strtotime($class['start_time'])); ?>
                                        (<?php echo $class['duration']; ?> minutes)
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                        <?php echo $class['attendee_count']; ?> students registered
                                    </p>
                                </div>
                                <a href="<?php echo htmlspecialchars($class['meeting_link']); ?>" 
                                   target="_blank"
                                   class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 text-sm">
                                    Join Meeting
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Schedule Form -->
        <form action="<?php echo BASE_URL; ?>/live/schedule.php?course_id=<?php echo $course_id; ?>" 
              method="POST"
              class="space-y-6">
            
            <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">

            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Class Title
                </label>
                <input type="text" 
                       name="title" 
                       id="title" 
                       required
                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Description
                </label>
                <textarea name="description" 
                          id="description" 
                          rows="3"
                          class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Date
                    </label>
                    <input type="date" 
                           name="date" 
                           id="date" 
                           required
                           min="<?php echo date('Y-m-d'); ?>"
                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label for="time" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Time
                    </label>
                    <input type="time" 
                           name="time" 
                           id="time" 
                           required
                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>

            <div>
                <label for="duration" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Duration (minutes)
                </label>
                <select name="duration" 
                        id="duration" 
                        required
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="30">30 minutes</option>
                    <option value="45">45 minutes</option>
                    <option value="60" selected>1 hour</option>
                    <option value="90">1.5 hours</option>
                    <option value="120">2 hours</option>
                </select>
            </div>

            <div>
                <label for="meeting_link" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Meeting Link
                </label>
                <input type="url" 
                       name="meeting_link" 
                       id="meeting_link" 
                       required
                       placeholder="https://meet.google.com/..."
                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div class="flex items-center justify-between">
                <a href="<?php echo BASE_URL; ?>/courses/view.php?id=<?php echo $course_id; ?>" 
                   class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                    <i class="fas fa-arrow-left"></i> Back to Course
                </a>
                <button type="submit" 
                        class="bg-blue-500 text-white px-6 py-2 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Schedule Class
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>
