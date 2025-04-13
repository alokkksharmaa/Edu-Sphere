<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('/auth/login.php');
}

$db = connectDB();

// Get search parameters
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Build query
$query = "
    SELECT c.*, u.username as teacher_name,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrolled_students
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    WHERE c.status = 'published'
";
$params = [];

if ($search) {
    $query .= " AND (c.title LIKE ? OR c.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Get total courses for pagination
$count_stmt = $db->prepare(str_replace("c.*, u.username as teacher_name,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrolled_students", "COUNT(*) as total", $query));
$count_stmt->execute($params);
$total_courses = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_courses / $per_page);

// Get courses for current page
$query .= " ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

$stmt = $db->prepare($query);
$stmt->execute($params);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Browse Courses';

ob_start();
?>

<div class="max-w-7xl mx-auto">
    <!-- Search and Filters -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <form action="<?php echo BASE_URL; ?>/courses" method="GET" class="flex gap-4">
            <div class="flex-1">
                <input type="text" 
                       name="search" 
                       value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Search courses..."
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <button type="submit" 
                    class="bg-blue-500 text-white px-6 py-2 rounded-md hover:bg-blue-600">
                Search
            </button>
        </form>
    </div>

    <!-- Courses Grid -->
    <?php if (empty($courses)): ?>
        <div class="bg-white p-6 rounded-lg shadow-md text-center">
            <p class="text-gray-600 mb-4">No courses found.</p>
            <?php if ($search): ?>
                <a href="<?php echo BASE_URL; ?>/courses" class="text-blue-500 hover:text-blue-600">
                    Clear search and show all courses
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
            <?php foreach ($courses as $course): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition">
                    <?php if ($course['thumbnail']): ?>
                        <img src="<?php echo BASE_URL . '/' . $course['thumbnail']; ?>" 
                             alt="<?php echo htmlspecialchars($course['title']); ?>"
                             class="w-full h-48 object-cover">
                    <?php else: ?>
                        <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                            <i class="fas fa-book text-4xl text-gray-400"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div class="p-6">
                        <h2 class="text-xl font-semibold mb-2">
                            <a href="<?php echo BASE_URL; ?>/courses/view.php?id=<?php echo $course['id']; ?>" 
                               class="text-blue-600 hover:text-blue-800">
                                <?php echo htmlspecialchars($course['title']); ?>
                            </a>
                        </h2>
                        
                        <p class="text-gray-600 mb-4">
                            <?php echo htmlspecialchars(substr($course['description'], 0, 100)) . '...'; ?>
                        </p>
                        
                        <div class="flex items-center justify-between text-sm text-gray-500 mb-4">
                            <div>
                                <i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($course['teacher_name']); ?>
                            </div>
                            <div>
                                <i class="fas fa-users"></i>
                                <?php echo $course['enrolled_students']; ?> students
                            </div>
                        </div>
                        
                        <a href="<?php echo BASE_URL; ?>/courses/view.php?id=<?php echo $course['id']; ?>" 
                           class="block w-full text-center bg-blue-500 text-white py-2 rounded hover:bg-blue-600 transition">
                            View Course
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6 rounded-lg shadow-md">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo BASE_URL; ?>/courses?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Previous
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo BASE_URL; ?>/courses?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                           class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing
                            <span class="font-medium"><?php echo $offset + 1; ?></span>
                            to
                            <span class="font-medium"><?php echo min($offset + $per_page, $total_courses); ?></span>
                            of
                            <span class="font-medium"><?php echo $total_courses; ?></span>
                            results
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                                <a href="<?php echo BASE_URL; ?>/courses?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Previous</span>
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            if ($start > 1) {
                                echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                            }
                            
                            for ($i = $start; $i <= $end; $i++) {
                                if ($i == $page) {
                                    echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-blue-50 text-sm font-medium text-blue-600">' . $i . '</span>';
                                } else {
                                    echo '<a href="' . BASE_URL . '/courses?page=' . $i . ($search ? '&search=' . urlencode($search) : '') . '" 
                                             class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $i . '</a>';
                                }
                            }
                            
                            if ($end < $total_pages) {
                                echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                            }
                            ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="<?php echo BASE_URL; ?>/courses?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Next</span>
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>
