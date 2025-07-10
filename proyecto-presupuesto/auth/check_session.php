<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {

redirect('auth/login.php');
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
session_destroy();
redirect('auth/login.php?timeout=1');
}

$_SESSION['last_activity'] = time();