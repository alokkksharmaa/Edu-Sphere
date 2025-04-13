<?php
require_once 'config/config.php';

$pageTitle = 'Welcome';

ob_start();
?>

<!-- Hero Section -->
<div class="bg-gradient-to-r from-blue-500 to-blue-700 text-white py-20">
    <div class="max-w-7xl mx-auto px-4">
        <div class="text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-6">World-Class Education, Free for Everyone</h1>
            <p class="text-xl md:text-2xl mb-8">Join our community of learners and educators to transform your future</p>
            <div class="space-x-4">
                <a href="<?php echo BASE_URL; ?>/auth/register.php" class="bg-white text-blue-700 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition">Get Started</a>
                <a href="<?php echo BASE_URL; ?>/courses" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-blue-700 transition">Browse Courses</a>
            </div>
        </div>
    </div>
</div>

<!-- Features Section -->
<div class="py-16">
    <div class="max-w-7xl mx-auto px-4">
        <h2 class="text-3xl font-bold text-center mb-12">Why Choose Live School?</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="text-center p-6 bg-white rounded-lg shadow-lg">
                <div class="text-4xl text-blue-500 mb-4">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h3 class="text-xl font-semibold mb-4">Quality Education</h3>
                <p class="text-gray-600">Access high-quality courses created by experienced educators from around the world.</p>
            </div>
            <div class="text-center p-6 bg-white rounded-lg shadow-lg">
                <div class="text-4xl text-blue-500 mb-4">
                    <i class="fas fa-clock"></i>
                </div>
                <h3 class="text-xl font-semibold mb-4">Learn at Your Pace</h3>
                <p class="text-gray-600">Study whenever and wherever you want with our flexible learning platform.</p>
            </div>
            <div class="text-center p-6 bg-white rounded-lg shadow-lg">
                <div class="text-4xl text-blue-500 mb-4">
                    <i class="fas fa-certificate"></i>
                </div>
                <h3 class="text-xl font-semibold mb-4">Track Progress</h3>
                <p class="text-gray-600">Monitor your learning journey with detailed progress tracking and assessments.</p>
            </div>
        </div>
    </div>
</div>

<!-- Latest Courses Section -->
<div class="bg-gray-100 py-16">
    <div class="max-w-7xl mx-auto px-4">
        <h2 class="text-3xl font-bold text-center mb-12">Latest Courses</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <?php
            $db = connectDB();
            $stmt = $db->query("SELECT c.*, u.username as teacher_name 
                               FROM courses c 
                               JOIN users u ON c.teacher_id = u.id 
                               WHERE c.status = 'published' 
                               ORDER BY c.created_at DESC LIMIT 3");
            $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($courses as $course): ?>
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <img src="<?php echo $course['thumbnail'] ?? BASE_URL . '/assets/images/default-course.jpg'; ?>" 
                         alt="<?php echo htmlspecialchars($course['title']); ?>" 
                         class="w-full h-48 object-cover">
                    <div class="p-6">
                        <h3 class="text-xl font-semibold mb-2"><?php echo htmlspecialchars($course['title']); ?></h3>
                        <p class="text-gray-600 mb-4"><?php echo htmlspecialchars(substr($course['description'], 0, 100)) . '...'; ?></p>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-500">By <?php echo htmlspecialchars($course['teacher_name']); ?></span>
                            <a href="<?php echo BASE_URL . '/courses/view.php?id=' . $course['id']; ?>" 
                               class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition">
                                Learn More
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-8">
            <a href="<?php echo BASE_URL; ?>/courses" class="bg-blue-500 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-600 transition">
                View All Courses
            </a>
        </div>
    </div>
</div>

<!-- Call to Action -->
<div class="py-16">
    <div class="max-w-7xl mx-auto px-4 text-center">
        <h2 class="text-3xl font-bold mb-6">Ready to Start Learning?</h2>
        <p class="text-xl text-gray-600 mb-8">Join thousands of students already learning on Live School</p>
        <a href="<?php echo BASE_URL; ?>/auth/register.php" class="bg-blue-500 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-600 transition">
            Create Free Account
        </a>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?>
