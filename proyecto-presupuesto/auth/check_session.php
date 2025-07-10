<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';


// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    redirect('auth/login.php');
}

// Verificar timeout de sesión
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_destroy();
    redirect('auth/login.php?timeout=1');
}

// Actualizar actividad
$_SESSION['last_activity'] = time();
?>