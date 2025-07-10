<?php
$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

$usuario_info = $db->fetchOne('SELECT nombre, apellido, ultimo_login FROM usuarios WHERE id = ?', [$user_id]);


try {
    $sql_presupuestos = "SELECT COUNT(*) as total_presupuestos,
                           SUM(CASE WHEN DATE(fecha_fin) >= CURDATE() AND activo = 1 THEN 1 ELSE 0 END) as presupuestos_activos,
                           SUM(CASE WHEN DATE(fecha_fin) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND activo = 1 THEN 1 ELSE 0 END) as presupuestos_por_vencer
                           FROM presupuestos WHERE usuario_id = ?";
    $resumen_presupuestos = $db->fetchOne($sql_presupuestos, [$user_id]);

    $sql_resumen_mes = "SELECT 
        SUM(CASE WHEN pi.tipo = 'ingreso' THEN pi.monto_real ELSE 0 END) as ingresos_mes,
        SUM(CASE WHEN pi.tipo = 'egreso' THEN pi.monto_real ELSE 0 END) as gastos_mes,
        SUM(CASE WHEN pi.tipo = 'ingreso' THEN pi.monto_planificado ELSE 0 END) as ingresos_planificados,
        SUM(CASE WHEN pi.tipo = 'egreso' THEN pi.monto_planificado ELSE 0 END) as gastos_planificados
    FROM presupuesto_items pi
    JOIN presupuestos p ON pi.presupuesto_id = p.id
    WHERE p.usuario_id = ? 
    AND MONTH(p.fecha_inicio) <= MONTH(CURDATE()) 
    AND MONTH(p.fecha_fin) >= MONTH(CURDATE())
    AND YEAR(p.fecha_inicio) <= YEAR(CURDATE()) 
    AND YEAR(p.fecha_fin) >= YEAR(CURDATE())
    AND p.activo = 1";
    $resumen_mes = $db->fetchOne($sql_resumen_mes, [$user_id]);

    $sql_mes_anterior = "SELECT 
        SUM(CASE WHEN pi.tipo = 'ingreso' THEN pi.monto_real ELSE 0 END) as ingresos_anterior,
        SUM(CASE WHEN pi.tipo = 'egreso' THEN pi.monto_real ELSE 0 END) as gastos_anterior
    FROM presupuesto_items pi
    JOIN presupuestos p ON pi.presupuesto_id = p.id
    WHERE p.usuario_id = ? 
    AND MONTH(p.fecha_inicio) <= MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    AND MONTH(p.fecha_fin) >= MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    AND YEAR(p.fecha_inicio) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    AND p.activo = 1";
    $mes_anterior = $db->fetchOne($sql_mes_anterior, [$user_id]);

    $sql_metas = "SELECT COUNT(*) as total_metas,
                  SUM(CASE WHEN completado = 1 THEN 1 ELSE 0 END) as metas_completadas,
                  SUM(CASE WHEN DATE(fecha_objetivo) < CURDATE() AND completado = 0 THEN 1 ELSE 0 END) as metas_vencidas,
                  SUM(CASE WHEN DATE(fecha_objetivo) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND completado = 0 THEN 1 ELSE 0 END) as metas_proximas
                  FROM metas WHERE usuario_id = ? AND activo = 1";
    $resumen_metas = $db->fetchOne($sql_metas, [$user_id]);

    $sql_transacciones = "SELECT t.*, pi.descripcion as item_nombre, p.nombre as presupuesto_nombre
                          FROM transacciones t
                          JOIN presupuesto_items pi ON t.presupuesto_item_id = pi.id
                          JOIN presupuestos p ON pi.presupuesto_id = p.id
                          WHERE p.usuario_id = ? AND p.activo = 1
                          ORDER BY t.fecha DESC, t.created_at DESC
                          LIMIT 5";
    $transacciones_recientes = $db->fetchAll($sql_transacciones, [$user_id]);

    $sql_gastos_categoria = "SELECT cp.nombre as categoria, cp.color,
                               SUM(t.monto) as total_gastado,
                               COUNT(t.id) as num_transacciones
                               FROM transacciones t
                               JOIN presupuesto_items pi ON t.presupuesto_item_id = pi.id
                               JOIN presupuestos p ON pi.presupuesto_id = p.id
                               JOIN subcategorias sc ON pi.subcategoria_id = sc.id
                               JOIN categorias_padre cp ON sc.categoria_padre_id = cp.id
                               WHERE p.usuario_id = ? 
                               AND p.activo = 1 
                               AND t.tipo = 'egreso' 
                               AND t.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                               GROUP BY cp.id, cp.nombre, cp.color
                               ORDER BY total_gastado DESC
                               LIMIT 6";
    $gastos_por_categoria = $db->fetchAll($sql_gastos_categoria, [$user_id]);

    $sql_tendencias = "SELECT 
        DATE_FORMAT(p.fecha_inicio, '%Y-%m') as mes,
        DATE_FORMAT(p.fecha_inicio, '%M %Y') as mes_nombre,
        SUM(CASE WHEN pi.tipo = 'ingreso' THEN pi.monto_real ELSE 0 END) as ingresos,
        SUM(CASE WHEN pi.tipo = 'egreso' THEN pi.monto_real ELSE 0 END) as gastos
    FROM presupuestos p
    LEFT JOIN presupuesto_items pi ON p.id = pi.presupuesto_id
    WHERE p.usuario_id = ? 
    AND p.activo = 1
    AND p.fecha_inicio >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(p.fecha_inicio, '%Y-%m'), DATE_FORMAT(p.fecha_inicio, '%M %Y')
    ORDER BY mes ASC";
    $tendencias = $db->fetchAll($sql_tendencias, [$user_id]);

    $alertas = [];
    
    if ($resumen_presupuestos['presupuestos_por_vencer'] > 0) {
        $alertas[] = [
            'tipo' => 'warning',
            'icono' => 'fa-calendar-times',
            'mensaje' => $resumen_presupuestos['presupuestos_por_vencer'] . ' presupuesto(s) vencen en los próximos 7 días',
            'enlace' => 'presupuestos.php'
        ];
    }
    
    
    if ($resumen_metas['metas_proximas'] > 0) {
        $alertas[] = [
            'tipo' => 'info',
            'icono' => 'fa-bullseye',
            'mensaje' => $resumen_metas['metas_proximas'] . ' meta(s) se vencen en los próximos 7 días',
            'enlace' => 'metas.php'
        ];
    }
    
    $balance_mes = ($resumen_mes['ingresos_mes'] ?? 0) - ($resumen_mes['gastos_mes'] ?? 0);
    if ($balance_mes < -1000) {
        $alertas[] = [
            'tipo' => 'danger',
            'icono' => 'fa-exclamation-triangle',
            'mensaje' => 'Tu balance mensual está muy negativo: ' . formatCurrency($balance_mes),
            'enlace' => 'presupuestos.php'
        ];
    }

} catch (Exception $e) {
    error_log("Error en dashboard: " . $e->getMessage());
    $resumen_presupuestos = ['total_presupuestos' => 0, 'presupuestos_activos' => 0, 'presupuestos_por_vencer' => 0];
    $resumen_mes = ['ingresos_mes' => 0, 'gastos_mes' => 0, 'ingresos_planificados' => 0, 'gastos_planificados' => 0];
    $mes_anterior = ['ingresos_anterior' => 0, 'gastos_anterior' => 0];
    $resumen_metas = ['total_metas' => 0, 'metas_completadas' => 0, 'metas_vencidas' => 0, 'metas_proximas' => 0];
    $transacciones_recientes = [];
    $gastos_por_categoria = [];
    $tendencias = [];
    $alertas = [];
}

