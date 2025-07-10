<?php
$page_title = 'Gestión de Roles';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

if (!hasPermission('roles_gestionar')) {
    $_SESSION['error_message'] = 'No tienes permisos para gestionar roles';
    redirect('dashboard.php');
}

$permisos_disponibles = [
    'usuarios_crear' => 'Crear usuarios',
    'usuarios_editar' => 'Editar usuarios',
    'usuarios_eliminar' => 'Eliminar usuarios',
    'roles_gestionar' => 'Gestionar roles',
    'categorias_gestionar' => 'Gestionar categorías',
    'presupuestos_crear' => 'Crear presupuestos',
    'presupuestos_editar' => 'Editar presupuestos',
    'metas_gestionar' => 'Gestionar metas',
    'reportes_personales' => 'Ver reportes personales',
    'reportes_globales' => 'Ver reportes globales'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    
    switch ($action) {
        case 'create':
            $nombre = sanitizeInput($_POST['nombre']);
            $descripcion = sanitizeInput($_POST['descripcion']);
            $permisos = $_POST['permisos'] ?? [];
            
            if (empty($nombre)) {
                $_SESSION['error_message'] = 'El nombre del rol es requerido';
            } else {
                $existing_role = $db->fetchOne('SELECT id FROM roles WHERE nombre = ?', [$nombre]);
                
                if ($existing_role) {
                    $_SESSION['error_message'] = 'El rol ya existe';
                } else {
                    $data = [
                        'nombre' => $nombre,
                        'descripcion' => $descripcion,
                        'permisos' => json_encode($permisos)
                    ];
                    
                    if ($db->insert('roles', $data)) {
                        $_SESSION['success_message'] = 'Rol creado exitosamente';
                        logActivity($_SESSION['user_id'], 'create_role', "Rol creado: $nombre");
                        redirect('roles.php');
                    } else {
                        $_SESSION['error_message'] = 'Error al crear el rol';
                    }
                }
            }
            break;
            
        case 'update':
            $id = $_POST['id'];
            $nombre = sanitizeInput($_POST['nombre']);
            $descripcion = sanitizeInput($_POST['descripcion']);
            $permisos = $_POST['permisos'] ?? [];
            
            if (empty($nombre)) {
                $_SESSION['error_message'] = 'El nombre del rol es requerido';
            } else {
                $existing_role = $db->fetchOne('SELECT id FROM roles WHERE nombre = ? AND id != ?', [$nombre, $id]);
                
                if ($existing_role) {
                    $_SESSION['error_message'] = 'El rol ya existe';
                } else {
                    $data = [
                        'nombre' => $nombre,
                        'descripcion' => $descripcion,
                        'permisos' => json_encode($permisos)
                    ];
                    
                    if ($db->update('roles', $data, 'id = ?', [$id])) {
                        $_SESSION['success_message'] = 'Rol actualizado exitosamente';
                        logActivity($_SESSION['user_id'], 'update_role', "Rol actualizado: $nombre");
                        redirect('roles.php');
                    } else {
                        $_SESSION['error_message'] = 'Error al actualizar el rol';
                    }
                }
            }
            break;
            
        case 'toggle_status':
            $id = $_POST['id'];
            $activo = $_POST['activo'] === '1' ? 0 : 1;
            
            if ($id == 1) {
                $_SESSION['error_message'] = 'No se puede desactivar el rol de Administrador';
            } else {
                if ($db->update('roles', ['activo' => $activo], 'id = ?', [$id])) {
                    $_SESSION['success_message'] = 'Estado del rol actualizado exitosamente';
                    logActivity($_SESSION['user_id'], 'toggle_role_status', "Estado del rol cambiado: ID $id");
                } else {
                    $_SESSION['error_message'] = 'Error al actualizar el estado';
                }
            }
            redirect('roles.php');
            break;
    }
}

