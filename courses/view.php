<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('/auth/login.php');
}

$course_id = $_GET['id'] ?? null;
if (!$course_id) {
    redirect('/courses');
}

$db = connectDB();

// Get course details
$stmt = $db->prepare("
    SELECT c.*, u.username as teacher_name,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrolled_students
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    WHERE c.id = ? AND (c.status = 'published' OR c.teacher_id = ?)
");
$stmt->execute([$course_id, $_SESSION['user_id']]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    $_SESSION['error'] = 'Course not found';
    redirect('/courses');
}

// Check if user is enrolled
$stmt = $db->prepare("SELECT * FROM enrollments WHERE student_id = ? AND course_id = ?");
$stmt->execute([$_SESSION['user_id'], $course_id]);
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'enroll' && isStudent()) {
        try {
            $stmt = $db->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], $course_id]);
            $_SESSION['message'] = 'Successfully enrolled in the course!';
            redirect('/courses/view.php?id=' . $course_id);
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to enroll in the course. Please try again.';
        }
    }
}

// Get course lessons
$stmt = $db->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_index");
$stmt->execute([$course_id]);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get course quizzes
$stmt = $db->prepare("
    SELECT q.*, 
           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count,
           (SELECT score FROM quiz_attempts WHERE student_id = ? AND quiz_id = q.id ORDER BY completed_at DESC LIMIT 1) as user_score
    FROM quizzes q
    WHERE q.course_id = ?
    ORDER BY q.created_at
");
$stmt->execute([$_SESSION['user_id'], $course_id]);
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = $course['title'];

ob_start();
?>

<div class="max-w-7xl mx-auto">
    <!-- Course Header -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="md:flex">
            <?php if ($course['thumbnail']): ?>
                <div class="md:w-1/3 mb-4 md:mb-0 md:mr-6">
                    <img src="<?php echo BASE_URL . '/' . $course['thumbnail']; ?>" 
                         alt="<?php echo htmlspecialchars($course['title']); ?>"
                         class="w-full h-auto rounded-lg">
                </div>
            <?php endif; ?>
            
            <div class="md:w-2/3">
                <h1 class="text-3xl font-bold mb-4"><?php echo htmlspecialchars($course['title']); ?></h1>
                
                <div class="mb-4">
                    <span class="text-gray-600">Instructor:</span>
                    <span class="font-semibold"><?php echo htmlspecialchars($course['teacher_name']); ?></span>
                </div>
                
                <div class="mb-4">
                    <span class="text-gray-600">Enrolled Students:</span>
                    <span class="font-semibold"><?php echo $course['enrolled_students']; ?></span>
                </div>
                
                <p class="text-gray-600 mb-6"><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                
                <?php if (isStudent()): ?>
                    <?php if ($enrollment): ?>
                        <div class="bg-green-100 text-green-700 px-4 py-2 rounded-md inline-block">
                            <i class="fas fa-check-circle"></i> You are enrolled in this course
                        </div>
                    <?php else: ?>
                        <form action="<?php echo BASE_URL; ?>/courses/view.php?id=<?php echo $course_id; ?>" 
                              method="POST">
                            <input type="hidden" name="action" value="enroll">
                            <button type="submit" 
                                    class="bg-blue-500 text-white px-6 py-2 rounded-md hover:bg-blue-600">
                                Enroll Now
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Course Content -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Lessons Section -->
        <div class="md:col-span-2">
            <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                <h2 class="text-xl font-bold mb-4">Course Lessons</h2>
                
                <?php if (empty($lessons)): ?>
                    <p class="text-gray-600">No lessons available yet.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($lessons as $index => $lesson): ?>
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="font-semibold">
                                            Lesson <?php echo $index + 1; ?>: <?php echo htmlspecialchars($lesson['title']); ?>
                                        </h3>
                                        <p class="text-sm text-gray-600">
                                            Type: <?php echo ucfirst($lesson['content_type']); ?>
                                        </p>
                                    </div>
                                    <?php if ($enrollment || isTeacher()): ?>
                                        <a href="<?php echo htmlspecialchars($lesson['content_url']); ?>" 
                                           target="_blank"
                                           class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                            View Lesson
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quizzes Section -->
            <?php if (!empty($quizzes)): ?>
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-bold mb-4">Course Quizzes</h2>
                    
                    <div class="space-y-4">
                        <?php foreach ($quizzes as $quiz): ?>
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="font-semibold"><?php echo htmlspecialchars($quiz['title']); ?></h3>
                                        <p class="text-sm text-gray-600">
                                            <?php echo $quiz['question_count']; ?> questions
                                            <?php if (isset($quiz['user_score'])): ?>
                                                • Last attempt: <?php echo $quiz['user_score']; ?>%
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <?php if ($enrollment || isTeacher()): ?>
                                        <a href="<?php echo BASE_URL; ?>/quizzes/take.php?id=<?php echo $quiz['id']; ?>" 
                                           class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                                            <?php echo isset($quiz['user_score']) ? 'Retake Quiz' : 'Start Quiz'; ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="md:col-span-1">
            <?php if ($enrollment): ?>
                <!-- Progress Card -->
                <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                    <h2 class="text-lg font-semibold mb-4">Your Progress</h2>
                    <div class="mb-4">
                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                            <span>Course Completion</span>
                            <span><?php echo $enrollment['progress']; ?>%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="bg-blue-500 h-2.5 rounded-full" style="width: <?php echo $enrollment['progress']; ?>%"></div>
                        </div>
                    </div>
                    <div class="text-sm text-gray-600">
                        <p>Enrolled on: <?php echo date('M j, Y', strtotime($enrollment['enrolled_at'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Course Stats -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-lg font-semibold mb-4">Course Information</h2>
                <div class="space-y-3">
                    <div>
                        <span class="text-gray-600">Total Lessons:</span>
                        <span class="font-semibold"><?php echo count($lessons); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-600">Total Quizzes:</span>
                        <span class="font-semibold"><?php echo count($quizzes); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-600">Last Updated:</span>
                        <span class="font-semibold"><?php echo date('M j, Y', strtotime($course['updated_at'])); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>
