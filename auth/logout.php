<?php
require_once '../config/config.php';

session_destroy();
redirect('/auth/login.php');
?>
