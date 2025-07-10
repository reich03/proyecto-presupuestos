<?php
// Archivo: proyecto-presupuesto/presupuestos.php
$page_title = 'Gestión de Presupuestos';
require_once __DIR__ . '/includes/header.php';

// --- LÓGICA PHP ---
$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    
    switch ($action) {
        case 'create':
            $nombre = sanitizeInput($_POST['nombre']);
            $descripcion = sanitizeInput($_POST['descripcion']);
            $fecha_inicio = $_POST['fecha_inicio'];
            $fecha_fin = $_POST['fecha_fin'];
            if (empty($nombre) || empty($fecha_inicio) || empty($fecha_fin)) {
                $_SESSION['error_message'] = 'Todos los campos requeridos deben ser completados';
            } elseif ($fecha_inicio > $fecha_fin) {
                $_SESSION['error_message'] = 'La fecha de inicio debe ser anterior a la fecha de fin';
            } else {
                $data = ['usuario_id' => $user_id, 'nombre' => $nombre, 'descripcion' => $descripcion, 'fecha_inicio' => $fecha_inicio, 'fecha_fin' => $fecha_fin];
                if ($db->insert('presupuestos', $data)) {
                    $nuevo_id = $db->lastInsertId();
                    $_SESSION['success_message'] = 'Presupuesto creado exitosamente. ¡Ahora puedes agregar items!';
                    redirect("presupuesto_items.php?action=create&presupuesto_id=$nuevo_id");
                } else {
                    $_SESSION['error_message'] = 'Error al crear el presupuesto';
                }
            }
            break;
            
        case 'update':
            $id = $_POST['id'];
            $nombre = sanitizeInput($_POST['nombre']);
            $descripcion = sanitizeInput($_POST['descripcion']);
            $fecha_inicio = $_POST['fecha_inicio'];
            $fecha_fin = $_POST['fecha_fin'];
            if (empty($nombre) || empty($fecha_inicio) || empty($fecha_fin)) {
                $_SESSION['error_message'] = 'Todos los campos requeridos deben ser completados';
            } elseif ($fecha_inicio > $fecha_fin) {
                $_SESSION['error_message'] = 'La fecha de inicio debe ser anterior a la fecha de fin';
            } else {
                $data = ['nombre' => $nombre, 'descripcion' => $descripcion, 'fecha_inicio' => $fecha_inicio, 'fecha_fin' => $fecha_fin];
                if ($db->update('presupuestos', $data, 'id = ? AND usuario_id = ?', [$id, $user_id])) {
                    $_SESSION['success_message'] = 'Presupuesto actualizado exitosamente';
                    redirect('presupuestos.php');
                } else {
                    $_SESSION['error_message'] = 'Error al actualizar el presupuesto';
                }
            }
            break;
            
        case 'delete':
            $id = $_POST['id'];
            if ($db->update('presupuestos', ['activo' => 0], 'id = ? AND usuario_id = ?', [$id, $user_id])) {
                $_SESSION['success_message'] = 'Presupuesto archivado exitosamente.';
            } else {
                $_SESSION['error_message'] = 'Error al archivar el presupuesto.';
            }
            redirect('presupuestos.php');
            break;
    }
}

