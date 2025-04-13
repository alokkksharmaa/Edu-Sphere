<?php
require_once '../config/config.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('/auth/login.php');
}

$db = connectDB();

// Get teacher's courses
$stmt = $db->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count,
           (SELECT COUNT(*) FROM quizzes WHERE course_id = c.id) as quiz_count
    FROM courses c 
    WHERE c.teacher_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent enrollments
$stmt = $db->prepare("
    SELECT e.*, u.username as student_name, c.title as course_title
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN courses c ON e.course_id = c.id
    WHERE c.teacher_id = ?
    ORDER BY e.enrolled_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Teacher Dashboard';

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
                <p class="text-gray-600">Teacher</p>
            </div>
            <div class="mt-6">
                <h3 class="font-semibold mb-2">Quick Stats</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center p-3 bg-blue-50 rounded">
                        <div class="text-2xl font-bold text-blue-600"><?php echo count($courses); ?></div>
                        <div class="text-sm text-gray-600">Total Courses</div>
                    </div>
                    <div class="text-center p-3 bg-green-50 rounded">
                        <div class="text-2xl font-bold text-green-600">
                            <?php 
                            $total_students = array_sum(array_column($courses, 'student_count'));
                            echo $total_students;
                            ?>
                        </div>
                        <div class="text-sm text-gray-600">Total Students</div>
                    </div>
                </div>
            </div>
            <div class="mt-6">
                <a href="<?php echo BASE_URL; ?>/courses/create.php" 
                   class="block w-full bg-blue-500 text-white text-center py-2 px-4 rounded hover:bg-blue-600">
                    Create New Course
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content Section -->
    <div class="md:col-span-2">
        <!-- My Courses -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-semibold mb-4">My Courses</h2>
            <?php if (empty($courses)): ?>
                <p class="text-gray-600">You haven't created any courses yet.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-4">
                    <?php foreach ($courses as $course): ?>
                        <div class="border rounded-lg p-4 hover:shadow-md transition">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="font-semibold">
                                        <a href="<?php echo BASE_URL; ?>/courses/edit.php?id=<?php echo $course['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-800">
                                            <?php echo htmlspecialchars($course['title']); ?>
                                        </a>
                                    </h3>
                                    <p class="text-sm text-gray-600">
                                        Status: 
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                     <?php echo $course['status'] === 'published' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                            <?php echo ucfirst($course['status']); ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="text-right text-sm">
                                    <div><?php echo $course['student_count']; ?> Students</div>
                                    <div><?php echo $course['quiz_count']; ?> Quizzes</div>
                                </div>
                            </div>
                            <div class="mt-4 flex space-x-2">
                                <a href="<?php echo BASE_URL; ?>/courses/edit.php?id=<?php echo $course['id']; ?>" 
                                   class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="<?php echo BASE_URL; ?>/quizzes/create.php?course_id=<?php echo $course['id']; ?>" 
                                   class="text-green-600 hover:text-green-800">
                                    <i class="fas fa-plus"></i> Add Quiz
                                </a>
                                <a href="<?php echo BASE_URL; ?>/courses/students.php?id=<?php echo $course['id']; ?>" 
                                   class="text-purple-600 hover:text-purple-800">
                                    <i class="fas fa-users"></i> View Students
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Enrollments -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4">Recent Enrollments</h2>
            <?php if (empty($recent_enrollments)): ?>
                <p class="text-gray-600">No students have enrolled in your courses yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enrolled Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recent_enrollments as $enrollment): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo htmlspecialchars($enrollment['student_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo htmlspecialchars($enrollment['course_title']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-full bg-gray-200 rounded-full h-2.5 mr-2">
                                                <div class="bg-blue-500 h-2.5 rounded-full" style="width: <?php echo $enrollment['progress']; ?>%"></div>
                                            </div>
                                            <span class="text-sm"><?php echo $enrollment['progress']; ?>%</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y', strtotime($enrollment['enrolled_at'])); ?>
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
