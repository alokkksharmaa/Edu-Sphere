<?php
require_once '../config/config.php';
require_once '../includes/utilities.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('/auth/login.php');
}

$db = connectDB();

// Get user progress and level
$stmt = $db->prepare("
    SELECT * FROM user_progress
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$progress = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$progress) {
    // Initialize progress if not exists
    $stmt = $db->prepare("
        INSERT INTO user_progress (user_id)
        VALUES (?)
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $progress = [
        'xp_points' => 0,
        'level' => 1,
        'streak_days' => 0
    ];
}

// Get earned badges
$stmt = $db->prepare("
    SELECT b.* FROM badges b
    JOIN user_badges ub ON b.id = ub.badge_id
    WHERE ub.user_id = ?
    ORDER BY ub.earned_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$badges = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get course completion stats
$stmt = $db->prepare("
    SELECT 
        c.title,
        COUNT(l.id) as total_lessons,
        COUNT(vp.id) as completed_lessons,
        (COUNT(vp.id) * 100.0 / COUNT(l.id)) as completion_percentage
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    JOIN lessons l ON c.id = l.course_id
    LEFT JOIN video_progress vp ON l.id = vp.lesson_id AND vp.user_id = ? AND vp.is_completed = 1
    WHERE e.student_id = ?
    GROUP BY c.id
    ORDER BY completion_percentage DESC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$course_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get quiz performance
$stmt = $db->prepare("
    SELECT 
        q.title,
        qa.score,
        qa.completed_at
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE qa.student_id = ?
    ORDER BY qa.completed_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$quiz_attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get activity streak data
$stmt = $db->prepare("
    SELECT DATE(vp.last_watched) as activity_date,
           COUNT(DISTINCT l.id) as lessons_watched
    FROM video_progress vp
    JOIN lessons l ON vp.lesson_id = l.id
    WHERE vp.user_id = ?
    AND vp.last_watched >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(vp.last_watched)
    ORDER BY activity_date ASC
");
$stmt->execute([$_SESSION['user_id']]);
$activity_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Learning Analytics";

ob_start();
?>

<div class="max-w-7xl mx-auto px-4">
    <!-- Progress Overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Level & XP -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4 dark:text-white">Level Progress</h2>
            <div class="flex items-center justify-between mb-2">
                <span class="text-3xl font-bold text-blue-500">Level <?php echo $progress['level']; ?></span>
                <span class="text-gray-600 dark:text-gray-400"><?php echo $progress['xp_points']; ?> XP</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                <div class="bg-blue-500 h-2.5 rounded-full" style="width: <?php echo ($progress['xp_points'] % 1000) / 10; ?>%"></div>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                <?php echo 1000 - ($progress['xp_points'] % 1000); ?> XP until next level
            </p>
        </div>

        <!-- Learning Streak -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4 dark:text-white">Learning Streak</h2>
            <div class="flex items-center space-x-2">
                <i class="fas fa-fire text-3xl text-orange-500"></i>
                <span class="text-3xl font-bold dark:text-white">
                    <?php echo $progress['streak_days']; ?> Days
                </span>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                Keep learning daily to maintain your streak!
            </p>
        </div>

        <!-- Badges -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4 dark:text-white">Earned Badges</h2>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($badges as $badge): ?>
                    <div class="relative group">
                        <img src="<?php echo $badge['icon_path']; ?>" 
                             alt="<?php echo htmlspecialchars($badge['name']); ?>"
                             class="w-12 h-12 rounded-full">
                        <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                            <?php echo htmlspecialchars($badge['name']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Course Progress -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4 dark:text-white">Course Progress</h2>
            <div class="space-y-4">
                <?php foreach ($course_stats as $course): ?>
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-medium dark:text-white">
                                <?php echo htmlspecialchars($course['title']); ?>
                            </span>
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                <?php echo $course['completed_lessons']; ?>/<?php echo $course['total_lessons']; ?> lessons
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                            <div class="bg-green-500 h-2.5 rounded-full" 
                                 style="width: <?php echo $course['completion_percentage']; ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Quiz Performance -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4 dark:text-white">Recent Quiz Scores</h2>
            <div class="relative" style="height: 300px;">
                <canvas id="quizChart"></canvas>
            </div>
        </div>

        <!-- Activity Calendar -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md lg:col-span-2">
            <h2 class="text-xl font-semibold mb-4 dark:text-white">Learning Activity</h2>
            <div class="relative" style="height: 200px;">
                <canvas id="activityChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Quiz Performance Chart
    const quizData = <?php echo json_encode($quiz_attempts); ?>;
    new Chart(document.getElementById('quizChart'), {
        type: 'line',
        data: {
            labels: quizData.map(q => q.title),
            datasets: [{
                label: 'Quiz Score',
                data: quizData.map(q => q.score),
                borderColor: 'rgb(59, 130, 246)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });

    // Activity Calendar Chart
    const activityData = <?php echo json_encode($activity_data); ?>;
    new Chart(document.getElementById('activityChart'), {
        type: 'bar',
        data: {
            labels: activityData.map(d => d.activity_date),
            datasets: [{
                label: 'Lessons Completed',
                data: activityData.map(d => d.lessons_watched),
                backgroundColor: 'rgb(34, 197, 94)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>
