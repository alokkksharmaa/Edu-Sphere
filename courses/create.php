<?php
require_once '../config/config.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('/auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? 'draft';
    
    if (empty($title) || empty($description)) {
        $_SESSION['error'] = 'Please fill in all required fields';
    } else {
        try {
            $db = connectDB();
            
            // Handle thumbnail upload
            $thumbnail = null;
            if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/thumbnails/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $filename = uniqid() . '.' . $file_extension;
                    $thumbnail = 'uploads/thumbnails/' . $filename;
                    move_uploaded_file($_FILES['thumbnail']['tmp_name'], $upload_dir . $filename);
                }
            }
            
            // Create course
            $stmt = $db->prepare("
                INSERT INTO courses (teacher_id, title, description, thumbnail, status) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $title, $description, $thumbnail, $status]);
            
            $course_id = $db->lastInsertId();
            $_SESSION['message'] = 'Course created successfully!';
            redirect('/courses/edit.php?id=' . $course_id);
            
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to create course. Please try again.';
        }
    }
}

$pageTitle = 'Create Course';

ob_start();
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold mb-6">Create New Course</h1>
        
        <form action="<?php echo BASE_URL; ?>/courses/create.php" method="POST" enctype="multipart/form-data">
            <div class="space-y-6">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700">Course Title</label>
                    <input type="text" id="title" name="title" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Course Description</label>
                    <textarea id="description" name="description" rows="4" required
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                </div>

                <div>
                    <label for="thumbnail" class="block text-sm font-medium text-gray-700">Course Thumbnail</label>
                    <input type="file" id="thumbnail" name="thumbnail" accept="image/jpeg,image/png"
                           class="mt-1 block w-full text-sm text-gray-500
                                  file:mr-4 file:py-2 file:px-4
                                  file:rounded-md file:border-0
                                  file:text-sm file:font-semibold
                                  file:bg-blue-50 file:text-blue-700
                                  hover:file:bg-blue-100">
                    <p class="mt-1 text-sm text-gray-500">Recommended size: 1280x720 pixels (16:9 ratio)</p>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Course Status</label>
                    <select id="status" name="status" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>

                <div class="flex items-center justify-end space-x-4">
                    <a href="<?php echo BASE_URL; ?>/dashboard" 
                       class="bg-gray-100 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-200">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                        Create Course
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>
