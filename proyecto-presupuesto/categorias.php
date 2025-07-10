<?php
// Archivo: proyecto-presupuesto/categorias.php
$page_title = 'Gestión de Categorías';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$tipo = $_GET['tipo'] ?? 'padre';

// Verificar permisos
if (!hasPermission('categorias_gestionar')) {
    $_SESSION['error_message'] = 'No tienes permisos para gestionar categorías';
    redirect('dashboard.php');
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    
    switch ($action) {
        case 'create_categoria':
            $nombre = sanitizeInput($_POST['nombre']);
            $descripcion = sanitizeInput($_POST['descripcion']);
            $tipo_categoria = $_POST['tipo'];
            
            if (empty($nombre) || empty($tipo_categoria)) {
                $_SESSION['error_message'] = 'Todos los campos requeridos deben ser completados';
            } else {
                $data = [
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'tipo' => $tipo_categoria
                ];
                
                if ($db->insert('categorias_padre', $data)) {
                    $_SESSION['success_message'] = 'Categoría creada exitosamente';
                    redirect('categorias.php');
                } else {
                    $_SESSION['error_message'] = 'Error al crear la categoría';
                }
            }
            break;
            
        case 'create_subcategoria':
            $nombre = sanitizeInput($_POST['nombre']);
            $descripcion = sanitizeInput($_POST['descripcion']);
            $categoria_padre_id = $_POST['categoria_padre_id'];
            
            if (empty($nombre) || empty($categoria_padre_id)) {
                $_SESSION['error_message'] = 'Todos los campos requeridos deben ser completados';
            } else {
                $data = [
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'categoria_padre_id' => $categoria_padre_id
                ];
                
                if ($db->insert('subcategorias', $data)) {
                    $_SESSION['success_message'] = 'Subcategoría creada exitosamente';
                    redirect('categorias.php?tipo=sub');
                } else {
                    $_SESSION['error_message'] = 'Error al crear la subcategoría';
                }
            }
            break;
            
        case 'update_categoria':
            $id = $_POST['id'];
            $nombre = sanitizeInput($_POST['nombre']);
            $descripcion = sanitizeInput($_POST['descripcion']);
            $tipo_categoria = $_POST['tipo'];
            
            if (empty($nombre) || empty($tipo_categoria)) {
                $_SESSION['error_message'] = 'Todos los campos requeridos deben ser completados';
            } else {
                $data = [
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'tipo' => $tipo_categoria
                ];
                
                if ($db->update('categorias_padre', $data, 'id = ?', [$id])) {
                    $_SESSION['success_message'] = 'Categoría actualizada exitosamente';
                    redirect('categorias.php');
                } else {
                    $_SESSION['error_message'] = 'Error al actualizar la categoría';
                }
            }
            break;
            
        case 'update_subcategoria':
            $id = $_POST['id'];
            $nombre = sanitizeInput($_POST['nombre']);
            $descripcion = sanitizeInput($_POST['descripcion']);
            $categoria_padre_id = $_POST['categoria_padre_id'];
            
            if (empty($nombre) || empty($categoria_padre_id)) {
                $_SESSION['error_message'] = 'Todos los campos requeridos deben ser completados';
            } else {
                $data = [
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'categoria_padre_id' => $categoria_padre_id
                ];
                
                if ($db->update('subcategorias', $data, 'id = ?', [$id])) {
                    $_SESSION['success_message'] = 'Subcategoría actualizada exitosamente';
                    redirect('categorias.php?tipo=sub');
                } else {
                    $_SESSION['error_message'] = 'Error al actualizar la subcategoría';
                }
            }
            break;
            
        case 'toggle_categoria':
            $id = $_POST['id'];
            $activo = $_POST['activo'] === '1' ? 0 : 1;
            
            if ($db->update('categorias_padre', ['activo' => $activo], 'id = ?', [$id])) {
                $_SESSION['success_message'] = 'Estado de la categoría actualizado exitosamente';
            } else {
                $_SESSION['error_message'] = 'Error al actualizar el estado';
            }
            redirect('categorias.php');
            break;
            
        case 'toggle_subcategoria':
            $id = $_POST['id'];
            $activo = $_POST['activo'] === '1' ? 0 : 1;
            
            if ($db->update('subcategorias', ['activo' => $activo], 'id = ?', [$id])) {
                $_SESSION['success_message'] = 'Estado de la subcategoría actualizado exitosamente';
            } else {
                $_SESSION['error_message'] = 'Error al actualizar el estado';
            }
            redirect('categorias.php?tipo=sub');
            break;
    }
}

