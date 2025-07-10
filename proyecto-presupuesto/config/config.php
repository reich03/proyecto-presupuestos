<?php


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


define('DB_HOST', 'db');
define('DB_NAME', 'presupuesto_app');
define('DB_USER', 'app_user');
define('DB_PASS', 'app_password123');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'Presupuesto Personal');

define('SESSION_TIMEOUT', 3600); 
date_default_timezone_set('America/Bogota');

