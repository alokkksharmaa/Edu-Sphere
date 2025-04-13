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

// Verify teacher owns this course
$db = connectDB();
$stmt = $db->prepare("SELECT id, title FROM courses WHERE id = ? AND teacher_id = ?");
$stmt->execute([$course_id, $_SESSION['user_id']]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    $_SESSION['error'] = 'Course not found or access denied';
    redirect('/dashboard/teacher.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid form submission';
        redirect("/assignments/create.php?course_id=$course_id");
    }

    $validator = new FormValidator($_POST);
    $rules = [
        'title' => ['required'],
        'description' => ['required'],
        'due_date' => ['required']
    ];

    if ($validator->validate($rules)) {
        $data = $validator->sanitize();
        
        try {
            $file_path = null;
            if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
                $uploader = new FileUploader('../uploads/assignments', 10485760, // 10MB
                    ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
                $file_path = $uploader->upload($_FILES['assignment_file']);
                
                if (!$file_path) {
                    $_SESSION['error'] = implode('<br>', $uploader->getErrors());
                    redirect("/assignments/create.php?course_id=$course_id");
                }
            }

            $stmt = $db->prepare("
                INSERT INTO assignments (course_id, title, description, due_date, file_path)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $course_id,
                $data['title'],
                $data['description'],
                $data['due_date'],
                $file_path
            ]);

            $_SESSION['message'] = 'Assignment created successfully';
            redirect("/courses/view.php?id=$course_id");

        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to create assignment';
        }
    } else {
        $_SESSION['error'] = implode('<br>', array_merge(...array_values($validator->getErrors())));
    }
}

$pageTitle = "Create Assignment - {$course['title']}";

ob_start();
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold mb-6">Create Assignment for <?php echo htmlspecialchars($course['title']); ?></h1>

        <form action="<?php echo BASE_URL; ?>/assignments/create.php?course_id=<?php echo $course_id; ?>" 
              method="POST"
              enctype="multipart/form-data"
              class="space-y-6">
            
            <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">

            <div>
                <label for="title" class="block text-sm font-medium text-gray-700">Assignment Title</label>
                <input type="text" 
                       name="title" 
                       id="title" 
                       required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                <textarea name="description" 
                          id="description" 
                          rows="4" 
                          required
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
            </div>

            <div>
                <label for="due_date" class="block text-sm font-medium text-gray-700">Due Date</label>
                <input type="datetime-local" 
                       name="due_date" 
                       id="due_date" 
                       required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div>
                <label for="assignment_file" class="block text-sm font-medium text-gray-700">
                    Assignment File (Optional)
                </label>
                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                    <div class="space-y-1 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div class="flex text-sm text-gray-600">
                            <label for="assignment_file" class="relative cursor-pointer rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                <span>Upload a file</span>
                                <input id="assignment_file" 
                                       name="assignment_file" 
                                       type="file" 
                                       accept=".pdf,.doc,.docx"
                                       class="sr-only">
                            </label>
                            <p class="pl-1">or drag and drop</p>
                        </div>
                        <p class="text-xs text-gray-500">
                            PDF, DOC, DOCX up to 10MB
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <a href="<?php echo BASE_URL; ?>/courses/view.php?id=<?php echo $course_id; ?>" 
                   class="text-gray-600 hover:text-gray-800">
                    <i class="fas fa-arrow-left"></i> Back to Course
                </a>
                <button type="submit" 
                        class="bg-blue-500 text-white px-6 py-2 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Create Assignment
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>
