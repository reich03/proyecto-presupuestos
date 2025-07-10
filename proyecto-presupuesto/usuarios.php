<?php
$page_title = 'Gestión de Usuarios';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

if (!hasPermission('usuarios_crear')) {
    $_SESSION['error_message'] = 'No tienes permisos para gestionar usuarios';
    redirect('dashboard.php');
}

$roles = $db->fetchAll('SELECT * FROM roles WHERE activo = 1 ORDER BY nombre');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    
    switch ($action) {
        case 'create':
            $usuario = sanitizeInput($_POST['usuario']);
            $email = sanitizeInput($_POST['email']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            $nombre = sanitizeInput($_POST['nombre']);
            $apellido = sanitizeInput($_POST['apellido']);
            $rol_id = $_POST['rol_id'];

            if (empty($usuario) || empty($email) || empty($password) || empty($nombre) || empty($apellido) || empty($rol_id)) {
                $_SESSION['error_message'] = 'Todos los campos requeridos deben ser completados';
            } elseif (!validateEmail($email)) {
                $_SESSION['error_message'] = 'El formato del email es inválido';
            } elseif ($password !== $confirm_password) {
                $_SESSION['error_message'] = 'Las contraseñas no coinciden';
            } elseif (strlen($password) < 6) {
                $_SESSION['error_message'] = 'La contraseña debe tener al menos 6 caracteres';
            } else {
                $existing_user = $db->fetchOne('SELECT id FROM usuarios WHERE usuario = ? OR email = ?', [$usuario, $email]);
                if ($existing_user) {
                    $_SESSION['error_message'] = 'El usuario o email ya existe';
                } else {
                    $data = [
                        'usuario' => $usuario, 
                        'email' => $email, 
                        'password_hash' => hashPassword($password), 
                        'nombre' => $nombre, 
                        'apellido' => $apellido, 
                        'rol_id' => $rol_id,
                        'activo' => 1,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $result = $db->insert('usuarios', $data);
                    if ($result) {
                        $_SESSION['success_message'] = 'Usuario creado exitosamente';
                        logActivity($_SESSION['user_id'], 'create_user', "Usuario creado: $usuario");
                        redirect('usuarios.php');
                    } else {
                        $_SESSION['error_message'] = 'Error al crear el usuario';
                        if ($db->getLastError()) {
                            error_log("Error creating user: " . $db->getLastError());
                        }
                    }
                }
            }
            break;

        case 'update':
            $id = $_POST['id'];
            $usuario = sanitizeInput($_POST['usuario']);
            $email = sanitizeInput($_POST['email']);
            $nombre = sanitizeInput($_POST['nombre']);
            $apellido = sanitizeInput($_POST['apellido']);
            $rol_id = $_POST['rol_id'];
            $password = $_POST['password'] ?? '';

            if (empty($usuario) || empty($email) || empty($nombre) || empty($apellido) || empty($rol_id)) {
                $_SESSION['error_message'] = 'Todos los campos requeridos deben ser completados';
            } elseif (!validateEmail($email)) {
                $_SESSION['error_message'] = 'El formato del email es inválido';
            } else {
                // Verificar que el usuario existe
                $current_user = $db->fetchOne('SELECT * FROM usuarios WHERE id = ?', [$id]);
                if (!$current_user) {
                    $_SESSION['error_message'] = 'Usuario no encontrado';
                    redirect('usuarios.php');
                    exit;
                }

                $existing_user = $db->fetchOne('SELECT id FROM usuarios WHERE (usuario = ? OR email = ?) AND id != ?', [$usuario, $email, $id]);
                if ($existing_user) {
                    $_SESSION['error_message'] = 'El usuario o email ya existe';
                } else {
                    $data = [
                        'usuario' => $usuario, 
                        'email' => $email, 
                        'nombre' => $nombre, 
                        'apellido' => $apellido, 
                        'rol_id' => $rol_id,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    if (!empty($password)) {
                        if (strlen($password) < 6) {
                            $_SESSION['error_message'] = 'La contraseña debe tener al menos 6 caracteres';
                        } else {
                            $data['password_hash'] = hashPassword($password);
                        }
                    }
                    
                    if (!isset($_SESSION['error_message'])) {
                        $result = $db->update('usuarios', $data, 'id = ?', [$id]);
                        
                        if ($result) {
                            $_SESSION['success_message'] = 'Usuario actualizado exitosamente';
                            logActivity($_SESSION['user_id'], 'update_user', "Usuario actualizado: $usuario (ID: $id)");
                            redirect('usuarios.php');
                        } else {
                            $_SESSION['error_message'] = 'Error al actualizar el usuario';
                            if ($db->getLastError()) {
                                error_log("Update failed. Error: " . $db->getLastError());
                            }
                        }
                    }
                }
            }
            
            if (isset($_SESSION['error_message'])) {
                redirect("usuarios.php?action=edit&id=$id");
                exit;
            }
            break;

        case 'toggle_status':
            $id = $_POST['id'];
            if ($id == $_SESSION['user_id']) {
                $_SESSION['error_message'] = 'No puedes cambiar tu propio estado.';
            } else {
                $current_status_row = $db->fetchOne('SELECT activo FROM usuarios WHERE id = ?', [$id]);
                if ($current_status_row) {
                    $new_status = $current_status_row['activo'] == 1 ? 0 : 1;
                    $result = $db->update('usuarios', ['activo' => $new_status], 'id = ?', [$id]);
                    if ($result) {
                        $_SESSION['success_message'] = 'Estado del usuario actualizado exitosamente';
                        logActivity($_SESSION['user_id'], 'toggle_user_status', "Estado del usuario ID $id cambiado a " . ($new_status ? 'Activo' : 'Inactivo'));
                    } else {
                        $_SESSION['error_message'] = 'Error al actualizar el estado del usuario.';
                    }
                } else {
                    $_SESSION['error_message'] = 'Usuario no encontrado.';
                }
            }
            redirect('usuarios.php');
            exit;
            break;
    }
}

switch ($action) {
    case 'create':
    case 'edit':
        $usuario_data = null;
        if ($action === 'edit' && $id) {
            $usuario_data = $db->fetchOne('SELECT u.*, r.nombre as rol_nombre FROM usuarios u LEFT JOIN roles r ON u.rol_id = r.id WHERE u.id = ?', [$id]);
            
            if (!$usuario_data) {
                $_SESSION['error_message'] = 'Usuario no encontrado';
                redirect('usuarios.php');
                exit;
            }
        }
        break;
    default:
        $usuarios = $db->fetchAll('SELECT u.*, r.nombre as rol_nombre FROM usuarios u LEFT JOIN roles r ON u.rol_id = r.id ORDER BY u.created_at DESC');
        break;
}
?>

<?php if ($action === 'list'): ?>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900"><i class="fas fa-users-cog mr-2 text-blue-600"></i>Gestión de Usuarios</h1>
        <a href="usuarios.php?action=create" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md shadow-sm transition-colors flex items-center">
            <i class="fas fa-plus mr-2"></i>Nuevo Usuario
        </a>
    </div>
    
    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900"><i class="fas fa-list mr-2"></i>Lista de Usuarios</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre Completo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rol</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Último Login</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($usuarios)): ?>
                        <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">No hay usuarios registrados.</td></tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $user): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($user['usuario']); ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($user['email']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($user['nombre'] . ' ' . $user['apellido']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800"><?= htmlspecialchars($user['rol_nombre']); ?></span></td>
                                <td class="px-6 py-4 whitespace-nowrap"><span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full <?= $user['activo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>"><i class="fas fa-circle mr-1 text-xs"></i><?= $user['activo'] ? 'Activo' : 'Inactivo'; ?></span></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= $user['ultimo_login'] ? formatDateTime($user['ultimo_login']) : 'Nunca'; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <div class="flex items-center justify-center space-x-2">
                                        <a href="usuarios.php?action=edit&id=<?= $user['id']; ?>" class="text-indigo-600 hover:text-indigo-900 p-1 rounded" title="Editar"><i class="fas fa-edit"></i></a>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button onclick="toggleStatus(<?= $user['id']; ?>, <?= $user['activo']; ?>)" class="<?= $user['activo'] ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900'; ?> p-1 rounded" title="<?= $user['activo'] ? 'Desactivar' : 'Activar'; ?>">
                                                <i class="fas <?= $user['activo'] ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($action === 'create' || $action === 'edit'): ?>
    <div class="max-w-2xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900"><i class="fas <?= $action === 'create' ? 'fa-user-plus' : 'fa-user-edit'; ?> mr-2 text-blue-600"></i><?= $action === 'create' ? 'Crear Nuevo Usuario' : 'Editar Usuario'; ?></h1>
            <a href="usuarios.php" class="text-gray-600 hover:text-gray-800 flex items-center"><i class="fas fa-arrow-left mr-2"></i>Volver</a>
        </div>
        
        <div class="bg-white shadow-sm rounded-lg p-6">
            <form method="POST" class="space-y-6" id="userForm">
                <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update'; ?>">
                <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= htmlspecialchars($id); ?>"><?php endif; ?>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="usuario" class="block text-sm font-medium text-gray-700 mb-2">Usuario *</label>
                        <input type="text" id="usuario" name="usuario" required 
                               value="<?= htmlspecialchars($usuario_data['usuario'] ?? ''); ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                        <input type="email" id="email" name="email" required 
                               value="<?= htmlspecialchars($usuario_data['email'] ?? ''); ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" required 
                               value="<?= htmlspecialchars($usuario_data['nombre'] ?? ''); ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="apellido" class="block text-sm font-medium text-gray-700 mb-2">Apellido *</label>
                        <input type="text" id="apellido" name="apellido" required 
                               value="<?= htmlspecialchars($usuario_data['apellido'] ?? ''); ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div>
                    <label for="rol_id" class="block text-sm font-medium text-gray-700 mb-2">Rol *</label>
                    <select id="rol_id" name="rol_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Seleccione un rol</option>
                        <?php foreach ($roles as $rol): ?>
                            <option value="<?= $rol['id']; ?>" <?= (isset($usuario_data['rol_id']) && $usuario_data['rol_id'] == $rol['id']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($rol['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="border-t pt-6 space-y-6">
                    <h3 class="text-lg font-medium text-gray-800">Seguridad</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                Contraseña <?= $action === 'create' ? '*' : '(dejar vacío para no cambiar)'; ?>
                            </label>
                            <input type="password" id="password" name="password" 
                                   <?= $action === 'create' ? 'required' : ''; ?> 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <?php if ($action === 'create'): ?>
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirmar Contraseña *</label>
                                <input type="password" id="confirm_password" name="confirm_password" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 pt-6 border-t">
                    <a href="usuarios.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded-md">Cancelar</a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md">
                        <i class="fas fa-save mr-2"></i><?= $action === 'create' ? 'Crear Usuario' : 'Actualizar Usuario'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function toggleStatus(id, currentStatus) {
    const actionText = currentStatus ? 'desactivar' : 'activar';
    const confirmColor = currentStatus ? '#d33' : '#3085d6';

    Swal.fire({
        title: `¿Seguro que quieres ${actionText} este usuario?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#6b7280',
        confirmButtonText: `Sí, ${actionText}`,
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'usuarios.php';
            form.innerHTML = `<input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="${id}">`;
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>