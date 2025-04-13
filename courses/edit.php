<?php
require_once '../config/config.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('/auth/login.php');
}

$course_id = $_GET['id'] ?? null;
if (!$course_id) {
    redirect('/dashboard');
}

$db = connectDB();

// Get course details
$stmt = $db->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
$stmt->execute([$course_id, $_SESSION['user_id']]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    $_SESSION['error'] = 'Course not found';
    redirect('/dashboard');
}

// Get course lessons
$stmt = $db->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_index");
$stmt->execute([$course_id]);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_course') {
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $status = $_POST['status'] ?? 'draft';
        
        if (empty($title) || empty($description)) {
            $_SESSION['error'] = 'Please fill in all required fields';
        } else {
            try {
                // Handle thumbnail upload
                $thumbnail = $course['thumbnail'];
                if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/thumbnails/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_extension = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png'];
                    
                    if (in_array($file_extension, $allowed_extensions)) {
                        // Delete old thumbnail if exists
                        if ($thumbnail && file_exists('../' . $thumbnail)) {
                            unlink('../' . $thumbnail);
                        }
                        
                        $filename = uniqid() . '.' . $file_extension;
                        $thumbnail = 'uploads/thumbnails/' . $filename;
                        move_uploaded_file($_FILES['thumbnail']['tmp_name'], $upload_dir . $filename);
                    }
                }
                
                // Update course
                $stmt = $db->prepare("
                    UPDATE courses 
                    SET title = ?, description = ?, thumbnail = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ? AND teacher_id = ?
                ");
                $stmt->execute([$title, $description, $thumbnail, $status, $course_id, $_SESSION['user_id']]);
                
                $_SESSION['message'] = 'Course updated successfully!';
                redirect('/courses/edit.php?id=' . $course_id);
                
            } catch (PDOException $e) {
                $_SESSION['error'] = 'Failed to update course. Please try again.';
            }
        }
    } elseif ($action === 'add_lesson') {
        $lesson_title = $_POST['lesson_title'] ?? '';
        $content_type = $_POST['content_type'] ?? '';
        $content_url = $_POST['content_url'] ?? '';
        
        if (empty($lesson_title) || empty($content_type) || empty($content_url)) {
            $_SESSION['error'] = 'Please fill in all lesson fields';
        } else {
            try {
                // Get the highest order_index
                $stmt = $db->prepare("SELECT MAX(order_index) as max_order FROM lessons WHERE course_id = ?");
                $stmt->execute([$course_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $new_order = ($result['max_order'] ?? 0) + 1;
                
                // Add lesson
                $stmt = $db->prepare("
                    INSERT INTO lessons (course_id, title, content_type, content_url, order_index)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$course_id, $lesson_title, $content_type, $content_url, $new_order]);
                
                $_SESSION['message'] = 'Lesson added successfully!';
                redirect('/courses/edit.php?id=' . $course_id);
                
            } catch (PDOException $e) {
                $_SESSION['error'] = 'Failed to add lesson. Please try again.';
            }
        }
    }
}

$pageTitle = 'Edit Course';

ob_start();
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h1 class="text-2xl font-bold mb-6">Edit Course</h1>
        
        <form action="<?php echo BASE_URL; ?>/courses/edit.php?id=<?php echo $course_id; ?>" 
              method="POST" 
              enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_course">
            
            <div class="space-y-6">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700">Course Title</label>
                    <input type="text" id="title" name="title" required
                           value="<?php echo htmlspecialchars($course['title']); ?>"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Course Description</label>
                    <textarea id="description" name="description" rows="4" required
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?php echo htmlspecialchars($course['description']); ?></textarea>
                </div>

                <div>
                    <label for="thumbnail" class="block text-sm font-medium text-gray-700">Course Thumbnail</label>
                    <?php if ($course['thumbnail']): ?>
                        <div class="mt-2 mb-4">
                            <img src="<?php echo BASE_URL . '/' . $course['thumbnail']; ?>" 
                                 alt="Current thumbnail" 
                                 class="w-48 h-auto rounded">
                        </div>
                    <?php endif; ?>
                    <input type="file" id="thumbnail" name="thumbnail" accept="image/jpeg,image/png"
                           class="mt-1 block w-full text-sm text-gray-500
                                  file:mr-4 file:py-2 file:px-4
                                  file:rounded-md file:border-0
                                  file:text-sm file:font-semibold
                                  file:bg-blue-50 file:text-blue-700
                                  hover:file:bg-blue-100">
                    <p class="mt-1 text-sm text-gray-500">Leave empty to keep current thumbnail</p>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Course Status</label>
                    <select id="status" name="status" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="draft" <?php echo $course['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo $course['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                    </select>
                </div>

                <div class="flex items-center justify-end space-x-4">
                    <a href="<?php echo BASE_URL; ?>/dashboard" 
                       class="bg-gray-100 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-200">
                        Back to Dashboard
                    </a>
                    <button type="submit" 
                            class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                        Update Course
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Lessons Section -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-bold mb-6">Course Lessons</h2>
        
        <!-- Add Lesson Form -->
        <form action="<?php echo BASE_URL; ?>/courses/edit.php?id=<?php echo $course_id; ?>" 
              method="POST" 
              class="mb-8 p-4 bg-gray-50 rounded-lg">
            <input type="hidden" name="action" value="add_lesson">
            
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label for="lesson_title" class="block text-sm font-medium text-gray-700">Lesson Title</label>
                    <input type="text" id="lesson_title" name="lesson_title" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="content_type" class="block text-sm font-medium text-gray-700">Content Type</label>
                    <select id="content_type" name="content_type" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select Type</option>
                        <option value="pdf">PDF</option>
                        <option value="video">Video</option>
                    </select>
                </div>
                
                <div>
                    <label for="content_url" class="block text-sm font-medium text-gray-700">Content URL</label>
                    <input type="url" id="content_url" name="content_url" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                           placeholder="https://example.com/content">
                    <p class="mt-1 text-sm text-gray-500">Enter the URL for your PDF or video content</p>
                </div>
                
                <div>
                    <button type="submit" 
                            class="w-full bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600">
                        Add Lesson
                    </button>
                </div>
            </div>
        </form>
        
        <!-- Lessons List -->
        <?php if (empty($lessons)): ?>
            <p class="text-gray-600">No lessons added yet.</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($lessons as $lesson): ?>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div>
                            <h3 class="font-semibold"><?php echo htmlspecialchars($lesson['title']); ?></h3>
                            <p class="text-sm text-gray-600">
                                Type: <?php echo ucfirst($lesson['content_type']); ?>
                            </p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <a href="<?php echo htmlspecialchars($lesson['content_url']); ?>" 
                               target="_blank"
                               class="text-blue-600 hover:text-blue-800">
                                <i class="fas fa-external-link-alt"></i> View
                            </a>
                            <form action="<?php echo BASE_URL; ?>/courses/delete_lesson.php" 
                                  method="POST" 
                                  class="inline"
                                  onsubmit="return confirm('Are you sure you want to delete this lesson?');">
                                <input type="hidden" name="lesson_id" value="<?php echo $lesson['id']; ?>">
                                <button type="submit" class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>
