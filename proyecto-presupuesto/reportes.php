<?php
// Archivo: proyecto-presupuesto/reportes.php
$page_title = 'Reportes y Análisis';
require_once __DIR__ . '/includes/header.php';

$user_id = $_SESSION['user_id'];
$tipo_reporte = $_GET['tipo'] ?? 'resumen';
$periodo = $_GET['periodo'] ?? 'mes_actual';

// Definir rangos de fechas según el período seleccionado
$fecha_inicio = '';
$fecha_fin = '';

switch ($periodo) {
    case 'mes_actual':
        $fecha_inicio = date('Y-m-01');
        $fecha_fin = date('Y-m-t');
        break;
    case 'mes_anterior':
        $fecha_inicio = date('Y-m-01', strtotime('-1 month'));
        $fecha_fin = date('Y-m-t', strtotime('-1 month'));
        break;
    case 'trimestre_actual':
        $mes_actual = date('n');
        $trimestre = ceil($mes_actual / 3);
        $fecha_inicio = date('Y-' . str_pad(($trimestre - 1) * 3 + 1, 2, '0', STR_PAD_LEFT) . '-01');
        $fecha_fin = date('Y-m-t', strtotime($fecha_inicio . ' +2 months'));
        break;
    case 'ano_actual':
        $fecha_inicio = date('Y-01-01');
        $fecha_fin = date('Y-12-31');
        break;
    case 'ultimos_6_meses':
        $fecha_inicio = date('Y-m-01', strtotime('-6 months'));
        $fecha_fin = date('Y-m-t');
        break;
    case 'personalizado':
        $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
        $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
        break;
}

// Obtener datos según el tipo de reporte
$datos_reporte = [];

// Resumen General
if ($tipo_reporte === 'resumen') {
    // Resumen financiero
    $sql_resumen = "SELECT 
        SUM(CASE WHEN pi.tipo = 'ingreso' THEN t.monto ELSE 0 END) as total_ingresos,
        SUM(CASE WHEN pi.tipo = 'egreso' THEN t.monto ELSE 0 END) as total_egresos,
        COUNT(DISTINCT p.id) as total_presupuestos,
        COUNT(t.id) as total_transacciones
    FROM transacciones t
    JOIN presupuesto_items pi ON t.presupuesto_item_id = pi.id
    JOIN presupuestos p ON pi.presupuesto_id = p.id
    WHERE p.usuario_id = ? AND t.fecha BETWEEN ? AND ?";
    
    $resumen = $db->fetchOne($sql_resumen, [$user_id, $fecha_inicio, $fecha_fin]);
    
    // Ingresos por categoría
    $sql_ingresos_categoria = "SELECT 
        cp.nombre as categoria,
        SUM(t.monto) as total
    FROM transacciones t
    JOIN presupuesto_items pi ON t.presupuesto_item_id = pi.id
    JOIN presupuestos p ON pi.presupuesto_id = p.id
    JOIN subcategorias sc ON pi.subcategoria_id = sc.id
    JOIN categorias_padre cp ON sc.categoria_padre_id = cp.id
    WHERE p.usuario_id = ? AND t.fecha BETWEEN ? AND ? AND t.tipo = 'ingreso'
    GROUP BY cp.id, cp.nombre
    ORDER BY total DESC";
    
    $ingresos_categoria = $db->fetchAll($sql_ingresos_categoria, [$user_id, $fecha_inicio, $fecha_fin]);
    
    // Gastos por categoría
    $sql_gastos_categoria = "SELECT 
        cp.nombre as categoria,
        SUM(t.monto) as total
    FROM transacciones t
    JOIN presupuesto_items pi ON t.presupuesto_item_id = pi.id
    JOIN presupuestos p ON pi.presupuesto_id = p.id
    JOIN subcategorias sc ON pi.subcategoria_id = sc.id
    JOIN categorias_padre cp ON sc.categoria_padre_id = cp.id
    WHERE p.usuario_id = ? AND t.fecha BETWEEN ? AND ? AND t.tipo = 'egreso'
    GROUP BY cp.id, cp.nombre
    ORDER BY total DESC";
    
    $gastos_categoria = $db->fetchAll($sql_gastos_categoria, [$user_id, $fecha_inicio, $fecha_fin]);
    
    // Evolución mensual
    $sql_evolucion = "SELECT 
        DATE_FORMAT(t.fecha, '%Y-%m') as mes,
        SUM(CASE WHEN t.tipo = 'ingreso' THEN t.monto ELSE 0 END) as ingresos,
        SUM(CASE WHEN t.tipo = 'egreso' THEN t.monto ELSE 0 END) as egresos
    FROM transacciones t
    JOIN presupuesto_items pi ON t.presupuesto_item_id = pi.id
    JOIN presupuestos p ON pi.presupuesto_id = p.id
    WHERE p.usuario_id = ? AND t.fecha BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(t.fecha, '%Y-%m')
    ORDER BY mes ASC";
    
    $evolucion_mensual = $db->fetchAll($sql_evolucion, [$user_id, $fecha_inicio, $fecha_fin]);
    
    $datos_reporte = [
        'resumen' => $resumen,
        'ingresos_categoria' => $ingresos_categoria,
        'gastos_categoria' => $gastos_categoria,
        'evolucion_mensual' => $evolucion_mensual
    ];
}