switch ($action) {
    case 'create':
    case 'edit':
        $rol = null;
        if ($action === 'edit' && $id) {
            $rol = $db->fetchOne('SELECT * FROM roles WHERE id = ?', [$id]);
            if (!$rol) {
                $_SESSION['error_message'] = 'Rol no encontrado';
                redirect('roles.php');
            }
        }
        break;
        
    default:
        $roles = $db->fetchAll('
            SELECT r.*, COUNT(u.id) as usuarios_count
            FROM roles r
            LEFT JOIN usuarios u ON r.id = u.rol_id AND u.activo = 1
            GROUP BY r.id
            ORDER BY r.nombre
        ');
        break;
}
?>

<?php if ($action === 'list'): ?>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Gestión de Roles</h1>
        <a href="roles.php?action=create" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
            <i class="fas fa-plus mr-2"></i>Nuevo Rol
        </a>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($roles as $rol): ?>
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 <?php echo $rol['activo'] ? 'border-blue-500' : 'border-gray-400'; ?>">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($rol['nombre']); ?></h3>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($rol['descripcion']); ?></p>
                    </div>
                    <div class="flex space-x-2">
                        <a href="roles.php?action=edit&id=<?php echo $rol['id']; ?>" class="text-blue-600 hover:text-blue-900">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php if ($rol['id'] != 1): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?php echo $rol['id']; ?>">
                                <input type="hidden" name="activo" value="<?php echo $rol['activo']; ?>">
                                <button type="submit" class="<?php echo $rol['activo'] ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900'; ?>">
                                    <i class="fas fa-<?php echo $rol['activo'] ? 'eye-slash' : 'eye'; ?>"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mb-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Permisos:</h4>
                    <div class="flex flex-wrap gap-1">
                        <?php
                        $permisos_rol = json_decode($rol['permisos'] ?? '[]', true);
                        if (!empty($permisos_rol)):
                            foreach ($permisos_rol as $permiso):
                                $nombre_permiso = $permisos_disponibles[$permiso] ?? $permiso;
                        ?>
                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                    <?php echo htmlspecialchars($nombre_permiso); ?>
                                </span>
                        <?php
                            endforeach;
                        else:
                        ?>
                            <span class="text-xs text-gray-500">Sin permisos asignados</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-600"><?php echo $rol['usuarios_count']; ?> usuarios</span>
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $rol['activo'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                        <?php echo $rol['activo'] ? 'Activo' : 'Inactivo'; ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php elseif ($action === 'create' || $action === 'edit'): ?>
    <div class="max-w-2xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">
                <?php echo $action === 'create' ? 'Crear Nuevo Rol' : 'Editar Rol'; ?>
            </h1>
            <a href="roles.php" class="text-gray-600 hover:text-gray-800">
                <i class="fas fa-arrow-left mr-2"></i>Volver
            </a>
        </div>
        
        <div class="bg-white shadow-md rounded-lg p-6">
            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" value="<?php echo $action === 'create' ? 'create' : 'update'; ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?php echo $rol['id']; ?>">
                <?php endif; ?>
                
                <div>
                    <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">Nombre del Rol *</label>
                    <input type="text" 
                           id="nombre" 
                           name="nombre" 
                           required 
                           value="<?php echo htmlspecialchars($rol['nombre'] ?? ''); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                    <textarea id="descripcion" 
                              name="descripcion" 
                              rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Descripción del rol..."><?php echo htmlspecialchars($rol['descripcion'] ?? ''); ?></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Permisos</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php
                        $permisos_rol = json_decode($rol['permisos'] ?? '[]', true);
                        foreach ($permisos_disponibles as $permiso => $descripcion):
                        ?>
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       id="permiso_<?php echo $permiso; ?>" 
                                       name="permisos[]" 
                                       value="<?php echo $permiso; ?>"
                                       <?php echo in_array($permiso, $permisos_rol) ? 'checked' : ''; ?>
                                       class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <label for="permiso_<?php echo $permiso; ?>" class="text-sm text-gray-700">
                                    <?php echo htmlspecialchars($descripcion); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <a href="roles.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">
                        Cancelar
                    </a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                        <?php echo $action === 'create' ? 'Crear Rol' : 'Actualizar Rol'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>