<?php
// Archivo: proyecto-presupuesto/transacciones.php
$page_title = 'Gestión de Transacciones';
require_once __DIR__ . '/includes/header.php';

// --- LÓGICA PHP ---
$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$item_id = $_GET['item_id'] ?? null;

// Obtener items de presupuesto del usuario para los formularios
$items_presupuesto = $db->fetchAll('
    SELECT pi.id, pi.nombre, p.nombre as presupuesto_nombre, pi.tipo
    FROM presupuesto_items pi
    JOIN presupuestos p ON pi.presupuesto_id = p.id
    WHERE p.usuario_id = ? AND p.activo = 1
    ORDER BY p.nombre, pi.nombre
', [$user_id]);

// Procesar acciones POST
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
                    if ($db->update('transacciones', $data, 'id = ?', [$id])) {
                        $_SESSION['success_message'] = 'Transacción actualizada exitosamente.';
                    } else {
                        $_SESSION['error_message'] = 'Error al actualizar la transacción.';
                    }
                }
                redirect('transacciones.php');
            }
            break;
            
        case 'delete':
            $id = $_POST['id'];
            if ($db->delete('transacciones', 'id = ?', [$id])) {
                $_SESSION['success_message'] = 'Transacción eliminada exitosamente.';
            } else {
                $_SESSION['error_message'] = 'Error al eliminar la transacción.';
            }
            redirect('transacciones.php');
            break;
    }
}

// Obtener datos para las vistas GET
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

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.tailwindcss.min.css">
<style>
    .dataTables_filter input, .dataTables_length select {
        border: 1px solid #D1D5DB; border-radius: 0.375rem; padding: 0.5rem 0.75rem; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);
    }
    .dataTables_filter input:focus, .dataTables_length select:focus {
        --tw-ring-color: #3B82F6; border-color: #3B82F6; outline: none; box-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
    }
    .dataTables_paginate .paginate_button {
        padding: 0.5rem 1rem; margin-left: 0.25rem; border-radius: 0.375rem; border: 1px solid transparent;
    }
    .dataTables_paginate .paginate_button.current, .dataTables_paginate .paginate_button.current:hover {
        background-color: #3B82F6 !important; color: white !important; border-color: #3B82F6 !important;
    }
    .dataTables_paginate .paginate_button:hover {
        background-color: #E5E7EB !important; border-color: #D1D5DB !important;
    }
</style>