// Reporte de Presupuestos
elseif ($tipo_reporte === 'presupuestos') {
    // Desempeño de presupuestos
    $sql_presupuestos = "SELECT 
        p.nombre,
        p.fecha_inicio,
        p.fecha_fin,
        SUM(CASE WHEN pi.tipo = 'ingreso' THEN pi.monto_planificado ELSE 0 END) as ingresos_planificados,
        SUM(CASE WHEN pi.tipo = 'egreso' THEN pi.monto_planificado ELSE 0 END) as egresos_planificados,
        SUM(CASE WHEN pi.tipo = 'ingreso' THEN pi.monto_real ELSE 0 END) as ingresos_reales,
        SUM(CASE WHEN pi.tipo = 'egreso' THEN pi.monto_real ELSE 0 END) as egresos_reales
    FROM presupuestos p
    LEFT JOIN presupuesto_items pi ON p.id = pi.presupuesto_id
    WHERE p.usuario_id = ? AND p.activo = 1
    AND (p.fecha_inicio BETWEEN ? AND ? OR p.fecha_fin BETWEEN ? AND ?)
    GROUP BY p.id, p.nombre, p.fecha_inicio, p.fecha_fin
    ORDER BY p.fecha_inicio DESC";
    
    $presupuestos = $db->fetchAll($sql_presupuestos, [$user_id, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin]);
    
    $datos_reporte = [
        'presupuestos' => $presupuestos
    ];
}

// Reporte de Metas
elseif ($tipo_reporte === 'metas') {
    // Progreso de metas
    $sql_metas = "SELECT 
        m.nombre,
        m.monto_objetivo,
        m.monto_actual,
        m.fecha_objetivo,
        m.completado,
        cp.nombre as categoria_nombre,
        ROUND((m.monto_actual / m.monto_objetivo) * 100, 2) as porcentaje_progreso,
        DATEDIFF(m.fecha_objetivo, CURDATE()) as dias_restantes
    FROM metas m
    LEFT JOIN categorias_padre cp ON m.categoria_id = cp.id
    WHERE m.usuario_id = ? AND m.activo = 1
    ORDER BY m.fecha_objetivo ASC";
    
    $metas = $db->fetchAll($sql_metas, [$user_id]);
    
    // Progreso por categoría
    $sql_metas_categoria = "SELECT 
        cp.nombre as categoria,
        COUNT(m.id) as total_metas,
        SUM(CASE WHEN m.completado = 1 THEN 1 ELSE 0 END) as metas_completadas,
        SUM(m.monto_objetivo) as monto_objetivo_total,
        SUM(m.monto_actual) as monto_actual_total
    FROM metas m
    LEFT JOIN categorias_padre cp ON m.categoria_id = cp.id
    WHERE m.usuario_id = ? AND m.activo = 1
    GROUP BY cp.id, cp.nombre
    ORDER BY monto_objetivo_total DESC";
    
    $metas_categoria = $db->fetchAll($sql_metas_categoria, [$user_id]);
    
    $datos_reporte = [
        'metas' => $metas,
        'metas_categoria' => $metas_categoria
    ];
}

