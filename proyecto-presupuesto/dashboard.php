<?php
$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

// Obtener datos del dashboard
$user_id = $_SESSION['user_id'];

// Resumen de presupuestos
$sql_presupuestos = "SELECT COUNT(*) as total_presupuestos,
                     SUM(CASE WHEN DATE(fecha_fin) >= CURDATE() THEN 1 ELSE 0 END) as presupuestos_activos
                     FROM presupuestos WHERE usuario_id = ? AND activo = 1";
$resumen_presupuestos = $db->fetchOne($sql_presupuestos, [$user_id]);

// Resumen financiero del mes actual
$sql_resumen_mes = "SELECT 
    SUM(CASE WHEN pi.tipo = 'ingreso' THEN pi.monto_real ELSE 0 END) as ingresos_mes,
    SUM(CASE WHEN pi.tipo = 'egreso' THEN pi.monto_real ELSE 0 END) as gastos_mes,
    SUM(CASE WHEN pi.tipo = 'ingreso' THEN pi.monto_planificado ELSE 0 END) as ingresos_planificados,
    SUM(CASE WHEN pi.tipo = 'egreso' THEN pi.monto_planificado ELSE 0 END) as gastos_planificados
FROM presupuesto_items pi
JOIN presupuestos p ON pi.presupuesto_id = p.id
WHERE p.usuario_id = ? 
AND MONTH(p.fecha_inicio) = MONTH(CURDATE()) 
AND YEAR(p.fecha_inicio) = YEAR(CURDATE())
AND p.activo = 1";
$resumen_mes = $db->fetchOne($sql_resumen_mes, [$user_id]);

// Metas
$sql_metas = "SELECT COUNT(*) as total_metas,
              SUM(CASE WHEN completado = 1 THEN 1 ELSE 0 END) as metas_completadas,
              SUM(CASE WHEN DATE(fecha_objetivo) < CURDATE() AND completado = 0 THEN 1 ELSE 0 END) as metas_vencidas
              FROM metas WHERE usuario_id = ? AND activo = 1";
$resumen_metas = $db->fetchOne($sql_metas, [$user_id]);

// Transacciones recientes
$sql_transacciones = "SELECT t.*, pi.nombre as item_nombre, p.nombre as presupuesto_nombre
                      FROM transacciones t
                      JOIN presupuesto_items pi ON t.presupuesto_item_id = pi.id
                      JOIN presupuestos p ON pi.presupuesto_id = p.id
                      WHERE p.usuario_id = ?
                      ORDER BY t.fecha DESC, t.created_at DESC
                      LIMIT 10";
$transacciones_recientes = $db->fetchAll($sql_transacciones, [$user_id]);

// Gastos por categoría (últimos 30 días)
$sql_gastos_categoria = "SELECT cp.nombre as categoria,
                         SUM(t.monto) as total_gastado
                         FROM transacciones t
                         JOIN presupuesto_items pi ON t.presupuesto_item_id = pi.id
                         JOIN presupuestos p ON pi.presupuesto_id = p.id
                         JOIN subcategorias sc ON pi.subcategoria_id = sc.id
                         JOIN categorias_padre cp ON sc.categoria_padre_id = cp.id
                         WHERE p.usuario_id = ? 
                         AND t.tipo = 'egreso' 
                         AND t.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                         GROUP BY cp.id, cp.nombre
                         ORDER BY total_gastado DESC
                         LIMIT 5";
$gastos_por_categoria = $db->fetchAll($sql_gastos_categoria, [$user_id]);

// Progreso de metas próximas
$sql_metas_progreso = "SELECT * FROM v_progreso_metas 
                       WHERE usuario_id = ? 
                       AND completado = 0 
                       AND dias_restantes > 0
                       ORDER BY dias_restantes ASC
                       LIMIT 5";
$metas_progreso = $db->fetchAll($sql_metas_progreso, [$user_id]);