$balance_mes = ($resumen_mes['ingresos_mes'] ?? 0) - ($resumen_mes['gastos_mes'] ?? 0);
$balance_planificado = ($resumen_mes['ingresos_planificados'] ?? 0) - ($resumen_mes['gastos_planificados'] ?? 0);

$cambio_ingresos = 0;
$cambio_gastos = 0;
if (($mes_anterior['ingresos_anterior'] ?? 0) > 0) {
    $cambio_ingresos = ((($resumen_mes['ingresos_mes'] ?? 0) - ($mes_anterior['ingresos_anterior'] ?? 0)) / ($mes_anterior['ingresos_anterior'] ?? 0)) * 100;
}
if (($mes_anterior['gastos_anterior'] ?? 0) > 0) {
    $cambio_gastos = ((($resumen_mes['gastos_mes'] ?? 0) - ($mes_anterior['gastos_anterior'] ?? 0)) / ($mes_anterior['gastos_anterior'] ?? 0)) * 100;
}

$mes_actual = date('F Y');
$mes_actual_es = [
    'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril',
    'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto',
    'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'
];
$mes_actual = str_replace(array_keys($mes_actual_es), array_values($mes_actual_es), $mes_actual);
?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">
                ¡Hola, <?= htmlspecialchars($usuario_info['nombre']); ?>! 👋
            </h1>
            <p class="text-gray-600">Resumen de tus finanzas para <?= $mes_actual; ?></p>
            <?php if ($usuario_info['ultimo_login']): ?>
                <p class="text-sm text-gray-500">Último acceso: <?= formatDateTime($usuario_info['ultimo_login']); ?></p>
            <?php endif; ?>
        </div>
        <div class="mt-4 md:mt-0 flex space-x-2">
            <a href="presupuestos.php?action=create" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm">
                <i class="fas fa-plus mr-2"></i>Nuevo Presupuesto
            </a>
            <a href="transacciones.php?action=create" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm">
                <i class="fas fa-plus mr-2"></i>Nueva Transacción
            </a>
        </div>
    </div>
    
    <?php if (!empty($alertas)): ?>
        <div class="mt-4 space-y-2">
            <?php foreach ($alertas as $alerta): ?>
                <div class="p-3 rounded-md bg-<?= $alerta['tipo'] === 'warning' ? 'yellow' : ($alerta['tipo'] === 'danger' ? 'red' : 'blue'); ?>-50 border border-<?= $alerta['tipo'] === 'warning' ? 'yellow' : ($alerta['tipo'] === 'danger' ? 'red' : 'blue'); ?>-200">
                    <div class="flex items-center">
                        <i class="fas <?= $alerta['icono']; ?> text-<?= $alerta['tipo'] === 'warning' ? 'yellow' : ($alerta['tipo'] === 'danger' ? 'red' : 'blue'); ?>-600 mr-2"></i>
                        <span class="text-sm text-<?= $alerta['tipo'] === 'warning' ? 'yellow' : ($alerta['tipo'] === 'danger' ? 'red' : 'blue'); ?>-800"><?= $alerta['mensaje']; ?></span>
                        <a href="<?= $alerta['enlace']; ?>" class="ml-auto text-<?= $alerta['tipo'] === 'warning' ? 'yellow' : ($alerta['tipo'] === 'danger' ? 'red' : 'blue'); ?>-600 hover:text-<?= $alerta['tipo'] === 'warning' ? 'yellow' : ($alerta['tipo'] === 'danger' ? 'red' : 'blue'); ?>-800 text-sm font-medium">
                            Ver <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Presupuestos</p>
                <p class="text-3xl font-bold text-gray-900"><?= $resumen_presupuestos['total_presupuestos'] ?? 0; ?></p>
                <div class="flex items-center space-x-4 mt-2">
                    <span class="text-xs text-green-600"><?= $resumen_presupuestos['presupuestos_activos'] ?? 0; ?> activos</span>
                    <?php if (($resumen_presupuestos['presupuestos_por_vencer'] ?? 0) > 0): ?>
                        <span class="text-xs text-orange-600"><?= $resumen_presupuestos['presupuestos_por_vencer']; ?> por vencer</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="w-14 h-14 bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl flex items-center justify-center">
                <i class="fas fa-calculator text-white text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Ingresos del Mes</p>
                <p class="text-3xl font-bold text-green-600"><?= formatCurrency($resumen_mes['ingresos_mes'] ?? 0); ?></p>
                <div class="flex items-center mt-2">
                    <span class="text-xs text-gray-500">Planificado: <?= formatCurrency($resumen_mes['ingresos_planificados'] ?? 0); ?></span>
                    <?php if ($cambio_ingresos != 0): ?>
                        <span class="ml-2 text-xs <?= $cambio_ingresos > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <i class="fas fa-arrow-<?= $cambio_ingresos > 0 ? 'up' : 'down'; ?>"></i>
                            <?= abs(round($cambio_ingresos, 1)); ?>%
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="w-14 h-14 bg-gradient-to-r from-green-500 to-green-600 rounded-xl flex items-center justify-center">
                <i class="fas fa-arrow-up text-white text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Gastos del Mes</p>
                <p class="text-3xl font-bold text-red-600"><?= formatCurrency($resumen_mes['gastos_mes'] ?? 0); ?></p>
                <div class="flex items-center mt-2">
                    <span class="text-xs text-gray-500">Planificado: <?= formatCurrency($resumen_mes['gastos_planificados'] ?? 0); ?></span>
                    <?php if ($cambio_gastos != 0): ?>
                        <span class="ml-2 text-xs <?= $cambio_gastos > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                            <i class="fas fa-arrow-<?= $cambio_gastos > 0 ? 'up' : 'down'; ?>"></i>
                            <?= abs(round($cambio_gastos, 1)); ?>%
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="w-14 h-14 bg-gradient-to-r from-red-500 to-red-600 rounded-xl flex items-center justify-center">
                <i class="fas fa-arrow-down text-white text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Balance del Mes</p>
                <p class="text-3xl font-bold <?= $balance_mes >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                    <?= formatCurrency($balance_mes); ?>
                </p>
                <div class="flex items-center mt-2">
                    <span class="text-xs text-gray-500">Planificado: <?= formatCurrency($balance_planificado); ?></span>
                    <div class="ml-2">
                        <?php 
                        $diferencia_balance = $balance_mes - $balance_planificado;
                        if ($diferencia_balance != 0):
                        ?>
                            <span class="text-xs <?= $diferencia_balance > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?= $diferencia_balance > 0 ? '+' : ''; ?><?= formatCurrency($diferencia_balance); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="w-14 h-14 bg-gradient-to-r from-<?= $balance_mes >= 0 ? 'green' : 'red'; ?>-500 to-<?= $balance_mes >= 0 ? 'green' : 'red'; ?>-600 rounded-xl flex items-center justify-center">
                <i class="fas fa-balance-scale text-white text-xl"></i>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-gray-800">Gastos por Categoría</h3>
            <span class="text-sm text-gray-500">Últimos 30 días</span>
        </div>
        <div class="h-80 relative">
            <?php if (empty($gastos_por_categoria)): ?>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <i class="fas fa-chart-pie text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-center">No hay datos de gastos para mostrar</p>
                    <a href="transacciones.php?action=create" class="mt-2 text-blue-600 hover:text-blue-800 text-sm">
                        Agregar primera transacción
                    </a>
                </div>
            <?php else: ?>
                <canvas id="gastosCategoria"></canvas>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-gray-800">Tendencias Mensuales</h3>
            <span class="text-sm text-gray-500">Últimos 6 meses</span>
        </div>
        <div class="h-80 relative">
            <?php if (empty($tendencias)): ?>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <i class="fas fa-chart-line text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-center">No hay datos históricos suficientes</p>
                </div>
            <?php else: ?>
                <canvas id="tendenciasMensuales"></canvas>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white rounded-xl shadow-lg p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-gray-800">
                <i class="fas fa-history mr-2 text-blue-600"></i>Transacciones Recientes
            </h3>
            <a href="transacciones.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                Ver todas <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <div class="space-y-4">
            <?php if (empty($transacciones_recientes)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-receipt text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 mb-4">No hay transacciones recientes</p>
                    <a href="transacciones.php?action=create" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm">
                        <i class="fas fa-plus mr-2"></i>Agregar Transacción
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($transacciones_recientes as $transaccion): ?>
                    <div class="flex items-center justify-between p-4 border border-gray-100 rounded-lg hover:bg-gray-50">
                        <div class="flex items-center space-x-4">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center <?= $transaccion['tipo'] === 'ingreso' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                                <i class="fas fa-arrow-<?= $transaccion['tipo'] === 'ingreso' ? 'up' : 'down'; ?>"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900"><?= htmlspecialchars($transaccion['descripcion'] ?: $transaccion['item_nombre']); ?></p>
                                <p class="text-sm text-gray-500"><?= htmlspecialchars($transaccion['presupuesto_nombre']); ?> • <?= formatDate($transaccion['fecha']); ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-bold <?= $transaccion['tipo'] === 'ingreso' ? 'text-green-600' : 'text-red-600'; ?>">
                                <?= formatCurrency($transaccion['monto']); ?>
                            </p>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $transaccion['tipo'] === 'ingreso' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?= ucfirst($transaccion['tipo']); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-gray-800">
                <i class="fas fa-bullseye mr-2 text-purple-600"></i>Metas
            </h3>
            <a href="metas.php" class="text-purple-600 hover:text-purple-800 text-sm font-medium">
                Ver todas <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <p class="text-2xl font-bold text-green-600"><?= $resumen_metas['metas_completadas'] ?? 0; ?></p>
                    <p class="text-sm text-green-700">Completadas</p>
                </div>
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <p class="text-2xl font-bold text-blue-600"><?= ($resumen_metas['total_metas'] ?? 0) - ($resumen_metas['metas_completadas'] ?? 0); ?></p>
                    <p class="text-sm text-blue-700">Pendientes</p>
                </div>
            </div>
            
            <?php if (($resumen_metas['metas_vencidas'] ?? 0) > 0): ?>
                <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                        <span class="text-sm text-red-700"><?= $resumen_metas['metas_vencidas']; ?> meta(s) vencida(s)</span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (($resumen_metas['metas_proximas'] ?? 0) > 0): ?>
                <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-clock text-yellow-500 mr-2"></i>
                        <span class="text-sm text-yellow-700"><?= $resumen_metas['metas_proximas']; ?> meta(s) próxima(s) a vencer</span>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="pt-4">
                <a href="metas.php?action=create" class="w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md text-sm text-center block">
                    <i class="fas fa-plus mr-2"></i>Nueva Meta
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const gastosData = <?= json_encode($gastos_por_categoria ?? []); ?>;
    const canvasGastos = document.getElementById('gastosCategoria');
    
    if (canvasGastos && gastosData.length > 0) {
        const ctx = canvasGastos.getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: gastosData.map(item => item.categoria),
                datasets: [{
                    data: gastosData.map(item => parseFloat(item.total_gastado)),
                    backgroundColor: [
                        '#4F46E5', '#F97316', '#10B981', '#3B82F6', '#F43F5E',
                        '#8B5CF6', '#D97706', '#059669', '#2563EB', '#E11D48'
                    ],
                    borderWidth: 3,
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
                            usePointStyle: true,
                            font: { size: 12 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = new Intl.NumberFormat('es-CO', { 
                                    style: 'currency', 
                                    currency: 'COP' 
                                }).format(context.parsed);
                                return `${label}: ${value}`;
                            }
                        }
                    }
                }
            }
        });
    }

    const tendenciasData = <?= json_encode($tendencias ?? []); ?>;
    const canvasTendencias = document.getElementById('tendenciasMensuales');
    
    if (canvasTendencias && tendenciasData.length > 0) {
        const ctx = canvasTendencias.getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: tendenciasData.map(item => item.mes_nombre),
                datasets: [{
                    label: 'Ingresos',
                    data: tendenciasData.map(item => parseFloat(item.ingresos || 0)),
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Gastos',
                    data: tendenciasData.map(item => parseFloat(item.gastos || 0)),
                    borderColor: '#F43F5E',
                    backgroundColor: 'rgba(244, 63, 94, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = new Intl.NumberFormat('es-CO', { 
                                    style: 'currency', 
                                    currency: 'COP' 
                                }).format(context.parsed.y);
                                return `${label}: ${value}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('es-CO', { 
                                    style: 'currency', 
                                    currency: 'COP',
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0
                                }).format(value);
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php 
require_once __DIR__ . '/includes/footer.php';
?>