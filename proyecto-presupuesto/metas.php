<?php
// Archivo: proyecto-presupuesto/metas.php
$page_title = 'Gestión de Metas';
require_once __DIR__ . '/includes/header.php';

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Obtener categorías para el formulario
$categorias = $db->fetchAll('SELECT * FROM categorias_padre WHERE activo = 1 ORDER BY nombre');

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    
    switch ($action) {
        case 'create':
            $nombre = sanitizeInput($_POST['nombre']);
            $descripcion = sanitizeInput($_POST['descripcion']);
            $monto_objetivo = floatval($_POST['monto_objetivo']);
            $fecha_objetivo = $_POST['fecha_objetivo'];
            $categoria_id = $_POST['categoria_id'] ?: null;
            
            if (empty($nombre) || $monto_objetivo <= 0 || empty($fecha_objetivo)) {
                $_SESSION['error_message'] = 'Todos los campos requeridos deben ser completados';
            } elseif ($fecha_objetivo <= date('Y-m-d')) {
                $_SESSION['error_message'] = 'La fecha objetivo debe ser futura';
            } else {
                $data = [
                    'usuario_id' => $user_id,
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'monto_objetivo' => $monto_objetivo,
                    'fecha_objetivo' => $fecha_objetivo,
                    'categoria_id' => $categoria_id
                ];
                
                if ($db->insert('metas', $data)) {
                    $_SESSION['success_message'] = 'Meta creada exitosamente';
                    redirect('metas.php');
                } else {
                    $_SESSION['error_message'] = 'Error al crear la meta';
                }
            }
            break;
            
        case 'add_progress':
            $meta_id = $_POST['meta_id'];
            $fecha = $_POST['fecha'];
            $monto = floatval($_POST['monto']);
            $descripcion = sanitizeInput($_POST['descripcion']);
            
            if (empty($fecha) || $monto <= 0) {
                $_SESSION['error_message'] = 'Todos los campos requeridos deben ser completados';
            } else {
                $data = [
                    'meta_id' => $meta_id,
                    'fecha' => $fecha,
                    'monto' => $monto,
                    'descripcion' => $descripcion
                ];
                
                if ($db->insert('metas_progreso', $data)) {
                    $_SESSION['success_message'] = 'Progreso agregado exitosamente';
                } else {
                    $_SESSION['error_message'] = 'Error al agregar el progreso';
                }
            }
            redirect('metas.php?action=view&id=' . $meta_id);
            break;
            
        case 'update':
            $id = $_POST['id'];
            $nombre = sanitizeInput($_POST['nombre']);
            $descripcion = sanitizeInput($_POST['descripcion']);
            $monto_objetivo = floatval($_POST['monto_objetivo']);
            $fecha_objetivo = $_POST['fecha_objetivo'];
            $categoria_id = $_POST['categoria_id'] ?: null;
            
            if (empty($nombre) || $monto_objetivo <= 0 || empty($fecha_objetivo)) {
                $_SESSION['error_message'] = 'Todos los campos requeridos deben ser completados';
            } else {
                $data = [
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'monto_objetivo' => $monto_objetivo,
                    'fecha_objetivo' => $fecha_objetivo,
                    'categoria_id' => $categoria_id
                ];
                
                if ($db->update('metas', $data, 'id = ? AND usuario_id = ?', [$id, $user_id])) {
                    $_SESSION['success_message'] = 'Meta actualizada exitosamente';
                    redirect('metas.php');
                } else {
                    $_SESSION['error_message'] = 'Error al actualizar la meta';
                }
            }
            break;
            
        case 'delete':
            $id = $_POST['id'];
            if ($db->update('metas', ['activo' => 0], 'id = ? AND usuario_id = ?', [$id, $user_id])) {
                $_SESSION['success_message'] = 'Meta eliminada exitosamente';
            } else {
                $_SESSION['error_message'] = 'Error al eliminar la meta';
            }
            redirect('metas.php');
            break;
    }
}

