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

$db = connectDB();

// Verify teacher owns this course
$stmt = $db->prepare("SELECT id, title, subject_id FROM courses WHERE id = ? AND teacher_id = ?");
$stmt->execute([$course_id, $_SESSION['user_id']]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    $_SESSION['error'] = 'Course not found or access denied';
    redirect('/dashboard/teacher.php');
}

// Get subject name
if ($course['subject_id']) {
    $stmt = $db->prepare("SELECT name FROM subjects WHERE id = ?");
    $stmt->execute([$course['subject_id']]);
    $subject = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get existing notes
$stmt = $db->prepare("SELECT * FROM notes WHERE course_id = ? AND teacher_id = ? ORDER BY created_at DESC");
$stmt->execute([$course_id, $_SESSION['user_id']]);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid form submission';
        redirect("/notes/manage.php?course_id=$course_id");
    }

    $validator = new FormValidator($_POST);
    $rules = [
        'title' => ['required'],
        'content' => ['required']
    ];

    if ($validator->validate($rules)) {
        $data = $validator->sanitize();
        
        try {
            $file_path = null;
            if (isset($_FILES['note_file']) && $_FILES['note_file']['error'] === UPLOAD_ERR_OK) {
                $uploader = new FileUploader('../uploads/notes', 10485760, // 10MB
                    ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
                $file_path = $uploader->upload($_FILES['note_file']);
                
                if (!$file_path) {
                    $_SESSION['error'] = implode('<br>', $uploader->getErrors());
                    redirect("/notes/manage.php?course_id=$course_id");
                }
            }

            $stmt = $db->prepare("
                INSERT INTO notes (course_id, teacher_id, title, content, file_path)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $course_id,
                $_SESSION['user_id'],
                $data['title'],
                $data['content'],
                $file_path
            ]);

            $_SESSION['message'] = 'Note created successfully';
            redirect("/notes/manage.php?course_id=$course_id");

        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to create note';
        }
    } else {
        $_SESSION['error'] = implode('<br>', array_merge(...array_values($validator->getErrors())));
    }
}

$pageTitle = "Manage Notes - {$course['title']}";

ob_start();
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($course['title']); ?> - Notes</h1>
        <?php if (isset($subject)): ?>
            <p class="text-gray-600">Subject: <?php echo htmlspecialchars($subject['name']); ?></p>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Create Note Form -->
        <div class="md:col-span-1">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-lg font-semibold mb-4">Create New Note</h2>
                
                <form action="<?php echo BASE_URL; ?>/notes/manage.php?course_id=<?php echo $course_id; ?>" 
                      method="POST"
                      enctype="multipart/form-data"
                      class="space-y-4">
                    
                    <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">

                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                        <input type="text" 
                               name="title" 
                               id="title" 
                               required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="content" class="block text-sm font-medium text-gray-700">Content</label>
                        <textarea name="content" 
                                  id="content" 
                                  rows="6" 
                                  required
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>

                    <div>
                        <label for="note_file" class="block text-sm font-medium text-gray-700">
                            Attachment (Optional)
                        </label>
                        <input type="file" 
                               id="note_file" 
                               name="note_file"
                               accept=".pdf,.doc,.docx"
                               class="mt-1 block w-full text-sm text-gray-500
                                      file:mr-4 file:py-2 file:px-4
                                      file:rounded-md file:border-0
                                      file:text-sm file:font-semibold
                                      file:bg-blue-50 file:text-blue-700
                                      hover:file:bg-blue-100">
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" 
                                class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Create Note
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Notes List -->
        <div class="md:col-span-2">
            <div class="bg-white rounded-lg shadow-md divide-y divide-gray-200">
                <?php if (empty($notes)): ?>
                    <div class="p-6 text-center text-gray-500">
                        No notes created yet
                    </div>
                <?php else: ?>
                    <?php foreach ($notes as $note): ?>
                        <div class="p-6">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="text-lg font-semibold"><?php echo htmlspecialchars($note['title']); ?></h3>
                                    <p class="text-sm text-gray-500 mt-1">
                                        Created: <?php echo date('F j, Y, g:i a', strtotime($note['created_at'])); ?>
                                    </p>
                                </div>
                                <div class="flex space-x-2">
                                    <?php if ($note['file_path']): ?>
                                        <a href="<?php echo BASE_URL; ?>/uploads/notes/<?php echo $note['file_path']; ?>" 
                                           target="_blank"
                                           class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-file-download"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?php echo BASE_URL; ?>/notes/edit.php?id=<?php echo $note['id']; ?>" 
                                       class="text-gray-600 hover:text-gray-800">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="mt-2 text-gray-600">
                                <?php echo nl2br(htmlspecialchars($note['content'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="mt-6">
        <a href="<?php echo BASE_URL; ?>/courses/view.php?id=<?php echo $course_id; ?>" 
           class="text-gray-600 hover:text-gray-800">
            <i class="fas fa-arrow-left"></i> Back to Course
        </a>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>
