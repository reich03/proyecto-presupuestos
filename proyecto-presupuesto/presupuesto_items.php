<?php
// Archivo: proyecto-presupuesto/presupuesto_items.php
$page_title = 'Gestión de Items de Presupuesto';
require_once __DIR__ . '/includes/header.php';

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$presupuesto_id = $_GET['presupuesto_id'] ?? null;

// Obtener presupuestos del usuario
$presupuestos = $db->fetchAll('
    SELECT id, nombre, fecha_inicio, fecha_fin 
    FROM presupuestos 
    WHERE usuario_id = ? AND activo = 1 
    ORDER BY fecha_inicio DESC
', [$user_id]);

// Obtener categorías y subcategorías
$categorias = $db->fetchAll('
    SELECT cp.*, 
           JSON_ARRAYAGG(
               JSON_OBJECT(
                   "id", sc.id,
                   "nombre", sc.nombre,
                   "descripcion", sc.descripcion
               )
           ) as subcategorias
    FROM categorias_padre cp
    LEFT JOIN subcategorias sc ON cp.id = sc.categoria_padre_id AND sc.activo = 1
    WHERE cp.activo = 1
    GROUP BY cp.id, cp.nombre, cp.descripcion, cp.tipo
    ORDER BY cp.tipo, cp.nombre
');

// Procesar datos de subcategorías
foreach ($categorias as &$categoria) {
    $categoria['subcategorias'] = json_decode($categoria['subcategorias'], true);
    // Filtrar subcategorías nulas
    $categoria['subcategorias'] = array_filter($categoria['subcategorias'], function($sub) {
        return !is_null($sub['id']);
    });
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    
    switch ($action) {
        case 'create':
            $presupuesto_id = $_POST['presupuesto_id'];
            $subcategoria_id = $_POST['subcategoria_id'];
            $nombre = sanitizeInput($_POST['nombre']);
            $descripcion = sanitizeInput($_POST['descripcion']);
            $monto_planificado = floatval($_POST['monto_planificado']);
            $tipo = $_POST['tipo'];
            
            if (empty($presupuesto_id) || empty($subcategoria_id) || empty($nombre) || $monto_planificado <= 0) {
                $_SESSION['error_message'] = 'Todos los campos requeridos deben ser completados correctamente';
            } else {
                // Verificar que el presupuesto pertenece al usuario
                $presupuesto_exists = $db->fetchOne('SELECT id FROM presupuestos WHERE id = ? AND usuario_id = ?', [$presupuesto_id, $user_id]);
                
                if (!$presupuesto_exists) {
                    $_SESSION['error_message'] = 'Presupuesto no válido';
                } else {
                    $data = [
                        'presupuesto_id' => $presupuesto_id,
                        'subcategoria_id' => $subcategoria_id,
                        'nombre' => $nombre,
                        'descripcion' => $descripcion,
                        'monto_planificado' => $monto_planificado,
                        'tipo' => $tipo
                    ];
                    
                    if ($db->insert('presupuesto_items', $data)) {
                        $_SESSION['success_message'] = 'Item agregado exitosamente al presupuesto';
                        redirect('presupuesto_items.php');
                    } else {
                        $_SESSION['error_message'] = 'Error al agregar el item al presupuesto';
                    }
                }
            }
            break;
            
        case 'update':
            $id = $_POST['id'];
            $presupuesto_id = $_POST['presupuesto_id'];
            $subcategoria_id = $_POST['subcategoria_id'];
            $nombre = sanitizeInput($_POST['nombre']);
            $descripcion = sanitizeInput($_POST['descripcion']);
            $monto_planificado = floatval($_POST['monto_planificado']);
            $tipo = $_POST['tipo'];
            
            if (empty($presupuesto_id) || empty($subcategoria_id) || empty($nombre) || $monto_planificado <= 0) {
                $_SESSION['error_message'] = 'Todos los campos requeridos deben ser completados correctamente';
            } else {
                // Verificar que el item pertenece al usuario
                $item_exists = $db->fetchOne('
                    SELECT pi.id FROM presupuesto_items pi
                    JOIN presupuestos p ON pi.presupuesto_id = p.id
                    WHERE pi.id = ? AND p.usuario_id = ?
                ', [$id, $user_id]);
                
                if (!$item_exists) {
                    $_SESSION['error_message'] = 'Item no válido';
                } else {
                    $data = [
                        'presupuesto_id' => $presupuesto_id,
                        'subcategoria_id' => $subcategoria_id,
                        'nombre' => $nombre,
                        'descripcion' => $descripcion,
                        'monto_planificado' => $monto_planificado,
                        'tipo' => $tipo
                    ];
                    
                    if ($db->update('presupuesto_items', $data, 'id = ?', [$id])) {
                        $_SESSION['success_message'] = 'Item actualizado exitosamente';
                        redirect('presupuesto_items.php');
                    } else {
                        $_SESSION['error_message'] = 'Error al actualizar el item';
                    }
                }
            }
            break;
            
        case 'delete':
            $id = $_POST['id'];
            // Verificar que el item pertenece al usuario antes de eliminar
            $item_exists = $db->fetchOne('
                SELECT pi.id FROM presupuesto_items pi
                JOIN presupuestos p ON pi.presupuesto_id = p.id
                WHERE pi.id = ? AND p.usuario_id = ?
            ', [$id, $user_id]);
            
            if ($item_exists) {
                if ($db->delete('presupuesto_items', 'id = ?', [$id])) {
                    $_SESSION['success_message'] = 'Item eliminado exitosamente';
                } else {
                    $_SESSION['error_message'] = 'Error al eliminar el item';
                }
            } else {
                $_SESSION['error_message'] = 'Item no válido';
            }
            redirect('presupuesto_items.php');
            break;
    }
}

// Obtener datos según la acción
switch ($action) {
    case 'create':
    case 'edit':
        $item = null;
        if ($action === 'edit' && $id) {
            $item = $db->fetchOne('
                SELECT pi.* FROM presupuesto_items pi
                JOIN presupuestos p ON pi.presupuesto_id = p.id
                WHERE pi.id = ? AND p.usuario_id = ?
            ', [$id, $user_id]);
            
            if (!$item) {
                $_SESSION['error_message'] = 'Item no encontrado';
                redirect('presupuesto_items.php');
            }
        }
        break;
        
    default:
        // Construir filtros
        $where_conditions = ['p.usuario_id = ?'];
        $params = [$user_id];
        
        if ($presupuesto_id) {
            $where_conditions[] = 'p.id = ?';
            $params[] = $presupuesto_id;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Obtener lista de items
        $items = $db->fetchAll("
            SELECT pi.*, p.nombre as presupuesto_nombre,
                   sc.nombre as subcategoria_nombre, cp.nombre as categoria_nombre,
                   (SELECT COUNT(*) FROM transacciones t WHERE t.presupuesto_item_id = pi.id) as total_transacciones
            FROM presupuesto_items pi
            JOIN presupuestos p ON pi.presupuesto_id = p.id
            JOIN subcategorias sc ON pi.subcategoria_id = sc.id
            JOIN categorias_padre cp ON sc.categoria_padre_id = cp.id
            WHERE $where_clause
            ORDER BY p.nombre, pi.tipo, cp.nombre, sc.nombre, pi.nombre
        ", $params);
        
        break;
}
?>

<?php if ($action === 'list'): ?>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Items de Presupuesto</h1>
        <div class="flex space-x-2">
            <?php if (empty($presupuestos)): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg px-4 py-2 text-yellow-800 text-sm">
                    <i class="fas fa-info-circle mr-2"></i>
                    Primero crea un presupuesto
                </div>
                <a href="presupuestos.php?action=create" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition-colors">
                    <i class="fas fa-plus mr-2"></i>Crear Presupuesto
                </a>
            <?php else: ?>
                <a href="presupuesto_items.php?action=create" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md transition-colors">
                    <i class="fas fa-plus mr-2"></i>Nuevo Item
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($presupuesto_id): ?>
        <?php $presupuesto_filtrado = $db->fetchOne('SELECT nombre FROM presupuestos WHERE id = ? AND usuario_id = ?', [$presupuesto_id, $user_id]); ?>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-medium text-blue-900">
                        <i class="fas fa-filter mr-2"></i>Filtrando por Presupuesto
                    </h3>
                    <p class="text-blue-700">Mostrando items del presupuesto: <strong><?php echo htmlspecialchars($presupuesto_filtrado['nombre']); ?></strong></p>
                </div>
                <a href="presupuesto_items.php" class="text-blue-600 hover:text-blue-800 font-medium">
                    <i class="fas fa-times mr-2"></i>Limpiar filtro
                </a>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Resumen de estadísticas -->
    <?php if (!empty($items)): ?>
        <?php
        $total_ingresos = array_sum(array_column(array_filter($items, function($item) { return $item['tipo'] === 'ingreso'; }), 'monto_planificado'));
        $total_egresos = array_sum(array_column(array_filter($items, function($item) { return $item['tipo'] === 'egreso'; }), 'monto_planificado'));
        $balance = $total_ingresos - $total_egresos;
        ?>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-arrow-up text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Ingresos</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo formatCurrency($total_ingresos); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                        <i class="fas fa-arrow-down text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Egresos</p>
                        <p class="text-2xl font-bold text-red-600"><?php echo formatCurrency($total_egresos); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full <?php echo $balance >= 0 ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                        <i class="fas fa-balance-scale text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Balance</p>
                        <p class="text-2xl font-bold <?php echo $balance >= 0 ? 'text-green-600' : 'text-red-600'; ?>"><?php echo formatCurrency($balance); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-list text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Items</p>
                        <p class="text-2xl font-bold text-blue-600"><?php echo count($items); ?></p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">
                <i class="fas fa-list mr-2"></i>Lista de Items
            </h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 ">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Presupuesto</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoría</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Planificado</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Real</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transacciones</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="text-gray-500">
                                    <i class="fas fa-inbox text-4xl mb-4"></i>
                                    <h3 class="text-lg font-medium mb-2">No hay items creados</h3>
                                    <p class="text-sm mb-4">Agrega items a tus presupuestos para comenzar</p>
                                    <?php if (!empty($presupuestos)): ?>
                                        <a href="presupuesto_items.php?action=create" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                                            <i class="fas fa-plus mr-2"></i>Crear primer item
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['presupuesto_nombre']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($item['categoria_nombre']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($item['subcategoria_nombre']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['nombre']); ?></div>
                                    <?php if ($item['descripcion']): ?>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($item['descripcion']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $item['tipo'] === 'ingreso' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <i class="fas <?php echo $item['tipo'] === 'ingreso' ? 'fa-arrow-up' : 'fa-arrow-down'; ?> mr-1"></i>
                                        <?php echo ucfirst($item['tipo']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold <?php echo $item['tipo'] === 'ingreso' ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo formatCurrency($item['monto_planificado']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold <?php echo $item['tipo'] === 'ingreso' ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo formatCurrency($item['monto_real']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <?php echo $item['total_transacciones']; ?>
                                    </span>
                                    <?php if ($item['total_transacciones'] > 0): ?>
                                        <a href="transacciones.php?item_id=<?php echo $item['id']; ?>" class="ml-2 text-blue-600 hover:text-blue-800" title="Ver transacciones">
                                            <i class="fas fa-eye text-xs"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="transacciones.php?action=create&item_id=<?php echo $item['id']; ?>" 
                                           class="text-green-600 hover:text-green-900 p-1 rounded" 
                                           title="Agregar transacción">
                                            <i class="fas fa-plus"></i>
                                        </a>
                                        <a href="presupuesto_items.php?action=edit&id=<?php echo $item['id']; ?>" 
                                           class="text-indigo-600 hover:text-indigo-900 p-1 rounded" 
                                           title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="deleteItem(<?php echo $item['id']; ?>)" 
                                                class="text-red-600 hover:text-red-900 p-1 rounded" 
                                                title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
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
    <div class="max-w-4xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">
                <i class="fas <?php echo $action === 'create' ? 'fa-plus' : 'fa-edit'; ?> mr-2"></i>
                <?php echo $action === 'create' ? 'Agregar Nuevo Item' : 'Editar Item'; ?>
            </h1>
            <a href="presupuesto_items.php" class="text-gray-600 hover:text-gray-800 font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Volver
            </a>
        </div>
        
        <?php if (empty($presupuestos)): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-yellow-600 text-xl mr-3"></i>
                    <div>
                        <h3 class="text-lg font-medium text-yellow-800">No hay presupuestos disponibles</h3>
                        <p class="text-yellow-700 mt-1">Primero debes crear un presupuesto antes de agregar items.</p>
                        <div class="mt-4">
                            <a href="presupuestos.php?action=create" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-md transition-colors">
                                <i class="fas fa-plus mr-2"></i>Crear Presupuesto
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" class="space-y-6" id="itemForm">
                    <input type="hidden" name="action" value="<?php echo $action === 'create' ? 'create' : 'update'; ?>">
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="presupuesto_id" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-calculator mr-2"></i>Presupuesto *
                            </label>
                            <select id="presupuesto_id" 
                                    name="presupuesto_id" 
                                    required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleccione un presupuesto</option>
                                <?php foreach ($presupuestos as $presupuesto): ?>
                                    <option value="<?php echo $presupuesto['id']; ?>" 
                                            <?php echo ($item['presupuesto_id'] ?? $presupuesto_id) == $presupuesto['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($presupuesto['nombre']); ?>
                                        (<?php echo formatDate($presupuesto['fecha_inicio']) . ' - ' . formatDate($presupuesto['fecha_fin']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="tipo" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-tag mr-2"></i>Tipo *
                            </label>
                            <select id="tipo" 
                                    name="tipo" 
                                    required 
                                    onchange="updateCategories()"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleccione un tipo</option>
                                <option value="ingreso" <?php echo ($item['tipo'] ?? '') === 'ingreso' ? 'selected' : ''; ?>>
                                    <i class="fas fa-arrow-up"></i> Ingreso
                                </option>
                                <option value="egreso" <?php echo ($item['tipo'] ?? '') === 'egreso' ? 'selected' : ''; ?>>
                                    <i class="fas fa-arrow-down"></i> Egreso
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label for="categoria_padre_id" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-folder mr-2"></i>Categoría *
                        </label>
                        <select id="categoria_padre_id" 
                                name="categoria_padre_id" 
                                required 
                                onchange="updateSubcategories()"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Primero seleccione un tipo</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="subcategoria_id" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-tags mr-2"></i>Subcategoría *
                        </label>
                        <select id="subcategoria_id" 
                                name="subcategoria_id" 
                                required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Primero seleccione una categoría</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-pencil-alt mr-2"></i>Nombre del Item *
                        </label>
                        <input type="text" 
                               id="nombre" 
                               name="nombre" 
                               required 
                               value="<?php echo htmlspecialchars($item['nombre'] ?? ''); ?>"
                               placeholder="Ej: Sueldo mensual, Gastos de comida, etc."
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label for="monto_planificado" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-dollar-sign mr-2"></i>Monto Planificado *
                        </label>
                        <input type="number" 
                               id="monto_planificado" 
                               name="monto_planificado" 
                               step="0.01" 
                               min="0"
                               required 
                               value="<?php echo $item['monto_planificado'] ?? ''; ?>"
                               placeholder="0.00"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-align-left mr-2"></i>Descripción
                        </label>
                        <textarea id="descripcion" 
                                  name="descripcion" 
                                  rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Descripción opcional del item..."><?php echo htmlspecialchars($item['descripcion'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                        <a href="presupuesto_items.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-md transition-colors">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </a>
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-md transition-colors">
                            <i class="fas <?php echo $action === 'create' ? 'fa-plus' : 'fa-save'; ?> mr-2"></i>
                            <?php echo $action === 'create' ? 'Crear Item' : 'Actualizar Item'; ?>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
// Datos de categorías para JavaScript
const categorias = <?php echo json_encode($categorias); ?>;
const itemActual = <?php echo $action === 'edit' ? json_encode($item) : 'null'; ?>;

function updateCategories() {
    const tipoSelect = document.getElementById('tipo');
    const categoriaSelect = document.getElementById('categoria_padre_id');
    const subcategoriaSelect = document.getElementById('subcategoria_id');
    
    // Limpiar selects
    categoriaSelect.innerHTML = '<option value="">Seleccione una categoría</option>';
    subcategoriaSelect.innerHTML = '<option value="">Primero seleccione una categoría</option>';
    
    if (tipoSelect.value) {
        const categoriasFiltradas = categorias.filter(cat => cat.tipo === tipoSelect.value);
        
        categoriasFiltradas.forEach(categoria => {
            const option = document.createElement('option');
            option.value = categoria.id;
            option.textContent = categoria.nombre;
            categoriaSelect.appendChild(option);
        });
    }
}

function updateSubcategories() {
    const categoriaSelect = document.getElementById('categoria_padre_id');
    const subcategoriaSelect = document.getElementById('subcategoria_id');
    
    // Limpiar subcategorías
    subcategoriaSelect.innerHTML = '<option value="">Seleccione una subcategoría</option>';
    
    if (categoriaSelect.value) {
        const categoria = categorias.find(cat => cat.id == categoriaSelect.value);
        
        if (categoria && categoria.subcategorias) {
            categoria.subcategorias.forEach(subcategoria => {
                const option = document.createElement('option');
                option.value = subcategoria.id;
                option.textContent = subcategoria.nombre;
                subcategoriaSelect.appendChild(option);
            });
        }
    }
}

// Cargar datos si estamos editando
document.addEventListener('DOMContentLoaded', function() {
    if (itemActual) {
        // Primero actualizar categorías basado en el tipo
        updateCategories();
        
        // Luego seleccionar la categoría
        setTimeout(() => {
            const categoriaActual = categorias.find(cat => 
                cat.subcategorias.some(sub => sub.id == itemActual.subcategoria_id)
            );
            
            if (categoriaActual) {
                document.getElementById('categoria_padre_id').value = categoriaActual.id;
                updateSubcategories();
                
                // Finalmente seleccionar la subcategoría
                setTimeout(() => {
                    document.getElementById('subcategoria_id').value = itemActual.subcategoria_id;
                }, 100);
            }
        }, 100);
    }
});

function deleteItem(id) {
    Swal.fire({
        title: '¿Eliminar item?',
        text: 'Esta acción no se puede deshacer. También se eliminarán todas las transacciones asociadas.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Crear formulario oculto para enviar la eliminación
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>