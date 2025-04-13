<?php
require_once '../config/config.php';
require_once '../includes/utilities.php';

if (isLoggedIn()) {
    redirect('/dashboard');
}

// Check remember me cookie
if (!isLoggedIn()) {
    validateRememberMeCookie();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid form submission.';
        redirect('/auth/login.php');
    }

    $validator = new FormValidator($_POST);
    $rules = [
        'email' => ['required', 'email'],
        'password' => ['required']
    ];

    if ($validator->validate($rules)) {
        $data = $validator->sanitize();
        $email = $data['email'];
        $password = $_POST['password']; // Don't sanitize password
        $remember_me = isset($_POST['remember_me']);

        try {
            $db = connectDB();
            $stmt = $db->prepare("SELECT id, username, password, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Set remember me cookie if requested
                if ($remember_me) {
                    setRememberMeCookie($user['id']);
                }

                // Set last login cookie
                Cookie::set('last_login', date('Y-m-d H:i:s'), 30 * 24 * 60 * 60);

                redirect('/dashboard');
            } else {
                $_SESSION['error'] = 'Invalid email or password';
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Login failed. Please try again.';
        }
    } else {
        $_SESSION['error'] = implode('<br>', array_merge(...array_values($validator->getErrors())));
    }
}

$pageTitle = 'Login';

ob_start();
?>

<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Sign in to your account
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Or
                <a href="<?php echo BASE_URL; ?>/auth/register.php" class="font-medium text-blue-600 hover:text-blue-500">
                    create a new account
                </a>
            </p>
        </div>
        <form class="mt-8 space-y-6" action="<?php echo BASE_URL; ?>/auth/login.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">
            <?php if (Cookie::exists('last_login')): ?>
                <div class="text-sm text-gray-600 text-center">
                    Last login: <?php echo Cookie::get('last_login'); ?>
                </div>
            <?php endif; ?>
            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <label for="email" class="sr-only">Email address</label>
                    <input id="email" name="email" type="email" required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm" 
                           placeholder="Email address">
                </div>
                <div>
                    <label for="password" class="sr-only">Password</label>
                    <input id="password" name="password" type="password" required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm" 
                           placeholder="Password">
                </div>
            </div>

            <div class="flex items-center">
                <input id="remember_me" name="remember_me" type="checkbox" 
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                <label for="remember_me" class="ml-2 block text-sm text-gray-900">
                    Remember me
                </label>
            </div>

            <div>
                <button type="submit" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Sign in
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>
