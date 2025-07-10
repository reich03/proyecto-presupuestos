<?php
require_once __DIR__ . '/../config/database.php';

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function redirect($url) {
    if (!headers_sent()) {
        header("Location: " . $url);
        exit();
    } else {
        echo "<script>window.location.href = '" . $url . "';</script>";
        exit();
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}



function hasPermission($permission) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $permissions = $_SESSION['user_permissions'] ?? [];
    
    return in_array($permission, $permissions);
}
// ====================================================================


function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    global $db;
    $sql = "SELECT u.*, r.nombre as rol_nombre, r.permisos 
            FROM usuarios u 
            LEFT JOIN roles r ON u.rol_id = r.id 
            WHERE u.id = ?";
    
    return $db->fetchOne($sql, [$_SESSION['user_id']]);
}

function formatCurrency($amount) {
    return '$ ' . number_format($amount, 0, ',', '.');
}

function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}


function formatDateTime($datetime) {
    return date('d/m/Y H:i', strtotime($datetime));
}

function calculatePercentage($current, $total) {
    if ($total == 0) return 0;
    return round(($current / $total) * 100, 2);
}

function showAlert($message, $type = 'info') {
    $alertClass = '';
    $iconClass = '';
    
    switch ($type) {
        case 'success':
            $alertClass = 'bg-green-100 border-green-400 text-green-700';
            $iconClass = 'fas fa-check-circle';
            break;
        case 'error':
            $alertClass = 'bg-red-100 border-red-400 text-red-700';
            $iconClass = 'fas fa-exclamation-triangle';
            break;
        case 'warning':
            $alertClass = 'bg-yellow-100 border-yellow-400 text-yellow-700';
            $iconClass = 'fas fa-exclamation-circle';
            break;
        default:
            $alertClass = 'bg-blue-100 border-blue-400 text-blue-700';
            $iconClass = 'fas fa-info-circle';
    }
    
    echo "<div class='alert {$alertClass} px-4 py-3 rounded relative mb-4 border' role='alert'>
            <span class='block sm:inline'>
                <i class='{$iconClass} mr-2'></i>
                {$message}
            </span>
          </div>";
}

function logActivity($user_id, $action, $description) {
    global $db;
    
    try {
        $sql = "INSERT INTO activity_log (user_id, action, description, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)";
        
        $params = [
            $user_id,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ];
        
        $db->query($sql, $params);
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
    }
}

function getConfig($key, $default = null) {
    global $db;
    
    try {
        $sql = "SELECT valor FROM configuracion WHERE clave = ?";
        $result = $db->fetchOne($sql, [$key]);
        
        return $result ? $result['valor'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function paginate($totalItems, $itemsPerPage, $currentPage) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    return [
        'total_items' => $totalItems,
        'items_per_page' => $itemsPerPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_previous' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
        'previous_page' => $currentPage - 1,
        'next_page' => $currentPage + 1
    ];
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function generateRandomColors($count) {
    $colors = [];
    for ($i = 0; $i < $count; $i++) {
        $colors[] = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
    }
    return $colors;
}

function getMonthName($month) {
    $months = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $months[$month] ?? '';
}

function getDayName($day) {
    $days = [
        0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
        4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'
    ];
    return $days[$day] ?? '';
}

function debug($data) {
    if ($_SERVER['HTTP_HOST'] === 'localhost') {
        echo "<pre>";
        print_r($data);
        echo "</pre>";
    }
}

