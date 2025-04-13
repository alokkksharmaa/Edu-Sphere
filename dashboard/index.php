<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('/auth/login.php');
}

// Route to appropriate dashboard based on user role
if (isTeacher()) {
    require_once 'teacher.php';
} elseif (isStudent()) {
    require_once 'student.php';
} else {
    $_SESSION['error'] = 'Invalid user role';
    redirect('/auth/logout.php');
}
?>
