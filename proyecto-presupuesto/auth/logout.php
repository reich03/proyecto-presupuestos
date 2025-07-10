<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (isLoggedIn()) {
        logActivity($_SESSION['user_id'], 'logout', 'Usuario cerró sesión');
    
    session_destroy();
}

redirect('login.php');