// Análisis Detallado
elseif ($tipo_reporte === 'analisis') {
    // Análisis de tendencias
    $sql_tendencias = "SELECT 
        DATE_FORMAT(t.fecha, '%Y-%m') as mes,
        cp.nombre as categoria,
        cp.tipo,
        SUM(t.monto) as total
    FROM transacciones t
    JOIN presupuesto_items pi ON t.presupuesto_item_id = pi.id
    JOIN presupuestos p ON pi.presupuesto_id = p.id
    JOIN subcategorias sc ON pi.subcategoria_id = sc.id
    JOIN categorias_padre cp ON sc.categoria_padre_id = cp.id
    WHERE p.usuario_id = ? AND t.fecha BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(t.fecha, '%Y-%m'), cp.id, cp.nombre, cp.tipo
    ORDER BY mes ASC, total DESC";
    
    $tendencias = $db->fetchAll($sql_tendencias, [$user_id, $fecha_inicio, $fecha_fin]);
    
    // Top gastos
    $sql_top_gastos = "SELECT 
        sc.nombre as subcategoria,
        cp.nombre as categoria,
        SUM(t.monto) as total,
        COUNT(t.id) as frecuencia
    FROM transacciones t
    JOIN presupuesto_items pi ON t.presupuesto_item_id = pi.id
    JOIN presupuestos p ON pi.presupuesto_id = p.id
    JOIN subcategorias sc ON pi.subcategoria_id = sc.id
    JOIN categorias_padre cp ON sc.categoria_padre_id = cp.id
    WHERE p.usuario_id = ? AND t.fecha BETWEEN ? AND ? AND t.tipo = 'egreso'
    GROUP BY sc.id, sc.nombre, cp.nombre
    ORDER BY total DESC
    LIMIT 10";
    
    $top_gastos = $db->fetchAll($sql_top_gastos, [$user_id, $fecha_inicio, $fecha_fin]);
    
    $datos_reporte = [
        'tendencias' => $tendencias,
        'top_gastos' => $top_gastos
    ];
}

// Función para generar colores para gráficos
function generarColores($cantidad) {
    $colores = [
        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
        '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384'
    ];
    
    $resultado = [];
    for ($i = 0; $i < $cantidad; $i++) {
        $resultado[] = $colores[$i % count($colores)];
    }
    
    return $resultado;
}
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 mb-4">Reportes y Análisis</h1>
    
    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label for="tipo" class="block text-sm font-medium text-gray-700 mb-2">Tipo de Reporte</label>
                <select id="tipo" name="tipo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="resumen" <?php echo $tipo_reporte === 'resumen' ? 'selected' : ''; ?>>Resumen General</option>
                    <option value="presupuestos" <?php echo $tipo_reporte === 'presupuestos' ? 'selected' : ''; ?>>Presupuestos</option>
                    <option value="metas" <?php echo $tipo_reporte === 'metas' ? 'selected' : ''; ?>>Metas</option>
                    <option value="analisis" <?php echo $tipo_reporte === 'analisis' ? 'selected' : ''; ?>>Análisis Detallado</option>
                </select>
            </div>
            
            <div>
                <label for="periodo" class="block text-sm font-medium text-gray-700 mb-2">Período</label>
                <select id="periodo" name="periodo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="toggleCustomDates()">
                    <option value="mes_actual" <?php echo $periodo === 'mes_actual' ? 'selected' : ''; ?>>Mes Actual</option>
                    <option value="mes_anterior" <?php echo $periodo === 'mes_anterior' ? 'selected' : ''; ?>>Mes Anterior</option>
                    <option value="trimestre_actual" <?php echo $periodo === 'trimestre_actual' ? 'selected' : ''; ?>>Trimestre Actual</option>
                    <option value="ano_actual" <?php echo $periodo === 'ano_actual' ? 'selected' : ''; ?>>Año Actual</option>
                    <option value="ultimos_6_meses" <?php echo $periodo === 'ultimos_6_meses' ? 'selected' : ''; ?>>Últimos 6 Meses</option>
                    <option value="personalizado" <?php echo $periodo === 'personalizado' ? 'selected' : ''; ?>>Personalizado</option>
                </select>
            </div>
            
            <div id="custom-dates" style="display: <?php echo $periodo === 'personalizado' ? 'block' : 'none'; ?>;">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="fecha_inicio" class="block text-sm font-medium text-gray-700 mb-2">Desde</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="fecha_fin" class="block text-sm font-medium text-gray-700 mb-2">Hasta</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo $fecha_fin; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>
            
            <div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                    <i class="fas fa-search mr-2"></i>Generar Reporte
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Contenido del reporte -->
<?php if ($tipo_reporte === 'resumen'): ?>
    <!-- Resumen General -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Ingresos</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo formatCurrency($datos_reporte['resumen']['total_ingresos'] ?? 0); ?></p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-arrow-up text-green-600"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Egresos</p>
                    <p class="text-2xl font-bold text-red-600"><?php echo formatCurrency($datos_reporte['resumen']['total_egresos'] ?? 0); ?></p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-arrow-down text-red-600"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Balance</p>
                    <?php 
                    $balance = ($datos_reporte['resumen']['total_ingresos'] ?? 0) - ($datos_reporte['resumen']['total_egresos'] ?? 0);
                    ?>
                    <p class="text-2xl font-bold <?php echo $balance >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo formatCurrency($balance); ?>
                    </p>
                </div>
                <div class="w-12 h-12 <?php echo $balance >= 0 ? 'bg-green-100' : 'bg-red-100'; ?> rounded-full flex items-center justify-center">
                    <i class="fas fa-balance-scale <?php echo $balance >= 0 ? 'text-green-600' : 'text-red-600'; ?>"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Transacciones</p>
                    <p class="text-2xl font-bold text-blue-600"><?php echo $datos_reporte['resumen']['total_transacciones'] ?? 0; ?></p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-exchange-alt text-blue-600"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gráficos -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Ingresos por Categoría -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Ingresos por Categoría</h3>
            <div class="h-80">
                <canvas id="ingresosCategoria"></canvas>
            </div>
        </div>
        
        <!-- Gastos por Categoría -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Gastos por Categoría</h3>
            <div class="h-80">
                <canvas id="gastosCategoria"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Evolución Mensual -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Evolución Mensual</h3>
        <div class="h-96">
            <canvas id="evolucionMensual"></canvas>
        </div>
    </div>

