
<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Funciones generales de la aplicación
 */

// Función para sanitizar entradas
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Función para validar email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Función para hashear contraseñas
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Función para verificar contraseñas
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Función para generar tokens CSRF
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Función para verificar tokens CSRF
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Función para redireccionar
function redirect($url) {
    // Verificar si no se han enviado headers
    if (!headers_sent()) {
        header("Location: " . $url);
        exit();
    } else {
        // Si ya se enviaron headers, usar JavaScript
        echo "<script>window.location.href = '" . $url . "';</script>";
        exit();
    }
}

// Función para verificar si el usuario está logueado
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

// Función para verificar roles
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

// Función para verificar permisos
function hasPermission($permission) {
    if (!isLoggedIn()) return false;
    
    $permissions = json_decode($_SESSION['user_permissions'] ?? '[]', true);
    return in_array($permission, $permissions);
}

// Función para obtener el usuario actual
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    global $db;
    $sql = "SELECT u.*, r.nombre as rol_nombre, r.permisos 
            FROM usuarios u 
            LEFT JOIN roles r ON u.rol_id = r.id 
            WHERE u.id = ?";
    
    return $db->fetchOne($sql, [$_SESSION['user_id']]);
}

// Función para formatear moneda
function formatCurrency($amount) {
    return '$' . number_format($amount, 2, ',', '.');
}

// Función para formatear fechas
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

// Función para formatear fechas con hora
function formatDateTime($datetime) {
    return date('d/m/Y H:i', strtotime($datetime));
}

// Función para calcular porcentaje
function calculatePercentage($current, $total) {
    if ($total == 0) return 0;
    return round(($current / $total) * 100, 2);
}

// Función para mostrar alertas
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

// Función para log de actividades
function logActivity($user_id, $action, $description) {
    global $db;
    
    // Verificar si la tabla existe antes de insertar
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
        // Si la tabla no existe, simplemente no hacer nada
        // En producción podrías loggear esto a un archivo
        error_log("Error logging activity: " . $e->getMessage());
    }
}

// Función para obtener configuración
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

// Función para paginación
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

// Función para validar fechas
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// Función para generar colores aleatorios para gráficos
function generateRandomColors($count) {
    $colors = [];
    for ($i = 0; $i < $count; $i++) {
        $colors[] = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
    }
    return $colors;
}

// Función para obtener el mes en español
function getMonthName($month) {
    $months = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $months[$month] ?? '';
}

// Función para obtener el día de la semana en español
function getDayName($day) {
    $days = [
        0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
        4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'
    ];
    return $days[$day] ?? '';
}

// Función para debug (solo en desarrollo)
function debug($data) {
    if ($_SERVER['HTTP_HOST'] === 'localhost') {
        echo "<pre>";
        print_r($data);
        echo "</pre>";
    }
}
