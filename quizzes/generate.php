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
        redirect("/quizzes/generate.php?course_id=$course_id");
    }

    try {
        // Handle PDF upload
        if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Please upload a PDF file');
        }

        $uploader = new FileUploader('../uploads/pdfs', 10485760, ['application/pdf']);
        $pdf_path = $uploader->upload($_FILES['pdf_file']);
        
        if (!$pdf_path) {
            throw new Exception(implode('<br>', $uploader->getErrors()));
        }

        // Get AI configuration
        $stmt = $db->prepare("SELECT * FROM ai_config LIMIT 1");
        $stmt->execute();
        $ai_config = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ai_config || empty($ai_config['api_key'])) {
            throw new Exception('AI service is not configured');
        }

        // Extract text from PDF using pdftotext (requires poppler-utils)
        $pdf_text = shell_exec("pdftotext " . escapeshellarg("../uploads/pdfs/$pdf_path") . " -");
        if (empty($pdf_text)) {
            throw new Exception('Failed to extract text from PDF');
        }

        // Call OpenAI API to generate quiz
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        
        $prompt = "Generate 6 multiple-choice questions based on the following text. Format the output as JSON with the following structure:
        {
            \"questions\": [
                {
                    \"question\": \"Question text\",
                    \"option_a\": \"First option\",
                    \"option_b\": \"Second option\",
                    \"option_c\": \"Third option\",
                    \"option_d\": \"Fourth option\",
                    \"correct_answer\": \"a/b/c/d\"
                }
            ]
        }
        
        Text: $pdf_text";

        $data = [
            'model' => $ai_config['model'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a quiz generator. Create challenging but fair multiple-choice questions based on the provided text. Ensure questions test understanding, not just memorization.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => $ai_config['max_tokens'],
            'temperature' => $ai_config['temperature'],
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $ai_config['api_key']
            ]
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception('Failed to generate quiz questions');
        }

        $result = json_decode($response, true);
        $content = $result['choices'][0]['message']['content'];
        $questions = json_decode($content, true);

        if (!isset($questions['questions']) || empty($questions['questions'])) {
            throw new Exception('Failed to parse generated questions');
        }

        // Start transaction
        $db->beginTransaction();

        // Save AI generated quiz record
        $stmt = $db->prepare("
            INSERT INTO ai_generated_quizzes (course_id, teacher_id, pdf_path)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$course_id, $_SESSION['user_id'], $pdf_path]);

        // Create quiz
        $stmt = $db->prepare("
            INSERT INTO quizzes (course_id, title, description)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $course_id,
            $_POST['title'],
            "AI-generated quiz based on uploaded PDF document."
        ]);
        $quiz_id = $db->lastInsertId();

        // Add questions
        $stmt = $db->prepare("
            INSERT INTO quiz_questions (quiz_id, question, option_a, option_b, option_c, option_d, correct_answer)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($questions['questions'] as $q) {
            $stmt->execute([
                $quiz_id,
                $q['question'],
                $q['option_a'],
                $q['option_b'],
                $q['option_c'],
                $q['option_d'],
                $q['correct_answer']
            ]);
        }

        $db->commit();
        $_SESSION['message'] = 'Quiz generated successfully';
        redirect("/courses/view.php?id=$course_id");

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
    }
}

$pageTitle = "Generate Quiz - {$course['title']}";

ob_start();
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold mb-6">Generate AI Quiz for <?php echo htmlspecialchars($course['title']); ?></h1>

        <form action="<?php echo BASE_URL; ?>/quizzes/generate.php?course_id=<?php echo $course_id; ?>" 
              method="POST"
              enctype="multipart/form-data"
              class="space-y-6">
            
            <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">

            <div>
                <label for="title" class="block text-sm font-medium text-gray-700">Quiz Title</label>
                <input type="text" 
                       name="title" 
                       id="title" 
                       required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div>
                <label for="pdf_file" class="block text-sm font-medium text-gray-700">
                    Upload PDF Document
                </label>
                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                    <div class="space-y-1 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div class="flex text-sm text-gray-600">
                            <label for="pdf_file" class="relative cursor-pointer rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                <span>Upload a file</span>
                                <input id="pdf_file" 
                                       name="pdf_file" 
                                       type="file" 
                                       accept=".pdf"
                                       required
                                       class="sr-only">
                            </label>
                            <p class="pl-1">or drag and drop</p>
                        </div>
                        <p class="text-xs text-gray-500">
                            PDF up to 10MB
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 p-4 rounded-md">
                <h3 class="text-blue-800 font-medium mb-2">How it works:</h3>
                <ul class="list-disc list-inside text-sm text-blue-700 space-y-1">
                    <li>Upload a PDF document containing the course material</li>
                    <li>Our AI will analyze the content and generate 6 multiple-choice questions</li>
                    <li>Questions are designed to test understanding of key concepts</li>
                    <li>You can review and edit the generated quiz later</li>
                </ul>
            </div>

            <div class="flex items-center justify-between">
                <a href="<?php echo BASE_URL; ?>/courses/view.php?id=<?php echo $course_id; ?>" 
                   class="text-gray-600 hover:text-gray-800">
                    <i class="fas fa-arrow-left"></i> Back to Course
                </a>
                <button type="submit" 
                        class="bg-blue-500 text-white px-6 py-2 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Generate Quiz
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>
