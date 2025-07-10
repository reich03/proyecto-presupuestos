<?php
$page_title = 'Gestión de Transacciones';
require_once __DIR__ . '/includes/header.php';

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$item_id = $_GET['item_id'] ?? null;

$items_presupuesto = $db->fetchAll('
    SELECT pi.id, pi.nombre, p.nombre as presupuesto_nombre, pi.tipo
    FROM presupuesto_items pi
    JOIN presupuestos p ON pi.presupuesto_id = p.id
    WHERE p.usuario_id = ? AND p.activo = 1
    ORDER BY p.nombre, pi.nombre
', [$user_id]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_post = $_POST['action'] ?? 'create';
    
    switch ($action_post) {
        case 'create':
        case 'update':
            $presupuesto_item_id = $_POST['presupuesto_item_id'];
            $fecha = $_POST['fecha'];
            $monto = floatval($_POST['monto']);
            $descripcion = sanitizeInput($_POST['descripcion']);
            $tipo = $_POST['tipo'];
            
            if (empty($presupuesto_item_id) || empty($fecha) || $monto <= 0) {
                $_SESSION['error_message'] = 'El item del presupuesto, la fecha y un monto mayor a cero son requeridos.';
            } else {
                $data = compact('presupuesto_item_id', 'fecha', 'monto', 'descripcion', 'tipo');
                
                if ($action_post === 'create') {
                    if ($db->insert('transacciones', $data)) {
                        $_SESSION['success_message'] = 'Transacción registrada exitosamente.';
                    } else {
                        $_SESSION['error_message'] = 'Error al registrar la transacción.';
                    }
                } else {
                    $id = $_POST['id'];
                    $item_exists = $db->fetchOne('SELECT t.id FROM transacciones t JOIN presupuesto_items pi ON t.presupuesto_item_id=pi.id JOIN presupuestos p ON pi.presupuesto_id=p.id WHERE t.id = ? AND p.usuario_id = ?', [$id, $user_id]);
                    if($item_exists){
                        if ($db->update('transacciones', $data, 'id = ?', [$id])) {
                            $_SESSION['success_message'] = 'Transacción actualizada exitosamente.';
                        } else {
                            $_SESSION['error_message'] = 'Error al actualizar la transacción.';
                        }
                    }
                }
                redirect('transacciones.php');
            }
            break;
            
        case 'delete':
            $id = $_POST['id'];
            $item_exists = $db->fetchOne('SELECT t.id FROM transacciones t JOIN presupuesto_items pi ON t.presupuesto_item_id=pi.id JOIN presupuestos p ON pi.presupuesto_id=p.id WHERE t.id = ? AND p.usuario_id = ?', [$id, $user_id]);
            if($item_exists){
                if ($db->delete('transacciones', 'id = ?', [$id])) {
                    $_SESSION['success_message'] = 'Transacción eliminada exitosamente.';
                } else {
                    $_SESSION['error_message'] = 'Error al eliminar la transacción.';
                }
            }
            redirect('transacciones.php');
            break;
    }
}

switch ($action) {
    case 'create':
    case 'edit':
        $transaccion = null;
        if ($action === 'edit' && $id) {
            $transaccion = $db->fetchOne('SELECT t.* FROM transacciones t JOIN presupuesto_items pi ON t.presupuesto_item_id = pi.id JOIN presupuestos p ON pi.presupuesto_id = p.id WHERE t.id = ? AND p.usuario_id = ?', [$id, $user_id]);
            if (!$transaccion) {
                $_SESSION['error_message'] = 'Transacción no encontrada.';
                redirect('transacciones.php');
            }
        }
        break;
        
    default:
        $where_conditions = ['p.usuario_id = ?'];
        $params = [$user_id];
        if ($item_id) {
            $where_conditions[] = 'pi.id = ?';
            $params[] = $item_id;
        }
        $where_clause = implode(' AND ', $where_conditions);
        $transacciones = $db->fetchAll("SELECT t.*, pi.nombre as item_nombre, p.nombre as presupuesto_nombre, sc.nombre as subcategoria_nombre, cp.nombre as categoria_nombre FROM transacciones t JOIN presupuesto_items pi ON t.presupuesto_item_id = pi.id JOIN presupuestos p ON pi.presupuesto_id = p.id JOIN subcategorias sc ON pi.subcategoria_id = sc.id JOIN categorias_padre cp ON sc.categoria_padre_id = cp.id WHERE $where_clause ORDER BY t.fecha DESC, t.created_at DESC", $params);
        break;
}
?>