// Obtener datos según la acción GET
switch ($action) {
    case 'create':
    case 'edit':
        $presupuesto = null;
        if ($action === 'edit' && $id) {
            $presupuesto = $db->fetchOne('SELECT * FROM presupuestos WHERE id = ? AND usuario_id = ?', [$id, $user_id]);
            if (!$presupuesto) {
                $_SESSION['error_message'] = 'Presupuesto no encontrado';
                redirect('presupuestos.php');
            }
        }
        break;
        
    case 'view':
        if (!$id) redirect('presupuestos.php');
        $presupuesto = $db->fetchOne('SELECT * FROM presupuestos WHERE id = ? AND usuario_id = ? AND activo = 1', [$id, $user_id]);
        if (!$presupuesto) {
            $_SESSION['error_message'] = 'Presupuesto no encontrado o archivado.';
            redirect('presupuestos.php');
        }
        $totales = $db->fetchOne('SELECT SUM(CASE WHEN pi.tipo = "ingreso" THEN pi.monto_planificado ELSE 0 END) as ip, SUM(CASE WHEN pi.tipo = "egreso" THEN pi.monto_planificado ELSE 0 END) as ep, SUM(CASE WHEN pi.tipo = "ingreso" THEN pi.monto_real ELSE 0 END) as ir, SUM(CASE WHEN pi.tipo = "egreso" THEN pi.monto_real ELSE 0 END) as er, COUNT(pi.id) as ti FROM presupuesto_items pi WHERE pi.presupuesto_id = ?', [$id]);
        $presupuesto = array_merge($presupuesto, [
            'ingresos_planificados' => $totales['ip'] ?? 0, 'egresos_planificados' => $totales['ep'] ?? 0,
            'ingresos_reales' => $totales['ir'] ?? 0, 'egresos_reales' => $totales['er'] ?? 0, 'total_items' => $totales['ti'] ?? 0,
        ]);
        $presupuesto['balance_real'] = $presupuesto['ingresos_reales'] - $presupuesto['egresos_reales'];
        $items = $db->fetchAll('SELECT pi.*, sc.nombre as subcategoria_nombre, cp.nombre as categoria_nombre, (SELECT COUNT(*) FROM transacciones t WHERE t.presupuesto_item_id = pi.id) as total_transacciones FROM presupuesto_items pi JOIN subcategorias sc ON pi.subcategoria_id = sc.id JOIN categorias_padre cp ON sc.categoria_padre_id = cp.id WHERE pi.presupuesto_id = ? ORDER BY pi.tipo, cp.nombre, sc.nombre', [$id]);
        break;
        
    default:
        $presupuestos_raw = $db->fetchAll('SELECT p.*, COALESCE(SUM(CASE WHEN pi.tipo = "ingreso" THEN pi.monto_real ELSE 0 END), 0) as ir, COALESCE(SUM(CASE WHEN pi.tipo = "egreso" THEN pi.monto_real ELSE 0 END), 0) as er, COUNT(pi.id) as ti FROM presupuestos p LEFT JOIN presupuesto_items pi ON p.id = pi.presupuesto_id WHERE p.usuario_id = ? AND p.activo = 1 GROUP BY p.id ORDER BY p.fecha_inicio DESC', [$user_id]);
        $presupuestos = array_map(function($p) {
            $p['balance_real'] = $p['ir'] - $p['er'];
            return $p;
        }, $presupuestos_raw);
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
        <h1 class="text-2xl font-bold text-gray-900"><i class="fas fa-calculator mr-2 text-blue-600"></i>Mis Presupuestos</h1>
        <a href="presupuestos.php?action=create" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md shadow-sm"><i class="fas fa-plus mr-2"></i>Nuevo Presupuesto</a>
    </div>

    <?php if (!empty($presupuestos)):
        $balance_total = array_sum(array_column($presupuestos, 'balance_real')); ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4 flex items-center"><div class="p-3 rounded-full bg-blue-100 text-blue-600"><i class="fas fa-folder text-xl"></i></div><div class="ml-4"><p class="text-sm text-gray-500">Total Presupuestos</p><p class="text-2xl font-bold text-blue-600"><?= count($presupuestos); ?></p></div></div>
            <div class="bg-white rounded-lg shadow p-4 flex items-center"><div class="p-3 rounded-full bg-green-100 text-green-600"><i class="fas fa-arrow-up text-xl"></i></div><div class="ml-4"><p class="text-sm text-gray-500">Ingresos Totales</p><p class="text-2xl font-bold text-green-600"><?= formatCurrency(array_sum(array_column($presupuestos, 'ir'))); ?></p></div></div>
            <div class="bg-white rounded-lg shadow p-4 flex items-center"><div class="p-3 rounded-full bg-red-100 text-red-600"><i class="fas fa-arrow-down text-xl"></i></div><div class="ml-4"><p class="text-sm text-gray-500">Gastos Totales</p><p class="text-2xl font-bold text-red-600"><?= formatCurrency(array_sum(array_column($presupuestos, 'er'))); ?></p></div></div>
            <div class="bg-white rounded-lg shadow p-4 flex items-center"><div class="p-3 rounded-full <?= $balance_total >= 0 ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>"><i class="fas fa-balance-scale text-xl"></i></div><div class="ml-4"><p class="text-sm text-gray-500">Balance General</p><p class="text-2xl font-bold <?= $balance_total >= 0 ? 'text-green-600' : 'text-red-600'; ?>"><?= formatCurrency($balance_total); ?></p></div></div>
        </div>
    <?php endif; ?>
    
    <div class="bg-white shadow-md rounded-lg overflow-hidden p-4">
        <table id="presupuestosTable" class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Presupuesto</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Período</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Items</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Balance Real</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($presupuestos as $p): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4"><div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($p['nombre']); ?></div></td>
                        <td class="px-6 py-4 text-sm text-gray-700"><?= formatDate($p['fecha_inicio']) . ' - ' . formatDate($p['fecha_fin']); ?></td>
                        <td class="px-6 py-4 text-center"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"><?= $p['ti']; ?></span></td>
                        <td class="px-6 py-4 text-sm font-bold <?= $p['balance_real'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>"><?= formatCurrency($p['balance_real']); ?></td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center space-x-2">
                                <a href="presupuestos.php?action=view&id=<?= $p['id']; ?>" class="bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold py-2 px-3 rounded-md" title="Ver Detalles"><i class="fas fa-eye"></i></a>
                                <a href="presupuesto_items.php?presupuesto_id=<?= $p['id']; ?>" class="bg-green-500 hover:bg-green-600 text-white text-xs font-bold py-2 px-3 rounded-md" title="Gestionar Items"><i class="fas fa-list"></i></a>
                                <a href="presupuestos.php?action=edit&id=<?= $p['id']; ?>" class="bg-indigo-500 hover:bg-indigo-600 text-white text-xs font-bold py-2 px-3 rounded-md" title="Editar"><i class="fas fa-edit"></i></a>
                                <button onclick="deletePresupuesto(<?= $p['id']; ?>)" class="bg-red-600 hover:bg-red-700 text-white text-xs font-bold py-2 px-3 rounded-md" title="Archivar"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($action === 'create' || $action === 'edit'): ?>
    <div class="max-w-2xl mx-auto">
        <div class="flex justify-between items-center mb-6"><h1 class="text-2xl font-bold text-gray-900"><i class="fas <?= $action === 'create' ? 'fa-plus' : 'fa-edit'; ?> mr-2 text-blue-600"></i><?= $action === 'create' ? 'Crear Nuevo Presupuesto' : 'Editar Presupuesto'; ?></h1><a href="presupuestos.php" class="text-gray-600 hover:text-gray-800"><i class="fas fa-arrow-left mr-2"></i>Volver</a></div>
        <div class="bg-white shadow-sm rounded-lg p-6"><form method="POST" class="space-y-6"><input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update'; ?>"><?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= $presupuesto['id']; ?>"><?php endif; ?><div><label for="nombre" class="block text-sm font-medium text-gray-700 mb-2"><i class="fas fa-tag mr-2"></i>Nombre del Presupuesto *</label><input type="text" id="nombre" name="nombre" required value="<?= htmlspecialchars($presupuesto['nombre'] ?? ''); ?>" placeholder="Ej: Presupuesto mensual Enero" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></div><div><label for="descripcion" class="block text-sm font-medium text-gray-700 mb-2"><i class="fas fa-align-left mr-2"></i>Descripción</label><textarea id="descripcion" name="descripcion" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Descripción opcional..."><?= htmlspecialchars($presupuesto['descripcion'] ?? ''); ?></textarea></div><div class="grid grid-cols-1 md:grid-cols-2 gap-4"><div><label for="fecha_inicio" class="block text-sm font-medium text-gray-700 mb-2"><i class="fas fa-calendar-alt mr-2"></i>Fecha de Inicio *</label><input type="date" id="fecha_inicio" name="fecha_inicio" required value="<?= $presupuesto['fecha_inicio'] ?? ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></div><div><label for="fecha_fin" class="block text-sm font-medium text-gray-700 mb-2"><i class="fas fa-calendar-check mr-2"></i>Fecha de Fin *</label><input type="date" id="fecha_fin" name="fecha_fin" required value="<?= $presupuesto['fecha_fin'] ?? ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></div></div><div class="flex justify-end space-x-3 pt-6 border-t"><a href="presupuestos.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded-md">Cancelar</a><button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md"><i class="fas <?= $action === 'create' ? 'fa-plus' : 'fa-save'; ?> mr-2"></i><?= $action === 'create' ? 'Crear' : 'Actualizar'; ?></button></div></form></div>
    </div>

<?php elseif ($action === 'view'): ?>
    <div class="mb-6"><div class="flex justify-between items-center mb-4"><h1 class="text-2xl font-bold text-gray-900"><i class="fas fa-eye mr-2 text-blue-600"></i><?= htmlspecialchars($presupuesto['nombre']); ?></h1><div class="flex space-x-2"><a href="presupuesto_items.php?action=create&presupuesto_id=<?= $presupuesto['id']; ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md"><i class="fas fa-plus mr-2"></i>Agregar Item</a><a href="presupuestos.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md"><i class="fas fa-arrow-left mr-2"></i>Volver</a></div></div><?php if ($presupuesto['descripcion']): ?><div class="bg-gray-50 rounded-lg p-4 mb-6"><p class="text-gray-700"><i class="fas fa-info-circle mr-2"></i><?= htmlspecialchars($presupuesto['descripcion']); ?></p></div><?php endif; ?><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6"><div class="bg-white rounded-lg shadow p-4 flex items-center"><div class="p-3 rounded-full bg-blue-100 text-blue-600"><i class="fas fa-calendar text-xl"></i></div><div class="ml-4"><p class="text-sm text-gray-500">Período</p><p class="text-lg font-semibold text-gray-900"><?= formatDate($presupuesto['fecha_inicio']) . ' al ' . formatDate($presupuesto['fecha_fin']); ?></p></div></div><div class="bg-white rounded-lg shadow p-4 flex items-center"><div class="p-3 rounded-full bg-green-100 text-green-600"><i class="fas fa-arrow-up text-xl"></i></div><div class="ml-4"><p class="text-sm text-gray-500">Ingresos Reales</p><p class="text-lg font-semibold text-green-600"><?= formatCurrency($presupuesto['ingresos_reales']); ?></p></div></div><div class="bg-white rounded-lg shadow p-4 flex items-center"><div class="p-3 rounded-full bg-red-100 text-red-600"><i class="fas fa-arrow-down text-xl"></i></div><div class="ml-4"><p class="text-sm text-gray-500">Gastos Reales</p><p class="text-lg font-semibold text-red-600"><?= formatCurrency($presupuesto['egresos_reales']); ?></p></div></div><div class="bg-white rounded-lg shadow p-4 flex items-center"><div class="p-3 rounded-full <?= $presupuesto['balance_real'] >= 0 ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>"><i class="fas fa-balance-scale text-xl"></i></div><div class="ml-4"><p class="text-sm text-gray-500">Balance</p><p class="text-lg font-semibold <?= $presupuesto['balance_real'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>"><?= formatCurrency($presupuesto['balance_real']); ?></p></div></div></div></div>
    <div class="bg-white shadow-md rounded-lg overflow-hidden p-4">
        <h3 class="text-lg font-medium text-gray-900 mb-4"><i class="fas fa-list mr-2"></i>Items del Presupuesto</h3>
        <?php if (empty($items)): ?>
            <div class="text-center py-12 text-gray-500"><i class="fas fa-inbox text-4xl mb-4"></i><h3 class="text-lg mb-2">No hay items en este presupuesto</h3><a href="presupuesto_items.php?action=create&presupuesto_id=<?= $presupuesto['id']; ?>" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"><i class="fas fa-plus mr-2"></i>Agregar primer item</a></div>
        <?php else: ?>
            <table id="itemsTable" class="w-full">
                <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoría</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Planificado</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Real</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Diferencia</th></tr></thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($items as $item): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4"><div class="text-sm text-gray-900"><?= htmlspecialchars($item['categoria_nombre']); ?></div><div class="text-xs text-gray-500"><?= htmlspecialchars($item['subcategoria_nombre']); ?></div></td>
                            <td class="px-6 py-4"><div class="font-medium"><?= htmlspecialchars($item['nombre']); ?></div></td>
                            <td class="px-6 py-4"><span class="inline-flex items-center px-2 py-1 text-xs rounded-full <?= $item['tipo'] === 'ingreso' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>"><i class="fas <?= $item['tipo'] === 'ingreso' ? 'fa-arrow-up' : 'fa-arrow-down'; ?> mr-1"></i><?= ucfirst($item['tipo']); ?></span></td>
                            <td class="px-6 py-4 font-semibold"><?= formatCurrency($item['monto_planificado']); ?></td>
                            <td class="px-6 py-4 font-semibold"><?= formatCurrency($item['monto_real']); ?></td>
                            <td class="px-6 py-4 font-medium <?php $diferencia = $item['monto_real'] - $item['monto_planificado']; echo $diferencia == 0 ? 'text-gray-600' : ($diferencia > 0 ? 'text-green-600' : 'text-red-600'); ?>"><?= formatCurrency($diferencia); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.tailwindcss.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Función de borrado
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

// INICIALIZACIÓN DE DATATABLES
$(document).ready(function() {
    const dtConfig = {
        destroy: true,
        language: { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
        dom: '<"grid grid-cols-1 md:grid-cols-2 gap-4 mb-4" <"flex items-center"l> <"flex items-center justify-end"f> >t<"grid grid-cols-1 md:grid-cols-2 gap-4 mt-4" <"flex items-center"i> <"flex items-center justify-end"p> >',
        responsive: true
    };

    // Inicialización condicional según la vista activa
    <?php if ($action === 'list'): ?>
        $('#presupuestosTable').DataTable({
            ...dtConfig,
            "columnDefs": [
                { "orderable": false, "targets": 4 } // Desactiva orden en la 5ta columna (Acciones)
            ]
        });
    <?php elseif ($action === 'view'): ?>
        $('#itemsTable').DataTable(dtConfig);
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>