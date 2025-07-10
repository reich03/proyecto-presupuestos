<?php
$page_title = 'Gestión de Presupuestos';
require_once __DIR__ . '/includes/header.php';

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['action'] ?? ''; 
    
    switch ($post_action) {
        case 'create':
            $nombre = sanitizeInput($_POST['nombre']);
            $descripcion = sanitizeInput($_POST['descripcion']);
            $fecha_inicio = $_POST['fecha_inicio'];
            $fecha_fin = $_POST['fecha_fin'];

            if (empty($nombre) || empty($fecha_inicio) || empty($fecha_fin)) {
                $_SESSION['error_message'] = 'El nombre, fecha de inicio y fecha de fin son requeridos.';
            } elseif ($fecha_inicio > $fecha_fin) {
                $_SESSION['error_message'] = 'La fecha de inicio debe ser anterior a la fecha de fin.';
            } else {
                $data = [
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'fecha_inicio' => $fecha_inicio,
                    'fecha_fin' => $fecha_fin,
                    'usuario_id' => $user_id,
                    'activo' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $resultado = $db->insert('presupuestos', $data);
                
                if ($resultado) {
                    $_SESSION['success_message'] = 'Presupuesto creado exitosamente.';
                    logActivity($user_id, 'create_budget', "Presupuesto creado: $nombre");
                } else {
                    $_SESSION['error_message'] = 'Error al crear el presupuesto.';
                    if ($db->getLastError()) {
                        error_log("Error creating budget: " . $db->getLastError());
                    }
                }
            }
            redirect('presupuestos.php');
            break;
            
        case 'update':
            $id_presupuesto = $_POST['id'] ?? null;
            $nombre = sanitizeInput($_POST['nombre']);
            $descripcion = sanitizeInput($_POST['descripcion']);
            $fecha_inicio = $_POST['fecha_inicio'];
            $fecha_fin = $_POST['fecha_fin'];

            if (empty($id_presupuesto) || empty($nombre) || empty($fecha_inicio) || empty($fecha_fin)) {
                $_SESSION['error_message'] = 'Todos los campos requeridos deben ser completados.';
            } elseif ($fecha_inicio > $fecha_fin) {
                $_SESSION['error_message'] = 'La fecha de inicio debe ser anterior a la fecha de fin.';
            } else {
                $current_budget = $db->fetchOne('SELECT * FROM presupuestos WHERE id = ? AND usuario_id = ?', [$id_presupuesto, $user_id]);
                if (!$current_budget) {
                    $_SESSION['error_message'] = 'Presupuesto no encontrado o no tienes permiso para editarlo.';
                    redirect('presupuestos.php');
                    exit;
                }

                $data = [
                    'nombre' => $nombre, 
                    'descripcion' => $descripcion, 
                    'fecha_inicio' => $fecha_inicio, 
                    'fecha_fin' => $fecha_fin,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $resultado = $db->update('presupuestos', $data, 'id = ? AND usuario_id = ?', [$id_presupuesto, $user_id]);
                
                if ($resultado) {
                    $_SESSION['success_message'] = 'Presupuesto actualizado exitosamente.';
                    logActivity($user_id, 'update_budget', "Presupuesto actualizado: $nombre (ID: $id_presupuesto)");
                } else {
                    $_SESSION['error_message'] = 'Error al actualizar el presupuesto.';
                    if ($db->getLastError()) {
                        error_log("Update budget failed. Error: " . $db->getLastError());
                    }
                }
            }
            
            if (isset($_SESSION['error_message'])) {
                redirect("presupuestos.php?action=edit&id=$id_presupuesto");
                exit;
            }
            
            redirect('presupuestos.php');
            break;
            
        case 'delete':
            $id_presupuesto = $_POST['id'] ?? null;
            
            if (empty($id_presupuesto)) {
                $_SESSION['error_message'] = 'ID del presupuesto requerido.';
            } else {
                $current_budget = $db->fetchOne('SELECT * FROM presupuestos WHERE id = ? AND usuario_id = ?', [$id_presupuesto, $user_id]);
                if (!$current_budget) {
                    $_SESSION['error_message'] = 'Presupuesto no encontrado o no tienes permiso para archivarlo.';
                } else {
                    $resultado = $db->update('presupuestos', ['activo' => 0], 'id = ? AND usuario_id = ?', [$id_presupuesto, $user_id]);
                    
                    if ($resultado) {
                        $_SESSION['success_message'] = 'Presupuesto archivado exitosamente.';
                        logActivity($user_id, 'archive_budget', "Presupuesto archivado: {$current_budget['nombre']} (ID: $id_presupuesto)");
                    } else {
                        $_SESSION['error_message'] = 'Error al archivar el presupuesto.';
                        if ($db->getLastError()) {
                            error_log("Archive budget failed. Error: " . $db->getLastError());
                        }
                    }
                }
            }
            redirect('presupuestos.php');
            break;
    }
}

switch ($action) {
    case 'create':
    case 'edit':
        $presupuesto = null;
        if ($action === 'edit' && $id) {
            $presupuesto = $db->fetchOne('SELECT * FROM presupuestos WHERE id = ? AND usuario_id = ?', [$id, $user_id]);
            if (!$presupuesto) {
                $_SESSION['error_message'] = 'Presupuesto no encontrado o no tienes permiso para editarlo.';
                redirect('presupuestos.php');
                exit;
            }
        }
        break;
        
    case 'view':
        if (!$id) {
            redirect('presupuestos.php');
            exit;
        }
        
        $presupuesto = $db->fetchOne('SELECT * FROM presupuestos WHERE id = ? AND usuario_id = ? AND activo = 1', [$id, $user_id]);
        if (!$presupuesto) {
            $_SESSION['error_message'] = 'Presupuesto no encontrado o archivado.';
            redirect('presupuestos.php');
            exit;
        }
        
        $totales = $db->fetchOne('
            SELECT 
                SUM(CASE WHEN pi.tipo = "ingreso" THEN pi.monto_planificado ELSE 0 END) as ip, 
                SUM(CASE WHEN pi.tipo = "egreso" THEN pi.monto_planificado ELSE 0 END) as ep, 
                SUM(CASE WHEN pi.tipo = "ingreso" THEN pi.monto_real ELSE 0 END) as ir, 
                SUM(CASE WHEN pi.tipo = "egreso" THEN pi.monto_real ELSE 0 END) as er, 
                COUNT(pi.id) as ti 
            FROM presupuesto_items pi 
            WHERE pi.presupuesto_id = ?', [$id]);
            
        $presupuesto = array_merge($presupuesto, [
            'ingresos_planificados' => $totales['ip'] ?? 0, 
            'egresos_planificados' => $totales['ep'] ?? 0,
            'ingresos_reales' => $totales['ir'] ?? 0, 
            'egresos_reales' => $totales['er'] ?? 0, 
            'total_items' => $totales['ti'] ?? 0,
        ]);
        
        $presupuesto['balance_planificado'] = $presupuesto['ingresos_planificados'] - $presupuesto['egresos_planificados'];
        $presupuesto['balance_real'] = $presupuesto['ingresos_reales'] - $presupuesto['egresos_reales'];
        
        // Obtener items del presupuesto
        $items = $db->fetchAll('
            SELECT pi.*, sc.nombre as subcategoria_nombre, cp.nombre as categoria_nombre 
            FROM presupuesto_items pi 
            LEFT JOIN subcategorias sc ON pi.subcategoria_id = sc.id 
            LEFT JOIN categorias_padre cp ON sc.categoria_padre_id = cp.id 
            WHERE pi.presupuesto_id = ? 
            ORDER BY pi.tipo, cp.nombre, sc.nombre', [$id]);
        break;
        
    default:
        $presupuestos_raw = $db->fetchAll('
            SELECT p.*, 
                COALESCE(SUM(CASE WHEN pi.tipo = "ingreso" THEN pi.monto_real ELSE 0 END), 0) as ir, 
                COALESCE(SUM(CASE WHEN pi.tipo = "egreso" THEN pi.monto_real ELSE 0 END), 0) as er, 
                COUNT(pi.id) as ti 
            FROM presupuestos p 
            LEFT JOIN presupuesto_items pi ON p.id = pi.presupuesto_id 
            WHERE p.usuario_id = ? AND p.activo = 1 
            GROUP BY p.id 
            ORDER BY p.fecha_inicio DESC', [$user_id]);
            
        $presupuestos = array_map(function($p) {
            $p['balance_real'] = $p['ir'] - $p['er'];
            return $p;
        }, $presupuestos_raw);
        break;
}
?>

<?php if ($action === 'list'): ?>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900"><i class="fas fa-calculator mr-2 text-blue-600"></i>Mis Presupuestos</h1>
        <a href="presupuestos.php?action=create" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md shadow-sm transition-colors"><i class="fas fa-plus mr-2"></i>Nuevo Presupuesto</a>
    </div>

    <?php if (!empty($presupuestos)):
        $balance_total = array_sum(array_column($presupuestos, 'balance_real')); ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-6 flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600"><i class="fas fa-folder text-xl"></i></div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Presupuestos</p>
                    <p class="text-2xl font-bold text-blue-600"><?= count($presupuestos); ?></p>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6 flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600"><i class="fas fa-arrow-up text-xl"></i></div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Ingresos Totales</p>
                    <p class="text-2xl font-bold text-green-600"><?= formatCurrency(array_sum(array_column($presupuestos, 'ir'))); ?></p>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6 flex items-center">
                <div class="p-3 rounded-full bg-red-100 text-red-600"><i class="fas fa-arrow-down text-xl"></i></div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Gastos Totales</p>
                    <p class="text-2xl font-bold text-red-600"><?= formatCurrency(array_sum(array_column($presupuestos, 'er'))); ?></p>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6 flex items-center">
                <div class="p-3 rounded-full <?= $balance_total >= 0 ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                    <i class="fas fa-balance-scale text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Balance General</p>
                    <p class="text-2xl font-bold <?= $balance_total >= 0 ? 'text-green-600' : 'text-red-600'; ?>"><?= formatCurrency($balance_total); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
             <h3 class="text-lg font-medium text-gray-900"><i class="fas fa-list mr-2"></i>Lista de Presupuestos</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Presupuesto</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Período</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance Real</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($presupuestos)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-4"></i>
                                <h3 class="text-lg font-medium mb-2">No tienes presupuestos creados</h3>
                                <p class="text-sm text-gray-400">Crea tu primer presupuesto para comenzar</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($presupuestos as $p): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($p['nombre']); ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($p['descripcion']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= formatDate($p['fecha_inicio']) . ' - ' . formatDate($p['fecha_fin']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"><?= $p['ti']; ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold <?= $p['balance_real'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>"><?= formatCurrency($p['balance_real']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <div class="flex items-center justify-center space-x-2">
                                        <a href="presupuestos.php?action=view&id=<?= $p['id']; ?>" class="text-blue-600 hover:text-blue-900 p-1 rounded" title="Ver Detalles"><i class="fas fa-eye"></i></a>
                                        <a href="presupuesto_items.php?presupuesto_id=<?= $p['id']; ?>" class="text-green-600 hover:text-green-900 p-1 rounded" title="Gestionar Items"><i class="fas fa-list"></i></a>
                                        <a href="presupuestos.php?action=edit&id=<?= $p['id']; ?>" class="text-indigo-600 hover:text-indigo-900 p-1 rounded" title="Editar"><i class="fas fa-edit"></i></a>
                                        <button onclick="deletePresupuesto(<?= $p['id']; ?>)" class="text-red-600 hover:text-red-900 p-1 rounded" title="Archivar"><i class="fas fa-trash"></i></button>
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
            <h1 class="text-2xl font-bold text-gray-900">
                <i class="fas <?= $action === 'create' ? 'fa-plus' : 'fa-edit'; ?> mr-2 text-blue-600"></i>
                <?= $action === 'create' ? 'Crear Nuevo Presupuesto' : 'Editar Presupuesto'; ?>
            </h1>
            <a href="presupuestos.php" class="text-gray-600 hover:text-gray-800 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i>Volver
            </a>
        </div>
        
        <div class="bg-white shadow-sm rounded-lg p-6">
            <form method="POST" action="presupuestos.php" class="space-y-6">
                <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update'; ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= htmlspecialchars($presupuesto['id']); ?>">
                <?php endif; ?>
                
                <div>
                    <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-tag mr-2"></i>Nombre del Presupuesto *
                    </label>
                    <input type="text" id="nombre" name="nombre" required 
                           value="<?= htmlspecialchars($presupuesto['nombre'] ?? ''); ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Ej: Presupuesto Familiar 2025">
                </div>
                
                <div>
                    <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-align-left mr-2"></i>Descripción
                    </label>
                    <textarea id="descripcion" name="descripcion" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Descripción opcional del presupuesto"><?= htmlspecialchars($presupuesto['descripcion'] ?? ''); ?></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="fecha_inicio" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar-alt mr-2"></i>Fecha de Inicio *
                        </label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" required 
                               value="<?= $presupuesto['fecha_inicio'] ?? ''; ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="fecha_fin" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar-check mr-2"></i>Fecha de Fin *
                        </label>
                        <input type="date" id="fecha_fin" name="fecha_fin" required 
                               value="<?= $presupuesto['fecha_fin'] ?? ''; ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 pt-6 border-t">
                    <a href="presupuestos.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded-md">Cancelar</a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md">
                        <i class="fas <?= $action === 'create' ? 'fa-plus' : 'fa-save'; ?> mr-2"></i>
                        <?= $action === 'create' ? 'Crear' : 'Actualizar'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($action === 'view'): ?>
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">
                <i class="fas fa-eye mr-2 text-blue-600"></i>
                <?= htmlspecialchars($presupuesto['nombre']); ?>
            </h1>
            <div class="flex space-x-2">
                <a href="presupuesto_items.php?presupuesto_id=<?= $presupuesto['id']; ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md">
                    <i class="fas fa-list mr-2"></i>Gestionar Items
                </a>
                <a href="presupuestos.php?action=edit&id=<?= $presupuesto['id']; ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md">
                    <i class="fas fa-edit mr-2"></i>Editar
                </a>
                <a href="presupuestos.php" class="text-gray-600 hover:text-gray-800 px-4 py-2 rounded-md border">
                    <i class="fas fa-arrow-left mr-2"></i>Volver
                </a>
            </div>
        </div>

        <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <h3 class="text-sm font-medium text-gray-500">Descripción</h3>
                    <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($presupuesto['descripcion']) ?: 'Sin descripción'; ?></p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500">Período</h3>
                    <p class="mt-1 text-sm text-gray-900"><?= formatDate($presupuesto['fecha_inicio']) . ' - ' . formatDate($presupuesto['fecha_fin']); ?></p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500">Total de Items</h3>
                    <p class="mt-1 text-sm text-gray-900"><?= $presupuesto['total_items']; ?> items</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-arrow-up text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Ingresos Planificados</p>
                        <p class="text-xl font-bold text-green-600"><?= formatCurrency($presupuesto['ingresos_planificados']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                        <i class="fas fa-arrow-down text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Gastos Planificados</p>
                        <p class="text-xl font-bold text-red-600"><?= formatCurrency($presupuesto['egresos_planificados']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-balance-scale text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Balance Planificado</p>
                        <p class="text-xl font-bold <?= $presupuesto['balance_planificado'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?= formatCurrency($presupuesto['balance_planificado']); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full <?= $presupuesto['balance_real'] >= 0 ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                        <i class="fas fa-chart-line text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Balance Real</p>
                        <p class="text-xl font-bold <?= $presupuesto['balance_real'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?= formatCurrency($presupuesto['balance_real']); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">
                    <i class="fas fa-list mr-2"></i>Items del Presupuesto
                </h3>
            </div>
            
            <?php if (empty($items)): ?>
                <div class="px-6 py-12 text-center text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-4"></i>
                    <h3 class="text-lg font-medium mb-2">No hay items en este presupuesto</h3>
                    <p class="text-sm text-gray-400 mb-4">Agrega ingresos y gastos para comenzar</p>
                    <a href="presupuesto_items.php?presupuesto_id=<?= $presupuesto['id']; ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                        <i class="fas fa-plus mr-2"></i>Agregar Items
                    </a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descripción</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoría</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Planificado</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Real</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Diferencia</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($items as $item): 
                                $diferencia = $item['monto_real'] - $item['monto_planificado'];
                                $es_positiva = ($item['tipo'] === 'ingreso' && $diferencia >= 0) || ($item['tipo'] === 'egreso' && $diferencia <= 0);
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $item['tipo'] === 'ingreso' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <i class="fas <?= $item['tipo'] === 'ingreso' ? 'fa-arrow-up' : 'fa-arrow-down'; ?> mr-1"></i>
                                            <?= ucfirst($item['tipo']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item['descripcion']); ?></div>
                                        <?php if ($item['notas']): ?>
                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($item['notas']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= htmlspecialchars($item['categoria_nombre'] . ' / ' . $item['subcategoria_nombre']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                        <?= formatCurrency($item['monto_planificado']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                        <?= formatCurrency($item['monto_real']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium <?= $es_positiva ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?= formatCurrency($diferencia); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function deletePresupuesto(id) {
    Swal.fire({
        title: '¿Archivar presupuesto?',
        text: 'Esta acción no lo eliminará, solo lo ocultará de la lista principal.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, archivar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'presupuestos.php';
            form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="${id}">`;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const fechaInicio = document.getElementById('fecha_inicio');
    const fechaFin = document.getElementById('fecha_fin');
    
    if (fechaInicio && fechaFin) {
        function validarFechas() {
            if (fechaInicio.value && fechaFin.value) {
                if (fechaInicio.value > fechaFin.value) {
                    fechaFin.setCustomValidity('La fecha de fin debe ser posterior a la fecha de inicio');
                } else {
                    fechaFin.setCustomValidity('');
                }
            }
        }
        
        fechaInicio.addEventListener('change', validarFechas);
        fechaFin.addEventListener('change', validarFechas);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>