<?php if ($action === 'list'): ?>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900"><i class="fas fa-exchange-alt mr-2 text-blue-600"></i>Transacciones</h1>
        <a href="transacciones.php?action=create" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md shadow-sm transition-colors <?= empty($items_presupuesto) ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= empty($items_presupuesto) ? 'onclick="event.preventDefault(); Swal.fire(\'Acción Requerida\',\'Primero debe crear un presupuesto con items.\',\'info\');"' : '' ?>>
            <i class="fas fa-plus mr-2"></i>Nueva Transacción
        </a>
    </div>
    
    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900"><i class="fas fa-list mr-2"></i>Lista de Transacciones</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descripción</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item / Categoría</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monto</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($transacciones)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="text-gray-500">
                                    <i class="fas fa-inbox text-4xl mb-4"></i>
                                    <h3 class="text-lg font-medium mb-2">No hay transacciones registradas</h3>
                                    <p class="text-sm mb-4">Registra tu primera transacción para verla aquí.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transacciones as $t): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= formatDate($t['fecha']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($t['descripcion'] ?: 'N/A'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($t['item_nombre']); ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($t['categoria_nombre']); ?> / <?= htmlspecialchars($t['presupuesto_nombre']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold <?= $t['tipo'] === 'ingreso' ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?= ($t['tipo'] === 'egreso' ? '-' : '+') . formatCurrency($t['monto']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <div class="flex items-center justify-center space-x-2">
                                        <a href="transacciones.php?action=edit&id=<?= $t['id']; ?>" class="text-indigo-600 hover:text-indigo-900 p-1 rounded" title="Editar"><i class="fas fa-edit"></i></a>
                                        <button onclick="deleteTransaction(<?= $t['id']; ?>)" class="text-red-600 hover:text-red-900 p-1 rounded" title="Eliminar"><i class="fas fa-trash"></i></button>
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
        <div class="flex justify-between items-center mb-6"><h1 class="text-2xl font-bold text-gray-900"><i class="fas <?= $action === 'create' ? 'fa-plus' : 'fa-edit'; ?> mr-2 text-blue-600"></i><?= $action === 'create' ? 'Registrar Transacción' : 'Editar Transacción'; ?></h1><a href="transacciones.php" class="text-gray-600 hover:text-gray-800"><i class="fas fa-arrow-left mr-2"></i>Volver</a></div>
        <?php if (empty($items_presupuesto)): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6 text-center">
                <i class="fas fa-exclamation-triangle text-yellow-600 text-3xl mb-3"></i>
                <h3 class="text-lg font-medium text-yellow-800">No tienes items de presupuesto</h3>
                <p class="text-yellow-700 mt-1">Para registrar una transacción, primero debes crear un presupuesto con al menos un item.</p>
                <div class="mt-4">
                    <a href="presupuesto_items.php?action=create" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-md transition-colors">
                        <i class="fas fa-plus mr-2"></i>Crear un Item ahora
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update'; ?>">
                    <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= $transaccion['id']; ?>"><?php endif; ?>
                    
                    <div>
                        <label for="presupuesto_item_id" class="block text-sm font-medium text-gray-700 mb-2"><i class="fas fa-tasks mr-2"></i>Item del Presupuesto *</label>
                        <select id="presupuesto_item_id" name="presupuesto_item_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="updateTipo()">
                            <option value="">Seleccione un item</option>
                            <?php foreach ($items_presupuesto as $item): ?>
                                <option value="<?= $item['id']; ?>" data-tipo="<?= $item['tipo']; ?>" <?= ($transaccion['presupuesto_item_id'] ?? $item_id) == $item['id'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($item['presupuesto_nombre'] . ' / ' . $item['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="fecha" class="block text-sm font-medium text-gray-700 mb-2"><i class="fas fa-calendar-alt mr-2"></i>Fecha *</label>
                            <input type="date" id="fecha" name="fecha" required value="<?= $transaccion['fecha'] ?? date('Y-m-d'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="monto" class="block text-sm font-medium text-gray-700 mb-2"><i class="fas fa-dollar-sign mr-2"></i>Monto *</label>
                            <input type="number" id="monto" name="monto" step="0.01" min="0.01" required value="<?= $transaccion['monto'] ?? ''; ?>" placeholder="0.00" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div>
                        <label for="tipo" class="block text-sm font-medium text-gray-700 mb-2"><i class="fas fa-exchange-alt mr-2"></i>Tipo de Transacción</label>
                        <input type="text" id="tipo_display" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed">
                        <input type="hidden" id="tipo" name="tipo" value="<?= $transaccion['tipo'] ?? '' ?>">
                    </div>

                    <div>
                        <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-2"><i class="fas fa-align-left mr-2"></i>Descripción (Opcional)</label>
                        <textarea id="descripcion" name="descripcion" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ej: Compra de mercado semanal"><?= htmlspecialchars($transaccion['descripcion'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-6 border-t">
                        <a href="transacciones.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded-md">Cancelar</a>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md"><i class="fas <?= $action === 'create' ? 'fa-plus' : 'fa-save'; ?> mr-2"></i><?= $action === 'create' ? 'Registrar' : 'Actualizar'; ?></button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function updateTipo() {
    const itemSelect = document.getElementById('presupuesto_item_id');
    const tipoInputHidden = document.getElementById('tipo');
    const tipoInputDisplay = document.getElementById('tipo_display');
    const selectedOption = itemSelect.options[itemSelect.selectedIndex];
    
    if (selectedOption && selectedOption.dataset.tipo) {
        const tipoValue = selectedOption.dataset.tipo;
        tipoInputHidden.value = tipoValue;
        tipoInputDisplay.value = tipoValue.charAt(0).toUpperCase() + tipoValue.slice(1);
    } else {
        tipoInputHidden.value = '';
        tipoInputDisplay.value = '';
    }
}

function deleteTransaction(id) {
    Swal.fire({
        title: '¿Eliminar transacción?',
        text: "Esta acción no se puede deshacer.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, ¡eliminar!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'transacciones.php'; 
            form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="${id}">`;
            document.body.appendChild(form);
            form.submit();
        }
    });
}


document.addEventListener('DOMContentLoaded', function() {
    <?php if ($action === 'create' || $action === 'edit'): ?>
        updateTipo();
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>