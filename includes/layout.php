<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#1E40AF',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <a href="<?php echo BASE_URL; ?>" class="flex items-center">
                        <span class="text-2xl font-bold text-primary">Live School</span>
                    </a>
                </div>
                <div class="flex items-center">
                    <?php if (isLoggedIn()): ?>
                        <a href="<?php echo BASE_URL; ?>/dashboard" class="text-gray-700 hover:text-primary px-3 py-2">Dashboard</a>
                        <a href="<?php echo BASE_URL; ?>/courses" class="text-gray-700 hover:text-primary px-3 py-2">Courses</a>
                        <?php if (isTeacher()): ?>
                            <a href="<?php echo BASE_URL; ?>/teacher/courses" class="text-gray-700 hover:text-primary px-3 py-2">My Courses</a>
                        <?php endif; ?>
                        <form action="<?php echo BASE_URL; ?>/auth/logout.php" method="POST" class="inline">
                            <button type="submit" class="text-gray-700 hover:text-primary px-3 py-2">Logout</button>
                        </form>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>/auth/login.php" class="text-gray-700 hover:text-primary px-3 py-2">Login</a>
                        <a href="<?php echo BASE_URL; ?>/auth/register.php" class="bg-primary text-white px-4 py-2 rounded-md ml-3">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 px-4">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php 
                echo $_SESSION['message'];
                unset($_SESSION['message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <?php echo $content ?? ''; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white mt-12">
        <div class="max-w-7xl mx-auto py-8 px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <h3 class="text-lg font-semibold mb-4">Live School</h3>
                    <p class="text-gray-400">Empowering education through free world-class learning resources.</p>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="<?php echo BASE_URL; ?>/about" class="text-gray-400 hover:text-white">About Us</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/courses" class="text-gray-400 hover:text-white">Courses</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/contact" class="text-gray-400 hover:text-white">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Connect With Us</h3>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
            </div>
            <div class="mt-8 pt-8 border-t border-gray-700 text-center text-gray-400">
                <p>&copy; <?php echo date('Y'); ?> Live School. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
