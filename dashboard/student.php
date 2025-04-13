<?php
require_once '../config/config.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('/auth/login.php');
}

$db = connectDB();

// Get enrolled courses
$stmt = $db->prepare("
    SELECT c.*, u.username as teacher_name, e.progress 
    FROM enrollments e 
    JOIN courses c ON e.course_id = c.id 
    JOIN users u ON c.teacher_id = u.id 
    WHERE e.student_id = ?
    ORDER BY e.enrolled_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$enrolled_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent quiz attempts
$stmt = $db->prepare("
    SELECT qa.*, q.title as quiz_title, c.title as course_title 
    FROM quiz_attempts qa 
    JOIN quizzes q ON qa.quiz_id = q.id 
    JOIN courses c ON q.course_id = c.id 
    WHERE qa.student_id = ? 
    ORDER BY qa.completed_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Student Dashboard';

ob_start();
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- Profile Section -->
    <div class="md:col-span-1">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="text-center">
                <div class="w-24 h-24 rounded-full bg-blue-500 text-white flex items-center justify-center text-3xl mx-auto mb-4">
                    <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                </div>
                <h2 class="text-xl font-semibold"><?php echo htmlspecialchars($_SESSION['username']); ?></h2>
                <p class="text-gray-600">Student</p>
            </div>
            <div class="mt-6">
                <h3 class="font-semibold mb-2">Quick Stats</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center p-3 bg-blue-50 rounded">
                        <div class="text-2xl font-bold text-blue-600"><?php echo count($enrolled_courses); ?></div>
                        <div class="text-sm text-gray-600">Enrolled Courses</div>
                    </div>
                    <div class="text-center p-3 bg-green-50 rounded">
                        <div class="text-2xl font-bold text-green-600"><?php echo count($recent_quizzes); ?></div>
                        <div class="text-sm text-gray-600">Completed Quizzes</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Section -->
    <div class="md:col-span-2">
        <!-- Enrolled Courses -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-semibold mb-4">My Courses</h2>
            <?php if (empty($enrolled_courses)): ?>
                <p class="text-gray-600">You haven't enrolled in any courses yet.</p>
                <a href="<?php echo BASE_URL; ?>/courses" class="inline-block mt-4 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Browse Courses
                </a>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-4">
                    <?php foreach ($enrolled_courses as $course): ?>
                        <div class="border rounded-lg p-4 hover:shadow-md transition">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="font-semibold">
                                        <a href="<?php echo BASE_URL; ?>/courses/view.php?id=<?php echo $course['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-800">
                                            <?php echo htmlspecialchars($course['title']); ?>
                                        </a>
                                    </h3>
                                    <p class="text-sm text-gray-600">By <?php echo htmlspecialchars($course['teacher_name']); ?></p>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-semibold">
                                        Progress: <?php echo $course['progress']; ?>%
                                    </div>
                                    <div class="w-24 h-2 bg-gray-200 rounded-full mt-1">
                                        <div class="h-full bg-blue-500 rounded-full" 
                                             style="width: <?php echo $course['progress']; ?>%">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Quiz Results -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4">Recent Quiz Results</h2>
            <?php if (empty($recent_quizzes)): ?>
                <p class="text-gray-600">You haven't taken any quizzes yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quiz</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recent_quizzes as $quiz): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo htmlspecialchars($quiz['quiz_title']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo htmlspecialchars($quiz['course_title']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                   <?php echo $quiz['score'] >= 70 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $quiz['score']; ?>%
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y', strtotime($quiz['completed_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>