// Obtener datos según la acción
$categoria = null;
$subcategoria = null;
$categorias_padre = $db->fetchAll('SELECT * FROM categorias_padre ORDER BY tipo, nombre');

if ($action === 'edit_categoria' && $id) {
    $categoria = $db->fetchOne('SELECT * FROM categorias_padre WHERE id = ?', [$id]);
    if (!$categoria) {
        $_SESSION['error_message'] = 'Categoría no encontrada';
        redirect('categorias.php');
    }
} elseif ($action === 'edit_subcategoria' && $id) {
    $subcategoria = $db->fetchOne('SELECT * FROM subcategorias WHERE id = ?', [$id]);
    if (!$subcategoria) {
        $_SESSION['error_message'] = 'Subcategoría no encontrada';
        redirect('categorias.php?tipo=sub');
    }
}

// Obtener listas
$categorias = $db->fetchAll('SELECT * FROM categorias_padre ORDER BY tipo, nombre');
$subcategorias = $db->fetchAll('
    SELECT sc.*, cp.nombre as categoria_padre_nombre, cp.tipo as categoria_tipo
    FROM subcategorias sc
    JOIN categorias_padre cp ON sc.categoria_padre_id = cp.id
    ORDER BY cp.tipo, cp.nombre, sc.nombre
');
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 mb-4">Gestión de Categorías</h1>
    
    <!-- Pestañas -->
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex space-x-8">
            <a href="categorias.php" 
               class="<?php echo $tipo === 'padre' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                Categorías Padre
            </a>
            <a href="categorias.php?tipo=sub" 
               class="<?php echo $tipo === 'sub' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                Subcategorías
            </a>
        </nav>
    </div>
</div>

<?php if ($tipo === 'padre'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Formulario de Categorías Padre -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">
                <?php echo $action === 'edit_categoria' ? 'Editar Categoría' : 'Crear Nueva Categoría'; ?>
            </h2>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="<?php echo $action === 'edit_categoria' ? 'update_categoria' : 'create_categoria'; ?>">
                <?php if ($action === 'edit_categoria'): ?>
                    <input type="hidden" name="id" value="<?php echo $categoria['id']; ?>">
                <?php endif; ?>
                
                <div>
                    <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">Nombre *</label>
                    <input type="text" 
                           id="nombre" 
                           name="nombre" 
                           required 
                           value="<?php echo htmlspecialchars($categoria['nombre'] ?? ''); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                    <textarea id="descripcion" 
                              name="descripcion" 
                              rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($categoria['descripcion'] ?? ''); ?></textarea>
                </div>
                
                <div>
                    <label for="tipo" class="block text-sm font-medium text-gray-700 mb-2">Tipo *</label>
                    <select id="tipo" 
                            name="tipo" 
                            required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Seleccione un tipo</option>
                        <option value="ingreso" <?php echo ($categoria['tipo'] ?? '') === 'ingreso' ? 'selected' : ''; ?>>Ingreso</option>
                        <option value="egreso" <?php echo ($categoria['tipo'] ?? '') === 'egreso' ? 'selected' : ''; ?>>Egreso</option>
                    </select>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <?php if ($action === 'edit_categoria'): ?>
                        <a href="categorias.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">
                            Cancelar
                        </a>
                    <?php endif; ?>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                        <?php echo $action === 'edit_categoria' ? 'Actualizar' : 'Crear'; ?>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Lista de Categorías Padre -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Categorías Padre</h2>
            
            <div class="space-y-2 max-h-96 overflow-y-auto">
                <?php foreach ($categorias as $cat): ?>
                    <div class="flex items-center justify-between p-3 border rounded-lg <?php echo $cat['activo'] ? 'bg-gray-50' : 'bg-red-50'; ?>">
                        <div class="flex-1">
                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($cat['nombre']); ?></div>
                            <div class="text-sm text-gray-500">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $cat['tipo'] === 'ingreso' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo ucfirst($cat['tipo']); ?>
                                </span>
                                <?php if ($cat['descripcion']): ?>
                                    - <?php echo htmlspecialchars($cat['descripcion']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <a href="categorias.php?action=edit_categoria&id=<?php echo $cat['id']; ?>" class="text-blue-600 hover:text-blue-900">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_categoria">
                                <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                <input type="hidden" name="activo" value="<?php echo $cat['activo']; ?>">
                                <button type="submit" class="<?php echo $cat['activo'] ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900'; ?>">
                                    <i class="fas fa-<?php echo $cat['activo'] ? 'eye-slash' : 'eye'; ?>"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

<?php else: ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Formulario de Subcategorías -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">
                <?php echo $action === 'edit_subcategoria' ? 'Editar Subcategoría' : 'Crear Nueva Subcategoría'; ?>
            </h2>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="<?php echo $action === 'edit_subcategoria' ? 'update_subcategoria' : 'create_subcategoria'; ?>">
                <?php if ($action === 'edit_subcategoria'): ?>
                    <input type="hidden" name="id" value="<?php echo $subcategoria['id']; ?>">
                <?php endif; ?>
                
                <div>
                    <label for="categoria_padre_id" class="block text-sm font-medium text-gray-700 mb-2">Categoría Padre *</label>
                    <select id="categoria_padre_id" 
                            name="categoria_padre_id" 
                            required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Seleccione una categoría</option>
                        <?php foreach ($categorias_padre as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" 
                                    <?php echo ($subcategoria['categoria_padre_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['nombre'] . ' (' . ucfirst($cat['tipo']) . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">Nombre *</label>
                    <input type="text" 
                           id="nombre" 
                           name="nombre" 
                           required 
                           value="<?php echo htmlspecialchars($subcategoria['nombre'] ?? ''); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                    <textarea id="descripcion" 
                              name="descripcion" 
                              rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($subcategoria['descripcion'] ?? ''); ?></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <?php if ($action === 'edit_subcategoria'): ?>
                        <a href="categorias.php?tipo=sub" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">
                            Cancelar
                        </a>
                    <?php endif; ?>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                        <?php echo $action === 'edit_subcategoria' ? 'Actualizar' : 'Crear'; ?>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Lista de Subcategorías -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Subcategorías</h2>
            
            <div class="space-y-2 max-h-96 overflow-y-auto">
                <?php foreach ($subcategorias as $sub): ?>
                    <div class="flex items-center justify-between p-3 border rounded-lg <?php echo $sub['activo'] ? 'bg-gray-50' : 'bg-red-50'; ?>">
                        <div class="flex-1">
                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($sub['nombre']); ?></div>
                            <div class="text-sm text-gray-500">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $sub['categoria_tipo'] === 'ingreso' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo htmlspecialchars($sub['categoria_padre_nombre']); ?>
                                </span>
                                <?php if ($sub['descripcion']): ?>
                                    - <?php echo htmlspecialchars($sub['descripcion']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <a href="categorias.php?action=edit_subcategoria&id=<?php echo $sub['id']; ?>&tipo=sub" class="text-blue-600 hover:text-blue-900">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_subcategoria">
                                <input type="hidden" name="id" value="<?php echo $sub['id']; ?>">
                                <input type="hidden" name="activo" value="<?php echo $sub['activo']; ?>">
                                <button type="submit" class="<?php echo $sub['activo'] ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900'; ?>">
                                    <i class="fas fa-<?php echo $sub['activo'] ? 'eye-slash' : 'eye'; ?>"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>