// Calcular balances
$balance_mes = ($resumen_mes['ingresos_mes'] ?? 0) - ($resumen_mes['gastos_mes'] ?? 0);
$balance_planificado = ($resumen_mes['ingresos_planificados'] ?? 0) - ($resumen_mes['gastos_planificados'] ?? 0);
?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Tarjeta de Presupuestos -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Presupuestos</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $resumen_presupuestos['total_presupuestos'] ?? 0; ?></p>
                <p class="text-xs text-gray-500"><?php echo ($resumen_presupuestos['presupuestos_activos'] ?? 0) . ' activos'; ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                <i class="fas fa-calculator text-blue-600"></i>
            </div>
        </div>
    </div>
    
    <!-- Tarjeta de Ingresos -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Ingresos del Mes</p>
                <p class="text-2xl font-bold text-green-600"><?php echo formatCurrency($resumen_mes['ingresos_mes'] ?? 0); ?></p>
                <p class="text-xs text-gray-500">Planificado: <?php echo formatCurrency($resumen_mes['ingresos_planificados'] ?? 0); ?></p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                <i class="fas fa-arrow-up text-green-600"></i>
            </div>
        </div>
    </div>
    
    <!-- Tarjeta de Gastos -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Gastos del Mes</p>
                <p class="text-2xl font-bold text-red-600"><?php echo formatCurrency($resumen_mes['gastos_mes'] ?? 0); ?></p>
                <p class="text-xs text-gray-500">Planificado: <?php echo formatCurrency($resumen_mes['gastos_planificados'] ?? 0); ?></p>
            </div>
            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                <i class="fas fa-arrow-down text-red-600"></i>
            </div>
        </div>
    </div>
    
    <!-- Tarjeta de Balance -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Balance del Mes</p>
                <p class="text-2xl font-bold <?php echo $balance_mes >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                    <?php echo formatCurrency($balance_mes); ?>
                </p>
                <p class="text-xs text-gray-500">Planificado: <?php echo formatCurrency($balance_planificado); ?></p>
            </div>
            <div class="w-12 h-12 <?php echo $balance_mes >= 0 ? 'bg-green-100' : 'bg-red-100'; ?> rounded-full flex items-center justify-center">
                <i class="fas fa-balance-scale <?php echo $balance_mes >= 0 ? 'text-green-600' : 'text-red-600'; ?>"></i>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Gráfico de Gastos por Categoría -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Gastos por Categoría (Últimos 30 días)</h3>
        <div class="h-80">
            <canvas id="gastosCategoria"></canvas>
        </div>
    </div>
    
    <!-- Progreso de Metas -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Progreso de Metas</h3>
        <div class="space-y-4">
            <?php if (empty($metas_progreso)): ?>
                <p class="text-gray-500 text-center py-8">No tienes metas activas</p>
            <?php else: ?>
                <?php foreach ($metas_progreso as $meta): ?>
                    <div class="border rounded-lg p-4">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($meta['nombre']); ?></h4>
                            <span class="text-sm text-gray-500"><?php echo $meta['dias_restantes']; ?> días</span>
                        </div>
                        <div class="mb-2">
                            <div class="flex justify-between text-sm text-gray-600">
                                <span><?php echo formatCurrency($meta['monto_actual']); ?></span>
                                <span><?php echo formatCurrency($meta['monto_objetivo']); ?></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo min(100, $meta['porcentaje_progreso']); ?>%"></div>
                            </div>
                        </div>
                        <div class="text-xs text-gray-500">
                            <?php echo $meta['porcentaje_progreso']; ?>% completado
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Transacciones Recientes -->
<div class="bg-white rounded-lg shadow-md p-6">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-800">Transacciones Recientes</h3>
        <a href="transacciones.php" class="text-blue-600 hover:text-blue-800 text-sm">Ver todas</a>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descripción</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Presupuesto</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monto</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($transacciones_recientes)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No hay transacciones recientes</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transacciones_recientes as $transaccion): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo formatDate($transaccion['fecha']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($transaccion['descripcion'] ?: $transaccion['item_nombre']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($transaccion['presupuesto_nombre']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $transaccion['tipo'] === 'ingreso' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo ucfirst($transaccion['tipo']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium <?php echo $transaccion['tipo'] === 'ingreso' ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo formatCurrency($transaccion['monto']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Gráfico de gastos por categoría
const gastosData = <?php echo json_encode($gastos_por_categoria); ?>;
const ctx = document.getElementById('gastosCategoria').getContext('2d');

new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: gastosData.map(item => item.categoria),
        datasets: [{
            data: gastosData.map(item => item.total_gastado),
            backgroundColor: [
                '#FF6384',
                '#36A2EB',
                '#FFCE56',
                '#4BC0C0',
                '#9966FF'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': $' + context.parsed.toLocaleString();
                    }
                }
            }
        }
    }
});
</script>

<?php 
require_once __DIR__ . '/includes/footer.php';

?>