<?php
require_once '../config/config.php';
require_once '../includes/utilities.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('/auth/login.php');
}

$assignment_id = $_GET['id'] ?? null;
if (!$assignment_id) {
    redirect('/dashboard/student.php');
}

$db = connectDB();

// Get assignment details and verify enrollment
$stmt = $db->prepare("
    SELECT a.*, c.title as course_title, c.id as course_id
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id
    WHERE a.id = ? AND e.student_id = ?
");
$stmt->execute([$assignment_id, $_SESSION['user_id']]);
$assignment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assignment) {
    $_SESSION['error'] = 'Assignment not found or you are not enrolled in this course';
    redirect('/dashboard/student.php');
}

// Check if already submitted
$stmt = $db->prepare("SELECT * FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?");
$stmt->execute([$assignment_id, $_SESSION['user_id']]);
$existing_submission = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid form submission';
        redirect("/assignments/submit.php?id=$assignment_id");
    }

    $validator = new FormValidator($_POST);
    $rules = [
        'submission_text' => ['required']
    ];

    if ($validator->validate($rules)) {
        $data = $validator->sanitize();
        
        try {
            $file_path = null;
            if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
                $uploader = new FileUploader('../uploads/submissions', 10485760, // 10MB
                    ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
                $file_path = $uploader->upload($_FILES['submission_file']);
                
                if (!$file_path) {
                    $_SESSION['error'] = implode('<br>', $uploader->getErrors());
                    redirect("/assignments/submit.php?id=$assignment_id");
                }
            }

            if ($existing_submission) {
                $stmt = $db->prepare("
                    UPDATE assignment_submissions 
                    SET submission_text = ?, file_path = ?, submitted_at = CURRENT_TIMESTAMP
                    WHERE assignment_id = ? AND student_id = ?
                ");
                $stmt->execute([
                    $data['submission_text'],
                    $file_path ?? $existing_submission['file_path'],
                    $assignment_id,
                    $_SESSION['user_id']
                ]);
                $_SESSION['message'] = 'Assignment submission updated successfully';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO assignment_submissions (assignment_id, student_id, submission_text, file_path)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $assignment_id,
                    $_SESSION['user_id'],
                    $data['submission_text'],
                    $file_path
                ]);
                $_SESSION['message'] = 'Assignment submitted successfully';
            }

            redirect("/courses/view.php?id={$assignment['course_id']}");

        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to submit assignment';
        }
    } else {
        $_SESSION['error'] = implode('<br>', array_merge(...array_values($validator->getErrors())));
    }
}

$pageTitle = "Submit Assignment - {$assignment['title']}";

ob_start();
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="mb-6">
            <h1 class="text-2xl font-bold mb-2"><?php echo htmlspecialchars($assignment['title']); ?></h1>
            <p class="text-gray-600 mb-4"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
            <div class="flex justify-between text-sm text-gray-500">
                <p>Course: <?php echo htmlspecialchars($assignment['course_title']); ?></p>
                <p>Due: <?php echo date('F j, Y, g:i a', strtotime($assignment['due_date'])); ?></p>
            </div>
            <?php if ($assignment['file_path']): ?>
                <div class="mt-4">
                    <a href="<?php echo BASE_URL; ?>/uploads/assignments/<?php echo $assignment['file_path']; ?>" 
                       target="_blank"
                       class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-file-download"></i> Download Assignment File
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <form action="<?php echo BASE_URL; ?>/assignments/submit.php?id=<?php echo $assignment_id; ?>" 
              method="POST"
              enctype="multipart/form-data"
              class="space-y-6">
            
            <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">

            <div>
                <label for="submission_text" class="block text-sm font-medium text-gray-700">Your Answer</label>
                <textarea name="submission_text" 
                          id="submission_text" 
                          rows="8" 
                          required
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?php echo htmlspecialchars($existing_submission['submission_text'] ?? ''); ?></textarea>
            </div>

            <div>
                <label for="submission_file" class="block text-sm font-medium text-gray-700">
                    Submission File (Optional)
                </label>
                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                    <div class="space-y-1 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div class="flex text-sm text-gray-600">
                            <label for="submission_file" class="relative cursor-pointer rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                <span>Upload a file</span>
                                <input id="submission_file" 
                                       name="submission_file" 
                                       type="file" 
                                       accept=".pdf,.doc,.docx"
                                       class="sr-only">
                            </label>
                            <p class="pl-1">or drag and drop</p>
                        </div>
                        <p class="text-xs text-gray-500">
                            PDF, DOC, DOCX up to 10MB
                        </p>
                        <?php if ($existing_submission && $existing_submission['file_path']): ?>
                            <p class="text-sm text-gray-600 mt-2">
                                Current file: <?php echo htmlspecialchars($existing_submission['file_path']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <a href="<?php echo BASE_URL; ?>/courses/view.php?id=<?php echo $assignment['course_id']; ?>" 
                   class="text-gray-600 hover:text-gray-800">
                    <i class="fas fa-arrow-left"></i> Back to Course
                </a>
                <button type="submit" 
                        class="bg-blue-500 text-white px-6 py-2 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <?php echo $existing_submission ? 'Update Submission' : 'Submit Assignment'; ?>
                </button>
            </div>
        </form>

        <?php if ($existing_submission): ?>
            <div class="mt-6 p-4 bg-gray-50 rounded-md">
                <h2 class="text-lg font-semibold mb-2">Previous Submission</h2>
                <p class="text-sm text-gray-600">
                    Submitted: <?php echo date('F j, Y, g:i a', strtotime($existing_submission['submitted_at'])); ?>
                </p>
                <?php if ($existing_submission['score']): ?>
                    <div class="mt-2">
                        <p class="font-medium">Score: <?php echo $existing_submission['score']; ?> / <?php echo $assignment['max_points']; ?></p>
                        <?php if ($existing_submission['feedback']): ?>
                            <div class="mt-2">
                                <p class="font-medium">Feedback:</p>
                                <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($existing_submission['feedback'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>