<?php if ($action === 'list'): ?>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900"><i class="fas fa-exchange-alt mr-2 text-blue-600"></i>Transacciones</h1>
        <a href="transacciones.php?action=create" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md shadow-sm <?= empty($items_presupuesto) ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= empty($items_presupuesto) ? 'onclick="event.preventDefault(); alert(\'Primero debe crear un presupuesto con items.\');"' : '' ?>>
            <i class="fas fa-plus mr-2"></i>Nueva Transacción
        </a>
    </div>
    
    <div class="bg-white shadow-md rounded-lg overflow-hidden p-4">
        <table id="transaccionesTable" class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descripción</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item / Categoría</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Monto</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($transacciones as $t): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-4 text-sm text-gray-800"><?= formatDate($t['fecha']); ?></td>
                        <td class="px-4 py-4 text-sm text-gray-800"><?= htmlspecialchars($t['descripcion'] ?: 'N/A'); ?></td>
                        <td class="px-4 py-4">
                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($t['item_nombre']); ?></div>
                            <div class="text-xs text-gray-500"><?= htmlspecialchars($t['categoria_nombre']); ?></div>
                        </td>
                        <td class="px-4 py-4 text-sm font-bold <?= $t['tipo'] === 'ingreso' ? 'text-green-600' : 'text-red-600'; ?>">
                            <?= ($t['tipo'] === 'egreso' ? '-' : '+') . formatCurrency($t['monto']); ?>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <div class="flex items-center justify-center space-x-2">
                                <a href="transacciones.php?action=edit&id=<?= $t['id']; ?>" class="bg-indigo-500 hover:bg-indigo-600 text-white text-xs font-bold py-2 px-3 rounded-md" title="Editar"><i class="fas fa-edit"></i></a>
                                <form method="POST" class="delete-form" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $t['id']; ?>">
                                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs font-bold py-2 px-3 rounded-md" title="Eliminar"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($action === 'create' || $action === 'edit'): ?>
    <div class="max-w-2xl mx-auto">
        <div class="flex justify-between items-center mb-6"><h1 class="text-2xl font-bold text-gray-900"><?= $action === 'create' ? 'Registrar Transacción' : 'Editar Transacción'; ?></h1><a href="transacciones.php" class="text-gray-600 hover:text-gray-800"><i class="fas fa-arrow-left mr-2"></i>Volver</a></div>
        <?php if (empty($items_presupuesto)): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4" role="alert"><p class="font-bold">Acción Requerida</p><p>Para registrar una transacción, primero debes tener al menos un "item" dentro de un presupuesto.</p><a href="presupuestos.php" class="mt-2 inline-block bg-blue-600 text-white px-3 py-1 rounded-md text-sm">Ir a Presupuestos</a></div>
        <?php else: ?>
            <div class="bg-white shadow-md rounded-lg p-6"><form method="POST" class="space-y-6"><input type="hidden" name="action" value="<?= $action; ?>"><?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= $transaccion['id']; ?>"><?php endif; ?><div><label for="presupuesto_item_id" class="block text-sm font-medium text-gray-700 mb-2">Item del Presupuesto *</label><select id="presupuesto_item_id" name="presupuesto_item_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="updateTipo()"><option value="">Seleccione un item</option><?php foreach ($items_presupuesto as $item): ?><option value="<?= $item['id']; ?>" data-tipo="<?= $item['tipo']; ?>" <?= ($transaccion['presupuesto_item_id'] ?? $item_id) == $item['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($item['presupuesto_nombre'] . ' / ' . $item['nombre']); ?></option><?php endforeach; ?></select></div><div class="grid grid-cols-1 md:grid-cols-2 gap-4"><div><label for="fecha" class="block text-sm font-medium text-gray-700 mb-2">Fecha *</label><input type="date" id="fecha" name="fecha" required value="<?= $transaccion['fecha'] ?? date('Y-m-d'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></div><div><label for="monto" class="block text-sm font-medium text-gray-700 mb-2">Monto *</label><input type="number" id="monto" name="monto" step="0.01" min="0.01" required value="<?= $transaccion['monto'] ?? ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></div></div><div><label for="tipo" class="block text-sm font-medium text-gray-700 mb-2">Tipo de Transacción *</label><select id="tipo" name="tipo" required class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" readonly><option value="ingreso" <?= ($transaccion['tipo'] ?? '') === 'ingreso' ? 'selected' : ''; ?>>Ingreso</option><option value="egreso" <?= ($transaccion['tipo'] ?? '') === 'egreso' ? 'selected' : ''; ?>>Egreso</option></select></div><div><label for="descripcion" class="block text-sm font-medium text-gray-700 mb-2">Descripción (Opcional)</label><textarea id="descripcion" name="descripcion" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($transaccion['descripcion'] ?? ''); ?></textarea></div><div class="flex justify-end space-x-3"><a href="transacciones.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-md">Cancelar</a><button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md"><?= $action === 'create' ? 'Registrar' : 'Actualizar'; ?></button></div></form></div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.tailwindcss.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Función para auto-seleccionar el tipo de transacción
function updateTipo() {
    const itemSelect = document.getElementById('presupuesto_item_id');
    const tipoSelect = document.getElementById('tipo');
    const selectedOption = itemSelect.options[itemSelect.selectedIndex];
    if (selectedOption && selectedOption.dataset.tipo) {
        tipoSelect.value = selectedOption.dataset.tipo;
    }
}

$(document).ready(function() {
    // Inicialización de DataTables para la lista de transacciones
    <?php if ($action === 'list'): ?>
        $('#transaccionesTable').DataTable({
            destroy: true,
            language: { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
            dom: '<"grid grid-cols-1 md:grid-cols-2 gap-4 mb-4"<"flex items-center"l><"flex items-center justify-end"f>>t<"grid grid-cols-1 md:grid-cols-2 gap-4 mt-4"<"flex items-center"i><"flex items-center justify-end"p>>',
            responsive: true,
            "order": [[ 0, "desc" ]], // Ordenar por fecha descendente por defecto
            "columnDefs": [
                { "orderable": false, "targets": 4 } // Desactiva orden en la columna de acciones
            ]
        });
    <?php endif; ?>

    // Script para el formulario de borrado con SweetAlert2
    $('.delete-form').on('submit', function(e) {
        e.preventDefault(); // Prevenir el envío inmediato del formulario
        const form = this;
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Esta acción no se puede revertir.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, ¡eliminar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit(); // Enviar el formulario si el usuario confirma
            }
        });
    });

    // Ejecutar la función al cargar la página en caso de que haya un item preseleccionado
    <?php if ($action === 'create' || $action === 'edit'): ?>
        updateTipo();
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>