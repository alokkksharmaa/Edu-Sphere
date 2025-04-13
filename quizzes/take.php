<?php
require_once '../config/config.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('/auth/login.php');
}

$quiz_id = $_GET['id'] ?? null;
if (!$quiz_id) {
    redirect('/dashboard');
}

$db = connectDB();

// Get quiz details and verify enrollment
$stmt = $db->prepare("
    SELECT q.*, c.id as course_id, c.title as course_title
    FROM quizzes q
    JOIN courses c ON q.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id
    WHERE q.id = ? AND e.student_id = ?
");
$stmt->execute([$quiz_id, $_SESSION['user_id']]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    $_SESSION['error'] = 'Quiz not found or you are not enrolled in this course';
    redirect('/dashboard');
}

// Get quiz questions
$stmt = $db->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($questions)) {
    $_SESSION['error'] = 'This quiz has no questions';
    redirect('/courses/view.php?id=' . $quiz['course_id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answers = $_POST['answers'] ?? [];
    
    if (count($answers) !== count($questions)) {
        $_SESSION['error'] = 'Please answer all questions';
    } else {
        try {
            // Calculate score
            $correct_answers = 0;
            foreach ($questions as $question) {
                if (isset($answers[$question['id']]) && 
                    strtolower($answers[$question['id']]) === strtolower($question['correct_answer'])) {
                    $correct_answers++;
                }
            }
            
            $score = round(($correct_answers / count($questions)) * 100);
            
            // Save attempt
            $stmt = $db->prepare("
                INSERT INTO quiz_attempts (student_id, quiz_id, score)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $quiz_id, $score]);
            
            $_SESSION['message'] = "Quiz completed! Your score: $score%";
            redirect('/courses/view.php?id=' . $quiz['course_id']);
            
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to submit quiz. Please try again.';
        }
    }
}

$pageTitle = $quiz['title'];

ob_start();
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold mb-2"><?php echo htmlspecialchars($quiz['title']); ?></h1>
            <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($quiz['description']); ?></p>
            <p class="text-sm text-gray-500">
                Course: <?php echo htmlspecialchars($quiz['course_title']); ?>
            </p>
        </div>

        <form action="<?php echo BASE_URL; ?>/quizzes/take.php?id=<?php echo $quiz_id; ?>" 
              method="POST"
              id="quizForm"
              onsubmit="return confirm('Are you sure you want to submit this quiz? You cannot change your answers after submission.');">
            
            <div class="space-y-6">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold mb-4">
                            Question <?php echo $index + 1; ?>:
                            <?php echo htmlspecialchars($question['question']); ?>
                        </h3>
                        
                        <div class="space-y-3">
                            <?php
                            $options = [
                                'a' => $question['option_a'],
                                'b' => $question['option_b'],
                                'c' => $question['option_c'],
                                'd' => $question['option_d']
                            ];
                            
                            foreach ($options as $key => $value):
                            ?>
                                <div class="flex items-center">
                                    <input type="radio" 
                                           id="q<?php echo $question['id']; ?>_<?php echo $key; ?>"
                                           name="answers[<?php echo $question['id']; ?>]"
                                           value="<?php echo $key; ?>"
                                           required
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                    <label for="q<?php echo $question['id']; ?>_<?php echo $key; ?>"
                                           class="ml-3 block text-gray-700">
                                        <?php echo htmlspecialchars($value); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="flex items-center justify-between">
                    <a href="<?php echo BASE_URL; ?>/courses/view.php?id=<?php echo $quiz['course_id']; ?>" 
                       class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-arrow-left"></i> Back to Course
                    </a>
                    <button type="submit" 
                            class="bg-blue-500 text-white px-6 py-2 rounded-md hover:bg-blue-600">
                        Submit Quiz
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Quiz Instructions -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-lg font-semibold mb-4">Quiz Instructions</h2>
        <ul class="list-disc list-inside space-y-2 text-gray-600">
            <li>Read each question carefully before selecting your answer.</li>
            <li>You must answer all questions to submit the quiz.</li>
            <li>Each question has only one correct answer.</li>
            <li>You cannot change your answers after submitting the quiz.</li>
            <li>Your score will be displayed immediately after submission.</li>
            <li>You can retake the quiz multiple times to improve your score.</li>
        </ul>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>
