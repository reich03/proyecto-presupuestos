<?php
// 1. INCLUIR ARCHIVOS DE CONFIGURACIÓN Y FUNCIONES
// config.php ya inicia la sesión, por lo que debe ir primero.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// 2. LÓGICA DE REDIRECCIÓN
// Si el usuario ya está logueado, no debe ver esta página.
if (isLoggedIn()) {
    redirect('../dashboard.php');
    exit(); // Detener la ejecución del script después de redirigir.
}

// 3. PROCESAMIENTO DEL FORMULARIO
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = sanitizeInput($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($usuario) || empty($password)) {
        $error = 'Por favor complete todos los campos.';
    } else {
        global $db;
        
        // Buscar usuario por nombre de usuario o email
        $sql = "SELECT u.*, r.nombre as rol_nombre, r.permisos 
                FROM usuarios u 
                LEFT JOIN roles r ON u.rol_id = r.id 
                WHERE (u.usuario = ? OR u.email = ?) AND u.activo = 1";
        
        $user = $db->fetchOne($sql, [$usuario, $usuario]);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Login exitoso: Regenerar ID de sesión por seguridad
            session_regenerate_id(true);

            // Guardar datos en la sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nombre'] . ' ' . $user['apellido'];
            $_SESSION['user_role'] = $user['rol_nombre'];
            $_SESSION['user_permissions'] = json_decode($user['permisos'], true); // Decodificar permisos
            $_SESSION['last_activity'] = time();
            
            // Actualizar último login en la base de datos
            $db->update('usuarios', ['ultimo_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
            
            // Log de actividad (si tienes esta función)
            // logActivity($user['id'], 'login', 'Usuario inició sesión');
            
            // Redirigir al dashboard y terminar el script
            redirect('../dashboard.php');
            exit();
        } else {
            $error = 'Credenciales inválidas o usuario inactivo.';
        }
    }
}

// 4. SI LLEGAMOS AQUÍ, MOSTRAMOS EL HTML
// El script solo continúa hasta aquí si no hubo una redirección.
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Reemplaza "your-fontawesome-kit" con tu kit real de Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-500 to-purple-600 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2"><?php echo APP_NAME; ?></h1>
            <p class="text-gray-600">Iniciar Sesión</p>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded mb-4" role="alert">
                <p><i class="fas fa-exclamation-triangle mr-2"></i><?php echo $error; ?></p>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="login.php" class="space-y-6">
            <div>
                <label for="usuario" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-user mr-2 text-gray-400"></i>Usuario o Email
                </label>
                <input type="text" id="usuario" name="usuario" required 
                       class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="tu_usuario o tu@email.com">
            </div>
            
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-lock mr-2 text-gray-400"></i>Contraseña
                </label>
                <input type="password" id="password" name="password" required 
                       class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="••••••••">
            </div>
            
            <div class="flex items-center justify-between">
                <a href="#" class="text-sm text-blue-600 hover:underline">¿Olvidaste tu contraseña?</a>
            </div>
            
            <button type="submit" 
                    class="w-full bg-blue-600 text-white py-2.5 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200 font-semibold">
                <i class="fas fa-sign-in-alt mr-2"></i>Iniciar Sesión
            </button>
        </form>
        
        <div class="mt-8 text-center">
            <p class="text-sm text-gray-600">
                © <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. Todos los derechos reservados.
            </p>
        </div>
    </div>
</body>
</html>