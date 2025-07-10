<?php

define('DB_HOST', 'db');
define('DB_NAME', 'presupuesto_app');
define('DB_USER', 'app_user');
define('DB_PASS', 'app_password123');
define('DB_CHARSET', 'utf8mb4');

// Configuración de la aplicación
define('APP_NAME', 'Presupuesto Personal');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost');

// Configuración de sesiones
define('SESSION_TIMEOUT', 3600); // 1 hora

define('HASH_ALGORITHM', 'sha256');
define('PASSWORD_SALT', 'tu_salt_unico_aqui_2024');

define('ITEMS_PER_PAGE', 10);

define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'tu_email@gmail.com');
define('SMTP_PASS', 'tu_password');

// Configuración de zona horaria
date_default_timezone_set('America/Bogota');

// Configuración de errores
if ($_SERVER['HTTP_HOST'] === 'localhost') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

session_start();


