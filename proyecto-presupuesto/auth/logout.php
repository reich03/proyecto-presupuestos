<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (isLoggedIn()) {
    // Log de actividad
    logActivity($_SESSION['user_id'], 'logout', 'Usuario cerró sesión');
    
    // Destruir sesión
    session_destroy();
}

redirect('login.php');
?>