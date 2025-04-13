<?php
require_once '../config/config.php';
require_once '../includes/utilities.php';

if (!isLoggedIn()) {
    redirect('/auth/login.php');
}

$db = connectDB();

// Get user's rank and stats
$stmt = $db->prepare("
    SELECT 
        up.*,
        (SELECT COUNT(*) + 1 
         FROM user_progress up2 
         WHERE up2.xp_points > up1.xp_points) as rank
    FROM user_progress up1
    WHERE up1.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get top 10 students
$stmt = $db->prepare("
    SELECT 
        u.username,
        up.xp_points,
        up.level,
        up.streak_days,
        COUNT(DISTINCT ub.badge_id) as badge_count
    FROM user_progress up
    JOIN users u ON up.user_id = u.id
    LEFT JOIN user_badges ub ON u.id = ub.user_id
    WHERE u.role = 'student'
    GROUP BY u.id
    ORDER BY up.xp_points DESC
    LIMIT 10
");
$stmt->execute();
$leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's recent achievements
$stmt = $db->prepare("
    SELECT b.*, ub.earned_at
    FROM badges b
    JOIN user_badges ub ON b.id = ub.badge_id
    WHERE ub.user_id = ?
    ORDER BY ub.earned_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available badges
$stmt = $db->prepare("
    SELECT b.*,
           CASE WHEN ub.user_id IS NOT NULL THEN 1 ELSE 0 END as earned
    FROM badges b
    LEFT JOIN user_badges ub ON b.id = ub.badge_id AND ub.user_id = ?
    ORDER BY b.xp_required ASC
");
$stmt->execute([$_SESSION['user_id']]);
$available_badges = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Leaderboard & Achievements";

ob_start();
?>

<div class="max-w-7xl mx-auto px-4">
    <!-- User Stats -->
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md mb-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Your Rank</h3>
                <p class="mt-1 text-3xl font-semibold text-blue-500">
                    #<?php echo $user_stats['rank']; ?>
                </p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Level</h3>
                <p class="mt-1 text-3xl font-semibold dark:text-white">
                    <?php echo $user_stats['level']; ?>
                </p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">XP Points</h3>
                <p class="mt-1 text-3xl font-semibold dark:text-white">
                    <?php echo number_format($user_stats['xp_points']); ?>
                </p>
            </div>
            <div>
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Streak</h3>
                <p class="mt-1 text-3xl font-semibold text-orange-500">
                    <?php echo $user_stats['streak_days']; ?> Days
                </p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Leaderboard -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md">
                <div class="p-6 border-b dark:border-gray-700">
                    <h2 class="text-xl font-semibold dark:text-white">Top Students</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-6">
                        <?php foreach ($leaderboard as $index => $student): ?>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <div class="w-8 text-center">
                                        <?php if ($index < 3): ?>
                                            <i class="fas fa-trophy text-2xl <?php 
                                                echo $index === 0 ? 'text-yellow-400' : 
                                                     ($index === 1 ? 'text-gray-400' : 'text-orange-400'); 
                                            ?>"></i>
                                        <?php else: ?>
                                            <span class="text-gray-600 dark:text-gray-400 font-medium">
                                                #<?php echo $index + 1; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <p class="font-medium dark:text-white">
                                            <?php echo htmlspecialchars($student['username']); ?>
                                            <?php if ($student['username'] === $_SESSION['username']): ?>
                                                <span class="text-blue-500 text-sm">(You)</span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            Level <?php echo $student['level']; ?> • 
                                            <?php echo $student['badge_count']; ?> badges
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold dark:text-white">
                                        <?php echo number_format($student['xp_points']); ?> XP
                                    </p>
                                    <?php if ($student['streak_days'] > 0): ?>
                                        <p class="text-sm text-orange-500">
                                            <?php echo $student['streak_days']; ?> day streak
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Achievements -->
        <div>
            <!-- Recent Achievements -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md mb-6">
                <div class="p-6 border-b dark:border-gray-700">
                    <h2 class="text-xl font-semibold dark:text-white">Recent Achievements</h2>
                </div>
                <div class="p-6">
                    <?php if (empty($recent_achievements)): ?>
                        <p class="text-gray-500 dark:text-gray-400 text-center">
                            No achievements yet. Keep learning to earn badges!
                        </p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recent_achievements as $achievement): ?>
                                <div class="flex items-center space-x-3">
                                    <img src="<?php echo $achievement['icon_path']; ?>" 
                                         alt="<?php echo htmlspecialchars($achievement['name']); ?>"
                                         class="w-10 h-10 rounded-full">
                                    <div>
                                        <p class="font-medium dark:text-white">
                                            <?php echo htmlspecialchars($achievement['name']); ?>
                                        </p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo timeAgo($achievement['earned_at']); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Available Badges -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md">
                <div class="p-6 border-b dark:border-gray-700">
                    <h2 class="text-xl font-semibold dark:text-white">Available Badges</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 gap-4">
                        <?php foreach ($available_badges as $badge): ?>
                            <div class="text-center group relative">
                                <img src="<?php echo $badge['icon_path']; ?>" 
                                     alt="<?php echo htmlspecialchars($badge['name']); ?>"
                                     class="w-16 h-16 mx-auto rounded-full <?php echo $badge['earned'] ? '' : 'filter grayscale'; ?>">
                                <p class="mt-2 font-medium dark:text-white">
                                    <?php echo htmlspecialchars($badge['name']); ?>
                                </p>
                                <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                                    <?php echo htmlspecialchars($badge['description']); ?><br>
                                    Required: <?php echo number_format($badge['xp_required']); ?> XP
                                </div>
                            </div>
                        <?php endforeach; ?>
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
