<?php
require_once '../config/config.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('/auth/login.php');
}

$course_id = $_GET['course_id'] ?? null;
if (!$course_id) {
    redirect('/dashboard');
}

$db = connectDB();

// Verify course ownership
$stmt = $db->prepare("SELECT title FROM courses WHERE id = ? AND teacher_id = ?");
$stmt->execute([$course_id, $_SESSION['user_id']]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    $_SESSION['error'] = 'Course not found';
    redirect('/dashboard');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $questions = $_POST['questions'] ?? [];
    
    if (empty($title) || empty($description) || empty($questions)) {
        $_SESSION['error'] = 'Please fill in all required fields and add at least one question';
    } else {
        try {
            $db->beginTransaction();
            
            // Create quiz
            $stmt = $db->prepare("INSERT INTO quizzes (course_id, title, description) VALUES (?, ?, ?)");
            $stmt->execute([$course_id, $title, $description]);
            $quiz_id = $db->lastInsertId();
            
            // Add questions
            $stmt = $db->prepare("
                INSERT INTO quiz_questions (quiz_id, question, option_a, option_b, option_c, option_d, correct_answer)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($questions as $question) {
                if (!empty($question['text']) && 
                    !empty($question['option_a']) && 
                    !empty($question['option_b']) && 
                    !empty($question['option_c']) && 
                    !empty($question['option_d']) && 
                    !empty($question['correct_answer'])) {
                    
                    $stmt->execute([
                        $quiz_id,
                        $question['text'],
                        $question['option_a'],
                        $question['option_b'],
                        $question['option_c'],
                        $question['option_d'],
                        $question['correct_answer']
                    ]);
                }
            }
            
            $db->commit();
            $_SESSION['message'] = 'Quiz created successfully!';
            redirect('/courses/edit.php?id=' . $course_id);
            
        } catch (PDOException $e) {
            $db->rollBack();
            $_SESSION['error'] = 'Failed to create quiz. Please try again.';
        }
    }
}

$pageTitle = 'Create Quiz';

ob_start();
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold mb-6">Create Quiz for <?php echo htmlspecialchars($course['title']); ?></h1>
        
        <form action="<?php echo BASE_URL; ?>/quizzes/create.php?course_id=<?php echo $course_id; ?>" 
              method="POST" 
              id="quizForm">
            <div class="space-y-6">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700">Quiz Title</label>
                    <input type="text" id="title" name="title" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Quiz Description</label>
                    <textarea id="description" name="description" rows="3" required
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                </div>

                <!-- Questions Container -->
                <div id="questionsContainer" class="space-y-6">
                    <!-- Questions will be added here dynamically -->
                </div>

                <div>
                    <button type="button" 
                            onclick="addQuestion()"
                            class="w-full bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600">
                        <i class="fas fa-plus"></i> Add Question
                    </button>
                </div>

                <div class="flex items-center justify-end space-x-4">
                    <a href="<?php echo BASE_URL; ?>/courses/edit.php?id=<?php echo $course_id; ?>" 
                       class="bg-gray-100 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-200">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                        Create Quiz
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<template id="questionTemplate">
    <div class="question-box bg-gray-50 p-4 rounded-lg">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Question <span class="question-number"></span></h3>
            <button type="button" 
                    onclick="removeQuestion(this)" 
                    class="text-red-600 hover:text-red-800">
                <i class="fas fa-trash"></i> Remove
            </button>
        </div>
        
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Question Text</label>
                <input type="text" name="questions[0][text]" required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Option A</label>
                    <input type="text" name="questions[0][option_a]" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Option B</label>
                    <input type="text" name="questions[0][option_b]" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Option C</label>
                    <input type="text" name="questions[0][option_c]" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Option D</label>
                    <input type="text" name="questions[0][option_d]" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Correct Answer</label>
                <select name="questions[0][correct_answer]" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Select correct answer</option>
                    <option value="a">Option A</option>
                    <option value="b">Option B</option>
                    <option value="c">Option C</option>
                    <option value="d">Option D</option>
                </select>
            </div>
        </div>
    </div>
</template>

<script>
let questionCount = 0;

function addQuestion() {
    const container = document.getElementById('questionsContainer');
    const template = document.getElementById('questionTemplate');
    const clone = template.content.cloneNode(true);
    
    // Update question number
    questionCount++;
    clone.querySelector('.question-number').textContent = questionCount;
    
    // Update input names
    const inputs = clone.querySelectorAll('input, select');
    inputs.forEach(input => {
        input.name = input.name.replace('[0]', `[${questionCount - 1}]`);
    });
    
    container.appendChild(clone);
}

function removeQuestion(button) {
    const questionBox = button.closest('.question-box');
    questionBox.remove();
    
    // Update question numbers
    const questions = document.querySelectorAll('.question-box');
    questions.forEach((question, index) => {
        question.querySelector('.question-number').textContent = index + 1;
        
        // Update input names
        const inputs = question.querySelectorAll('input, select');
        inputs.forEach(input => {
            input.name = input.name.replace(/\[\d+\]/, `[${index}]`);
        });
    });
    
    questionCount = questions.length;
}

// Add first question automatically
document.addEventListener('DOMContentLoaded', () => {
    addQuestion();
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>