<?php elseif ($tipo_reporte === 'presupuestos'): ?>
    <!-- Reporte de Presupuestos -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 bg-gray-50">
            <h3 class="text-lg font-medium text-gray-900">Desempeño de Presupuestos</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Presupuesto</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Período</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ingresos</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gastos</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cumplimiento</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($datos_reporte['presupuestos'])): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">No hay presupuestos en el período seleccionado</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($datos_reporte['presupuestos'] as $presupuesto): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($presupuesto['nombre']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo formatDate($presupuesto['fecha_inicio']) . ' - ' . formatDate($presupuesto['fecha_fin']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div>Plan: <?php echo formatCurrency($presupuesto['ingresos_planificados']); ?></div>
                                    <div class="text-green-600">Real: <?php echo formatCurrency($presupuesto['ingresos_reales']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div>Plan: <?php echo formatCurrency($presupuesto['egresos_planificados']); ?></div>
                                    <div class="text-red-600">Real: <?php echo formatCurrency($presupuesto['egresos_reales']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php
                                    $balance_planificado = $presupuesto['ingresos_planificados'] - $presupuesto['egresos_planificados'];
                                    $balance_real = $presupuesto['ingresos_reales'] - $presupuesto['egresos_reales'];
                                    $cumplimiento = $balance_planificado != 0 ? ($balance_real / $balance_planificado) * 100 : 0;
                                    ?>
                                    <div class="text-center">
                                        <div class="text-lg font-semibold <?php echo $cumplimiento >= 100 ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo round($cumplimiento, 1); ?>%
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo min(100, abs($cumplimiento)); ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($tipo_reporte === 'metas'): ?>
    <!-- Reporte de Metas -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Progreso de Metas -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Progreso de Metas</h3>
            <div class="space-y-4 max-h-96 overflow-y-auto">
                <?php if (empty($datos_reporte['metas'])): ?>
                    <p class="text-gray-500 text-center py-8">No hay metas registradas</p>
                <?php else: ?>
                    <?php foreach ($datos_reporte['metas'] as $meta): ?>
                        <div class="border rounded-lg p-4">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($meta['nombre']); ?></h4>
                                <span class="text-sm text-gray-500">
                                    <?php echo $meta['dias_restantes'] > 0 ? $meta['dias_restantes'] . ' días' : 'Vencida'; ?>
                                </span>
                            </div>
                            <div class="mb-2">
                                <div class="flex justify-between text-sm text-gray-600">
                                    <span><?php echo formatCurrency($meta['monto_actual']); ?></span>
                                    <span><?php echo formatCurrency($meta['monto_objetivo']); ?></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="<?php echo $meta['completado'] ? 'bg-green-600' : 'bg-blue-600'; ?> h-2 rounded-full" style="width: <?php echo min(100, $meta['porcentaje_progreso']); ?>%"></div>
                                </div>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?php echo $meta['porcentaje_progreso']; ?>% completado
                                <?php if ($meta['categoria_nombre']): ?>
                                    - <?php echo htmlspecialchars($meta['categoria_nombre']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Metas por Categoría -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Metas por Categoría</h3>
            <div class="h-80">
                <canvas id="metasCategoria"></canvas>
            </div>
        </div>
    </div>

<?php elseif ($tipo_reporte === 'analisis'): ?>
    <!-- Análisis Detallado -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Top Gastos -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Top 10 Gastos</h3>
            <div class="space-y-3">
                <?php if (empty($datos_reporte['top_gastos'])): ?>
                    <p class="text-gray-500 text-center py-8">No hay datos de gastos</p>
                <?php else: ?>
                    <?php foreach ($datos_reporte['top_gastos'] as $index => $gasto): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center text-sm font-medium text-red-600">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($gasto['subcategoria']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($gasto['categoria']); ?></div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium text-gray-900"><?php echo formatCurrency($gasto['total']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo $gasto['frecuencia']; ?> transacciones</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tendencias -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Tendencias por Categoría</h3>
            <div class="h-80">
                <canvas id="tendenciasCategoria"></canvas>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function toggleCustomDates() {
    const periodo = document.getElementById('periodo').value;
    const customDates = document.getElementById('custom-dates');
    
    if (periodo === 'personalizado') {
        customDates.style.display = 'block';
    } else {
        customDates.style.display = 'none';
    }
}

// Configuración de gráficos
<?php if ($tipo_reporte === 'resumen'): ?>
    // Gráfico de Ingresos por Categoría
    const ingresosData = <?php echo json_encode($datos_reporte['ingresos_categoria']); ?>;
    if (ingresosData.length > 0) {
        const ctxIngresos = document.getElementById('ingresosCategoria').getContext('2d');
        new Chart(ctxIngresos, {
            type: 'doughnut',
            data: {
                labels: ingresosData.map(item => item.categoria),
                datasets: [{
                    data: ingresosData.map(item => item.total),
                    backgroundColor: <?php echo json_encode(generarColores(count($datos_reporte['ingresos_categoria']))); ?>,
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
                                return context.label + ': ' + formatCurrency(context.parsed);
                            }
                        }
                    }
                }
            }
        });
    }

    // Gráfico de Gastos por Categoría
    const gastosData = <?php echo json_encode($datos_reporte['gastos_categoria']); ?>;
    if (gastosData.length > 0) {
        const ctxGastos = document.getElementById('gastosCategoria').getContext('2d');
        new Chart(ctxGastos, {
            type: 'doughnut',
            data: {
                labels: gastosData.map(item => item.categoria),
                datasets: [{
                    data: gastosData.map(item => item.total),
                    backgroundColor: <?php echo json_encode(generarColores(count($datos_reporte['gastos_categoria']))); ?>,
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
                                return context.label + ': ' + formatCurrency(context.parsed);
                            }
                        }
                    }
                }
            }
        });
    }

    // Gráfico de Evolución Mensual
    const evolucionData = <?php echo json_encode($datos_reporte['evolucion_mensual']); ?>;
    if (evolucionData.length > 0) {
        const ctxEvolucion = document.getElementById('evolucionMensual').getContext('2d');
        new Chart(ctxEvolucion, {
            type: 'line',
            data: {
                labels: evolucionData.map(item => item.mes),
                datasets: [{
                    label: 'Ingresos',
                    data: evolucionData.map(item => item.ingresos),
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true
                }, {
                    label: 'Egresos',
                    data: evolucionData.map(item => item.egresos),
                    borderColor: '#EF4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + formatCurrency(context.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return formatCurrency(value);
                            }
                        }
                    }
                }
            }
        });
    }

<?php elseif ($tipo_reporte === 'metas'): ?>
    // Gráfico de Metas por Categoría
    const metasData = <?php echo json_encode($datos_reporte['metas_categoria']); ?>;
    if (metasData.length > 0) {
        const ctxMetas = document.getElementById('metasCategoria').getContext('2d');
        new Chart(ctxMetas, {
            type: 'bar',
            data: {
                labels: metasData.map(item => item.categoria || 'Sin categoría'),
                datasets: [{
                    label: 'Metas Completadas',
                    data: metasData.map(item => item.metas_completadas),
                    backgroundColor: '#10B981'
                }, {
                    label: 'Metas Pendientes',
                    data: metasData.map(item => item.total_metas - item.metas_completadas),
                    backgroundColor: '#EF4444'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true
                    }
                }
            }
        });
    }
<?php endif; ?>
</script>
<?php

require_once __DIR__ . '/includes/footer.php';

?>