// Obtener datos según la acción
switch ($action) {
    case 'create':
    case 'edit':
        $meta = null;
        if ($action === 'edit' && $id) {
            $meta = $db->fetchOne('SELECT * FROM metas WHERE id = ? AND usuario_id = ?', [$id, $user_id]);
            if (!$meta) {
                $_SESSION['error_message'] = 'Meta no encontrada';
                redirect('metas.php');
            }
        }
        break;
        
    case 'view':
        if (!$id) {
            redirect('metas.php');
        }
        
        // Obtener meta con cálculos manuales
        $meta = $db->fetchOne('
            SELECT m.*, cp.nombre as categoria_nombre,
                   ROUND((m.monto_actual / m.monto_objetivo) * 100, 2) as porcentaje_progreso,
                   DATEDIFF(m.fecha_objetivo, CURDATE()) as dias_restantes
            FROM metas m
            LEFT JOIN categorias_padre cp ON m.categoria_id = cp.id
            WHERE m.id = ? AND m.usuario_id = ?
        ', [$id, $user_id]);
        
        if (!$meta) {
            $_SESSION['error_message'] = 'Meta no encontrada';
            redirect('metas.php');
        }
        
        // Obtener progreso de la meta
        $progreso = $db->fetchAll('SELECT * FROM metas_progreso WHERE meta_id = ? ORDER BY fecha DESC', [$id]);
        
        break;
        
    default:
        // Obtener lista de metas con cálculos manuales
        $metas = $db->fetchAll('
            SELECT m.*, cp.nombre as categoria_nombre,
                   ROUND((m.monto_actual / m.monto_objetivo) * 100, 2) as porcentaje_progreso,
                   DATEDIFF(m.fecha_objetivo, CURDATE()) as dias_restantes
            FROM metas m
            LEFT JOIN categorias_padre cp ON m.categoria_id = cp.id
            WHERE m.usuario_id = ? AND m.activo = 1
            ORDER BY m.fecha_objetivo ASC
        ', [$user_id]);
        
        break;
}
?>

<?php if ($action === 'list'): ?>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Mis Metas</h1>
        <a href="metas.php?action=create" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
            <i class="fas fa-plus mr-2"></i>Nueva Meta
        </a>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (empty($metas)): ?>
            <div class="col-span-full text-center py-12">
                <i class="fas fa-bullseye text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No tienes metas creadas</h3>
                <p class="text-gray-600 mb-4">Crea tu primera meta para empezar a alcanzar tus objetivos financieros</p>
                <a href="metas.php?action=create" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                    <i class="fas fa-plus mr-2"></i>Crear Meta
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($metas as $meta): ?>
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 <?php echo $meta['completado'] ? 'border-green-500' : ($meta['dias_restantes'] < 0 ? 'border-red-500' : 'border-blue-500'); ?>">
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($meta['nombre']); ?></h3>
                        <div class="flex space-x-2">
                            <a href="metas.php?action=view&id=<?php echo $meta['id']; ?>" class="text-blue-600 hover:text-blue-900">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="metas.php?action=edit&id=<?php echo $meta['id']; ?>" class="text-indigo-600 hover:text-indigo-900">
                                <i class="fas fa-edit"></i>
                            </a>
                        </div>
                    </div>
                    
                    <?php if ($meta['descripcion']): ?>
                        <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($meta['descripcion']); ?></p>
                    <?php endif; ?>
                    
                    <div class="mb-4">
                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                            <span><?php echo formatCurrency($meta['monto_actual']); ?></span>
                            <span><?php echo formatCurrency($meta['monto_objetivo']); ?></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="<?php echo $meta['completado'] ? 'bg-green-600' : 'bg-blue-600'; ?> h-2 rounded-full" style="width: <?php echo min(100, $meta['porcentaje_progreso']); ?>%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span><?php echo $meta['porcentaje_progreso']; ?>% completado</span>
                            <span>
                                <?php if ($meta['completado']): ?>
                                    <i class="fas fa-check text-green-600"></i> Completado
                                <?php elseif ($meta['dias_restantes'] < 0): ?>
                                    <i class="fas fa-exclamation-triangle text-red-600"></i> Vencido
                                <?php else: ?>
                                    <?php echo $meta['dias_restantes']; ?> días restantes
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="text-xs text-gray-500">
                        <p>Fecha objetivo: <?php echo formatDate($meta['fecha_objetivo']); ?></p>
                        <?php if ($meta['categoria_nombre']): ?>
                            <p>Categoría: <?php echo htmlspecialchars($meta['categoria_nombre']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php elseif ($action === 'create' || $action === 'edit'): ?>
    <div class="max-w-2xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">
                <?php echo $action === 'create' ? 'Crear Nueva Meta' : 'Editar Meta'; ?>
            </h1>
            <a href="metas.php" class="text-gray-600 hover:text-gray-800">
                <i class="fas fa-arrow-left mr-2"></i>Volver
            </a>
        </div>
        
        <div class="bg-white shadow-md rounded-lg p-6">
            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" value="<?php echo $action === 'create' ? 'create' : 'update'; ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?php echo $meta['id']; ?>">
                <?php endif; ?>
                
                <div>
                    <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">Nombre de la Meta *</label>
                    <input type="text" 
                           id="nombre" 
                           name="nombre" 
                           required 
                           value="<?php echo htmlspecialchars($meta['nombre'] ?? ''); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                    <textarea id="descripcion" 
                              name="descripcion" 
                              rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Describe tu meta..."><?php echo htmlspecialchars($meta['descripcion'] ?? ''); ?></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="monto_objetivo" class="block text-sm font-medium text-gray-700 mb-2">Monto Objetivo *</label>
                        <input type="number" 
                               id="monto_objetivo" 
                               name="monto_objetivo" 
                               step="0.01" 
                               min="0"
                               required 
                               value="<?php echo $meta['monto_objetivo'] ?? ''; ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label for="fecha_objetivo" class="block text-sm font-medium text-gray-700 mb-2">Fecha Objetivo *</label>
                        <input type="date" 
                               id="fecha_objetivo" 
                               name="fecha_objetivo" 
                               required 
                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                               value="<?php echo $meta['fecha_objetivo'] ?? ''; ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div>
                    <label for="categoria_id" class="block text-sm font-medium text-gray-700 mb-2">Categoría (Opcional)</label>
                    <select id="categoria_id" 
                            name="categoria_id" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Sin categoría</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?php echo $categoria['id']; ?>" 
                                    <?php echo ($meta['categoria_id'] ?? '') == $categoria['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($categoria['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <a href="metas.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">
                        Cancelar
                    </a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                        <?php echo $action === 'create' ? 'Crear Meta' : 'Actualizar Meta'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($action === 'view'): ?>
    <div class="mb-6">
        <div class="flex justify-between items-center mb-4">
            <h1 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($meta['nombre']); ?></h1>
            <div class="flex space-x-2">
                <a href="metas.php?action=edit&id=<?php echo $meta['id']; ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                    <i class="fas fa-edit mr-2"></i>Editar
                </a>
                <a href="metas.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">
                    <i class="fas fa-arrow-left mr-2"></i>Volver
                </a>
            </div>
        </div>
        
        <div class="bg-white shadow-md rounded-lg p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="text-center">
                    <p class="text-sm text-gray-600">Monto Actual</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo formatCurrency($meta['monto_actual']); ?></p>
                </div>
                <div class="text-center">
                    <p class="text-sm text-gray-600">Monto Objetivo</p>
                    <p class="text-2xl font-bold text-blue-600"><?php echo formatCurrency($meta['monto_objetivo']); ?></p>
                </div>
                <div class="text-center">
                    <p class="text-sm text-gray-600">Progreso</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $meta['porcentaje_progreso']; ?>%</p>
                </div>
                <div class="text-center">
                    <p class="text-sm text-gray-600">Días Restantes</p>
                    <p class="text-2xl font-bold <?php echo $meta['dias_restantes'] < 0 ? 'text-red-600' : 'text-gray-800'; ?>">
                        <?php echo $meta['dias_restantes'] < 0 ? 'Vencido' : $meta['dias_restantes']; ?>
                    </p>
                </div>
            </div>
            
            <div class="mb-4">
                <div class="w-full bg-gray-200 rounded-full h-4">
                    <div class="<?php echo $meta['completado'] ? 'bg-green-600' : 'bg-blue-600'; ?> h-4 rounded-full" style="width: <?php echo min(100, $meta['porcentaje_progreso']); ?>%"></div>
                </div>
            </div>
            
            <?php if ($meta['descripcion']): ?>
                <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($meta['descripcion']); ?></p>
            <?php endif; ?>
            
            <div class="flex justify-between text-sm text-gray-600">
                <span>Fecha objetivo: <?php echo formatDate($meta['fecha_objetivo']); ?></span>
                <?php if ($meta['categoria_nombre']): ?>
                    <span>Categoría: <?php echo htmlspecialchars($meta['categoria_nombre']); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Agregar Progreso -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white shadow-md rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Agregar Progreso</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_progress">
                <input type="hidden" name="meta_id" value="<?php echo $meta['id']; ?>">
                
                <div>
                    <label for="fecha" class="block text-sm font-medium text-gray-700 mb-2">Fecha *</label>
                    <input type="date" 
                           id="fecha" 
                           name="fecha" 
                           required 
                           value="<?php echo date('Y-m-d'); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="monto" class="block text-sm font-medium text-gray-700 mb-2">Monto *</label>
                    <input type="number" 
                           id="monto" 
                           name="monto" 
                           step="0.01" 
                           min="0"
                           required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                    <input type="text" 
                           id="descripcion" 
                           name="descripcion" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Descripción del progreso...">
                </div>
                
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                    <i class="fas fa-plus mr-2"></i>Agregar Progreso
                </button>
            </form>
        </div>
        
        <!-- Historial de Progreso -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Historial de Progreso</h3>
            <div class="space-y-4 max-h-96 overflow-y-auto">
                <?php if (empty($progreso)): ?>
                    <p class="text-gray-500 text-center py-8">No hay progreso registrado</p>
                <?php else: ?>
                    <?php foreach ($progreso as $p): ?>
                        <div class="border rounded-lg p-4">
                            <div class="flex justify-between items-start mb-2">
                                <div class="font-medium text-gray-900"><?php echo formatCurrency($p['monto']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo formatDate($p['fecha']); ?></div>
                            </div>
                            <?php if ($p['descripcion']): ?>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($p['descripcion']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>