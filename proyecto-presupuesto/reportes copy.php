<?php

use Dompdf\Dompdf;
use Dompdf\Options;


if (isset($_POST['action']) && $_POST['action'] === 'print') {
    
    require_once __DIR__ . '../../vendor/autoload.php';

    // Obtener los datos de los filtros del POST para mantener la consistencia
    $tipo_reporte = $_POST['tipo'] ?? 'resumen';
    $periodo = $_POST['periodo'] ?? 'mes_actual';
    $fecha_inicio_filtro = $_POST['fecha_inicio'] ?? '';
    $fecha_fin_filtro = $_POST['fecha_fin'] ?? '';

    // Iniciar el buffer de salida para capturar el HTML del reporte
    ob_start();

    // Incluir un archivo de estilos específico para el PDF
    // Esto nos da control total sobre la apariencia del documento impreso
    require_once __DIR__ . '/reporte_pdf_styles.php';

    // Re-incluir la parte superior de la página (sin el menú de navegación)
    // Asumimos que tienes un header_pdf.php o similar para un encabezado limpio
    // Si no, puedes simplemente poner el título aquí.
    echo "<h1>Reporte de " . htmlspecialchars(ucfirst($tipo_reporte)) . "</h1>";
    echo "<p>Periodo: " . htmlspecialchars(str_replace('_', ' ', ucfirst($periodo))) . "</p>";
    if ($periodo === 'personalizado') {
        echo "<p>Desde: " . htmlspecialchars($fecha_inicio_filtro) . " Hasta: " . htmlspecialchars($fecha_fin_filtro) . "</p>";
    }
    echo "<hr>";


    // Renderizar el contenido del reporte (el HTML que está más abajo)
    // Pasamos las variables necesarias para que se genere el contenido correcto
    // Esto es una simulación, el contenido real se captura después.
    // La idea es que el HTML del reporte se genere aquí.
    // Por simplicidad, capturaremos el HTML generado por la página y lo limpiaremos.

    // El contenido del reporte se genera más abajo y se captura con ob_get_clean()
}
// --- FIN: Lógica para generar PDF ---


$page_title = 'Reportes y Análisis';
require_once __DIR__ . '/includes/header.php';

// Si la acción no es imprimir, usa los parámetros GET como antes
if (!isset($_POST['action']) || $_POST['action'] !== 'print') {
    $user_id = $_SESSION['user_id'];
    $tipo_reporte = $_GET['tipo'] ?? 'resumen';
    $periodo = $_GET['periodo'] ?? 'mes_actual';
} else {
    // Si es para imprimir, usa los datos que ya obtuvimos del POST
    $user_id = $_SESSION['user_id'];
}


// Definir rangos de fechas según el período seleccionado
$fecha_inicio = '';
$fecha_fin = '';

// Usar las fechas del filtro si vienen del POST (para el PDF) o del GET
$fecha_inicio_source = $_POST['fecha_inicio'] ?? $_GET['fecha_inicio'] ?? null;
$fecha_fin_source = $_POST['fecha_fin'] ?? $_GET['fecha_fin'] ?? null;


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
        $fecha_inicio = $fecha_inicio_source ?? date('Y-m-01');
        $fecha_fin = $fecha_fin_source ?? date('Y-m-t');
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
    WHERE p.usuario_id = ? AND t.fecha BETWEEN ? AND ? AND pi.tipo = 'ingreso'
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
    WHERE p.usuario_id = ? AND t.fecha BETWEEN ? AND ? AND pi.tipo = 'egreso'
    GROUP BY cp.id, cp.nombre
    ORDER BY total DESC";
    
    $gastos_categoria = $db->fetchAll($sql_gastos_categoria, [$user_id, $fecha_inicio, $fecha_fin]);
    
    // Evolución mensual
    $sql_evolucion = "SELECT 
        DATE_FORMAT(t.fecha, '%Y-%m') as mes,
        SUM(CASE WHEN pi.tipo = 'ingreso' THEN t.monto ELSE 0 END) as ingresos,
        SUM(CASE WHEN pi.tipo = 'egreso' THEN t.monto ELSE 0 END) as egresos
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
    $sql_presupuestos = "SELECT 
        p.nombre, p.fecha_inicio, p.fecha_fin,
        SUM(CASE WHEN pi.tipo = 'ingreso' THEN pi.monto_planificado ELSE 0 END) as ingresos_planificados,
        SUM(CASE WHEN pi.tipo = 'egreso' THEN pi.monto_planificado ELSE 0 END) as egresos_planificados,
        SUM(CASE WHEN pi.tipo = 'ingreso' THEN pi.monto_real ELSE 0 END) as ingresos_reales,
        SUM(CASE WHEN pi.tipo = 'egreso' THEN pi.monto_real ELSE 0 END) as egresos_reales
    FROM presupuestos p
    LEFT JOIN presupuesto_items pi ON p.id = pi.presupuesto_id
    WHERE p.usuario_id = ? AND p.activo = 1 AND (p.fecha_inicio BETWEEN ? AND ? OR p.fecha_fin BETWEEN ? AND ?)
    GROUP BY p.id, p.nombre, p.fecha_inicio, p.fecha_fin
    ORDER BY p.fecha_inicio DESC";
    
    $presupuestos = $db->fetchAll($sql_presupuestos, [$user_id, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin]);
    
    $datos_reporte = ['presupuestos' => $presupuestos];
}

// Reporte de Metas
elseif ($tipo_reporte === 'metas') {
    $sql_metas = "SELECT 
        m.nombre, m.monto_objetivo, m.monto_actual, m.fecha_objetivo, m.completado,
        cp.nombre as categoria_nombre,
        ROUND((m.monto_actual / m.monto_objetivo) * 100, 2) as porcentaje_progreso,
        DATEDIFF(m.fecha_objetivo, CURDATE()) as dias_restantes
    FROM metas m
    LEFT JOIN categorias_padre cp ON m.categoria_id = cp.id
    WHERE m.usuario_id = ? AND m.activo = 1
    ORDER BY m.fecha_objetivo ASC";
    
    $metas = $db->fetchAll($sql_metas, [$user_id]);
    
    $sql_metas_categoria = "SELECT 
        cp.nombre as categoria, COUNT(m.id) as total_metas,
        SUM(CASE WHEN m.completado = 1 THEN 1 ELSE 0 END) as metas_completadas,
        SUM(m.monto_objetivo) as monto_objetivo_total, SUM(m.monto_actual) as monto_actual_total
    FROM metas m
    LEFT JOIN categorias_padre cp ON m.categoria_id = cp.id
    WHERE m.usuario_id = ? AND m.activo = 1
    GROUP BY cp.id, cp.nombre
    ORDER BY monto_objetivo_total DESC";
    
    $metas_categoria = $db->fetchAll($sql_metas_categoria, [$user_id]);
    
    $datos_reporte = ['metas' => $metas, 'metas_categoria' => $metas_categoria];
}

// Análisis Detallado
elseif ($tipo_reporte === 'analisis') {
    $sql_tendencias = "SELECT 
        DATE_FORMAT(t.fecha, '%Y-%m') as mes, cp.nombre as categoria, cp.tipo, SUM(t.monto) as total
    FROM transacciones t
    JOIN presupuesto_items pi ON t.presupuesto_item_id = pi.id
    JOIN presupuestos p ON pi.presupuesto_id = p.id
    JOIN subcategorias sc ON pi.subcategoria_id = sc.id
    JOIN categorias_padre cp ON sc.categoria_padre_id = cp.id
    WHERE p.usuario_id = ? AND t.fecha BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(t.fecha, '%Y-%m'), cp.id, cp.nombre, cp.tipo
    ORDER BY mes ASC, total DESC";
    
    $tendencias = $db->fetchAll($sql_tendencias, [$user_id, $fecha_inicio, $fecha_fin]);
    
    $sql_top_gastos = "SELECT 
        sc.nombre as subcategoria, cp.nombre as categoria, SUM(t.monto) as total, COUNT(t.id) as frecuencia
    FROM transacciones t
    JOIN presupuesto_items pi ON t.presupuesto_item_id = pi.id
    JOIN presupuestos p ON pi.presupuesto_id = p.id
    JOIN subcategorias sc ON pi.subcategoria_id = sc.id
    JOIN categorias_padre cp ON sc.categoria_padre_id = cp.id
    WHERE p.usuario_id = ? AND t.fecha BETWEEN ? AND ? AND pi.tipo = 'egreso'
    GROUP BY sc.id, sc.nombre, cp.nombre
    ORDER BY total DESC LIMIT 10";
    
    $top_gastos = $db->fetchAll($sql_top_gastos, [$user_id, $fecha_inicio, $fecha_fin]);
    
    $datos_reporte = ['tendencias' => $tendencias, 'top_gastos' => $top_gastos];
}

function generarColores($cantidad) {
    $colores = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#C9CBCF'];
    $resultado = [];
    for ($i = 0; $i < $cantidad; $i++) { $resultado[] = $colores[$i % count($colores)]; }
    return $resultado;
}
?>

<div class="mb-6" id="report-filters">
    <h1 class="text-2xl font-bold text-gray-900 mb-4">Reportes y Análisis</h1>
    
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <!-- El formulario ahora usará POST para enviar los datos de los gráficos -->
        <form method="POST" id="reportForm" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <!-- Campos ocultos para enviar imágenes de gráficos como Base64 -->
            <input type="hidden" name="ingresosCategoriaChart" id="ingresosCategoriaChart">
            <input type="hidden" name="gastosCategoriaChart" id="gastosCategoriaChart">
            <input type="hidden" name="evolucionMensualChart" id="evolucionMensualChart">
            <input type="hidden" name="metasCategoriaChart" id="metasCategoriaChart">
            <input type="hidden" name="tendenciasCategoriaChart" id="tendenciasCategoriaChart">

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
            
            <div id="custom-dates" style="display: <?php echo $periodo === 'personalizado' ? 'grid' : 'none'; ?>;" class="col-span-1 md:col-span-2">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="fecha_inicio" class="block text-sm font-medium text-gray-700 mb-2">Desde</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="fecha_fin" class="block text-sm font-medium text-gray-700 mb-2">Hasta</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-2">
                <button type="button" onclick="generateReport()" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md flex items-center justify-center">
                    <i class="fas fa-search mr-2"></i>Generar
                </button>
                <button type="button" onclick="printReport()" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md flex items-center justify-center">
                    <i class="fas fa-print mr-2"></i>Imprimir
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Contenedor para el contenido del reporte -->
<div id="report-content">
    <?php if ($tipo_reporte === 'resumen'): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <p class="text-sm font-medium text-gray-600">Total Ingresos</p>
                <p class="text-2xl font-bold text-green-600"><?php echo formatCurrency($datos_reporte['resumen']['total_ingresos'] ?? 0); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <p class="text-sm font-medium text-gray-600">Total Egresos</p>
                <p class="text-2xl font-bold text-red-600"><?php echo formatCurrency($datos_reporte['resumen']['total_egresos'] ?? 0); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <p class="text-sm font-medium text-gray-600">Balance</p>
                <?php $balance = ($datos_reporte['resumen']['total_ingresos'] ?? 0) - ($datos_reporte['resumen']['total_egresos'] ?? 0); ?>
                <p class="text-2xl font-bold <?php echo $balance >= 0 ? 'text-green-600' : 'text-red-600'; ?>"><?php echo formatCurrency($balance); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <p class="text-sm font-medium text-gray-600">Transacciones</p>
                <p class="text-2xl font-bold text-blue-600"><?php echo $datos_reporte['resumen']['total_transacciones'] ?? 0; ?></p>
            </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Ingresos por Categoría</h3>
                <div class="h-80"><canvas id="ingresosCategoria"></canvas></div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Gastos por Categoría</h3>
                <div class="h-80"><canvas id="gastosCategoria"></canvas></div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Evolución Mensual</h3>
            <div class="h-96"><canvas id="evolucionMensual"></canvas></div>
        </div>

    <?php elseif ($tipo_reporte === 'presupuestos'): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4"><h3 class="text-lg font-medium text-gray-900">Desempeño de Presupuestos</h3></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Presupuesto</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Período</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ingresos</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gastos</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cumplimiento</th></tr></thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($datos_reporte['presupuestos'])): ?>
                            <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No hay presupuestos en el período seleccionado</td></tr>
                        <?php else: foreach ($datos_reporte['presupuestos'] as $p): ?>
                            <tr>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($p['nombre']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo formatDate($p['fecha_inicio']) . ' - ' . formatDate($p['fecha_fin']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><div>Plan: <?php echo formatCurrency($p['ingresos_planificados']); ?></div><div class="text-green-600">Real: <?php echo formatCurrency($p['ingresos_reales']); ?></div></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><div>Plan: <?php echo formatCurrency($p['egresos_planificados']); ?></div><div class="text-red-600">Real: <?php echo formatCurrency($p['egresos_reales']); ?></div></td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?php $balance_plan = $p['ingresos_planificados'] - $p['egresos_planificados'];
                                    $balance_real = $p['ingresos_reales'] - $p['egresos_reales'];
                                    $cump = $balance_plan != 0 ? ($balance_real / $balance_plan) * 100 : 0; ?>
                                    <div class="text-lg font-semibold <?php echo $cump >= 100 ? 'text-green-600' : 'text-red-600'; ?>"><?php echo round($cump, 1); ?>%</div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tipo_reporte === 'metas'): ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Progreso de Metas</h3>
                <div class="space-y-4 max-h-96 overflow-y-auto">
                    <?php if (empty($datos_reporte['metas'])): ?>
                        <p class="text-gray-500 text-center py-8">No hay metas registradas</p>
                    <?php else: foreach ($datos_reporte['metas'] as $meta): ?>
                        <div class="border rounded-lg p-4">
                            <div class="flex justify-between"><h4 class="font-medium"><?php echo htmlspecialchars($meta['nombre']); ?></h4><span class="text-sm text-gray-500"><?php echo $meta['dias_restantes'] > 0 ? $meta['dias_restantes'] . ' días' : 'Vencida'; ?></span></div>
                            <div class="w-full bg-gray-200 rounded-full h-2 my-2"><div class="<?php echo $meta['completado'] ? 'bg-green-600' : 'bg-blue-600'; ?> h-2 rounded-full" style="width: <?php echo min(100, $meta['porcentaje_progreso']); ?>%"></div></div>
                            <div class="text-xs text-gray-500"><?php echo $meta['porcentaje_progreso']; ?>% completado</div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Metas por Categoría</h3>
                <div class="h-80"><canvas id="metasCategoria"></canvas></div>
            </div>
        </div>

    <?php elseif ($tipo_reporte === 'analisis'): ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Top 10 Gastos</h3>
                <div class="space-y-3">
                    <?php if (empty($datos_reporte['top_gastos'])): ?>
                        <p class="text-gray-500 text-center py-8">No hay datos de gastos</p>
                    <?php else: foreach ($datos_reporte['top_gastos'] as $gasto): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div><div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($gasto['subcategoria']); ?></div><div class="text-xs text-gray-500"><?php echo htmlspecialchars($gasto['categoria']); ?></div></div>
                            <div class="text-right"><div class="text-sm font-medium text-gray-900"><?php echo formatCurrency($gasto['total']); ?></div></div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Tendencias por Categoría</h3>
                <div class="h-80"><canvas id="tendenciasCategoria"></canvas></div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Almacenar instancias de gráficos para poder destruirlas y volver a crearlas
window.myCharts = {};

function destroyCharts() {
    Object.values(window.myCharts).forEach(chart => chart.destroy());
    window.myCharts = {};
}

function renderCharts() {
    destroyCharts(); // Limpiar gráficos anteriores

    <?php if ($tipo_reporte === 'resumen'): ?>
        const ingresosData = <?php echo json_encode($datos_reporte['ingresos_categoria']); ?>;
        if (ingresosData.length > 0) {
            const ctx = document.getElementById('ingresosCategoria').getContext('2d');
            window.myCharts.ingresos = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ingresosData.map(i => i.categoria),
                    datasets: [{ data: ingresosData.map(i => i.total), backgroundColor: <?php echo json_encode(generarColores(count($datos_reporte['ingresos_categoria']))); ?> }]
                },
                options: { responsive: true, maintainAspectRatio: false, animation: { onComplete: () => saveChartImage('ingresosCategoria') } }
            });
        }

        const gastosData = <?php echo json_encode($datos_reporte['gastos_categoria']); ?>;
        if (gastosData.length > 0) {
            const ctx = document.getElementById('gastosCategoria').getContext('2d');
            window.myCharts.gastos = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: gastosData.map(i => i.categoria),
                    datasets: [{ data: gastosData.map(i => i.total), backgroundColor: <?php echo json_encode(generarColores(count($datos_reporte['gastos_categoria']))); ?> }]
                },
                options: { responsive: true, maintainAspectRatio: false, animation: { onComplete: () => saveChartImage('gastosCategoria') } }
            });
        }

        const evolucionData = <?php echo json_encode($datos_reporte['evolucion_mensual']); ?>;
        if (evolucionData.length > 0) {
            const ctx = document.getElementById('evolucionMensual').getContext('2d');
            window.myCharts.evolucion = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: evolucionData.map(i => i.mes),
                    datasets: [
                        { label: 'Ingresos', data: evolucionData.map(i => i.ingresos), borderColor: '#10B981', fill: true },
                        { label: 'Egresos', data: evolucionData.map(i => i.egresos), borderColor: '#EF4444', fill: true }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: false, animation: { onComplete: () => saveChartImage('evolucionMensual') } }
            });
        }
    <?php elseif ($tipo_reporte === 'metas'): ?>
        const metasData = <?php echo json_encode($datos_reporte['metas_categoria']); ?>;
        if (metasData.length > 0) {
            const ctx = document.getElementById('metasCategoria').getContext('2d');
            window.myCharts.metas = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: metasData.map(i => i.categoria || 'Sin categoría'),
                    datasets: [
                        { label: 'Metas Completadas', data: metasData.map(i => i.metas_completadas), backgroundColor: '#10B981' },
                        { label: 'Metas Pendientes', data: metasData.map(i => i.total_metas - i.metas_completadas), backgroundColor: '#EF4444' }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true } }, animation: { onComplete: () => saveChartImage('metasCategoria') } }
            });
        }
    <?php endif; ?>
}

function saveChartImage(canvasId) {
    const canvas = document.getElementById(canvasId);
    const hiddenInput = document.getElementById(canvasId + 'Chart');
    if (canvas && hiddenInput) {
        hiddenInput.value = canvas.toDataURL('image/png');
    }
}

function toggleCustomDates() {
    document.getElementById('custom-dates').style.display = document.getElementById('periodo').value === 'personalizado' ? 'grid' : 'none';
}

function generateReport() {
    const form = document.getElementById('reportForm');
    form.action = 'reportes.php'; // Asegurarse que la acción sea la página actual
    form.method = 'GET'; // Usar GET para generar el reporte en la página
    // Limpiar los campos de gráficos para no enviar data innecesaria
    document.querySelectorAll('input[type=hidden]').forEach(input => input.value = '');
    form.submit();
}

function printReport() {
    // Asegurarse que todos los gráficos hayan terminado de renderizarse y guardado su imagen
    setTimeout(() => {
        const form = document.getElementById('reportForm');
        form.action = 'reportes.php'; // La acción sigue siendo la misma página
        form.method = 'POST'; // Usar POST para enviar los datos de los gráficos
        // Añadir un campo para identificar la acción de imprimir
        let actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'print';
        form.appendChild(actionInput);
        form.submit();
        // Remover el input después de enviar para no interferir con "Generar Reporte"
        form.removeChild(actionInput);
    }, 500); // Un pequeño delay para dar tiempo a los gráficos a renderizarse
}

// Renderizar los gráficos cuando la página carga
document.addEventListener('DOMContentLoaded', function() {
    renderCharts();
    toggleCustomDates();
});
</script>

<?php
// --- INICIO: Procesamiento final para PDF ---
if (isset($_POST['action']) && $_POST['action'] === 'print') {
    
    // Capturar el HTML generado del reporte
    $report_html = ob_get_clean();

    // Crear el HTML final para el PDF
    $pdf_html = '<html><head><title>Reporte</title>';
    $pdf_html .= '<style>' . file_get_contents(__DIR__ . '/includes/reporte_pdf_styles.php') . '</style>';
    $pdf_html .= '</head><body>';
    
    // Añadir el título y período
    $pdf_html .= "<h1>Reporte de " . htmlspecialchars(ucfirst($tipo_reporte)) . "</h1>";
    $pdf_html .= "<p>Periodo: " . htmlspecialchars(str_replace('_', ' ', ucfirst($periodo))) . "</p>";
    if ($periodo === 'personalizado') {
        $pdf_html .= "<p>Desde: " . htmlspecialchars($fecha_inicio) . " Hasta: " . htmlspecialchars($fecha_fin) . "</p>";
    }
    $pdf_html .= "<hr>";

    // Reemplazar los canvas de los gráficos con las imágenes Base64 enviadas
    $report_content_html = file_get_contents(__DIR__ . '/reportes.php'); // Cargar el contenido de nuevo para procesarlo
    
    // Extraer solo el div#report-content
    $doc = new DOMDocument();
    @$doc->loadHTML($report_html);
    $contentNode = $doc->getElementById('report-content');
    $content_html = $doc->saveHTML($contentNode);

    // Reemplazar canvas con imágenes
    if (!empty($_POST['ingresosCategoriaChart'])) {
        $content_html = preg_replace('/<div class="h-80"><canvas id="ingresosCategoria"><\/canvas><\/div>/', '<img src="' . $_POST['ingresosCategoriaChart'] . '" class="chart-img">', $content_html);
    }
    if (!empty($_POST['gastosCategoriaChart'])) {
        $content_html = preg_replace('/<div class="h-80"><canvas id="gastosCategoria"><\/canvas><\/div>/', '<img src="' . $_POST['gastosCategoriaChart'] . '" class="chart-img">', $content_html);
    }
    if (!empty($_POST['evolucionMensualChart'])) {
        $content_html = preg_replace('/<div class="h-96"><canvas id="evolucionMensual"><\/canvas><\/div>/', '<img src="' . $_POST['evolucionMensualChart'] . '" class="chart-img-large">', $content_html);
    }
     if (!empty($_POST['metasCategoriaChart'])) {
        $content_html = preg_replace('/<div class="h-80"><canvas id="metasCategoria"><\/canvas><\/div>/', '<img src="' . $_POST['metasCategoriaChart'] . '" class="chart-img">', $content_html);
    }

    $pdf_html .= $content_html;
    $pdf_html .= '</body></html>';

    // Configurar dompdf
    $options = new Options();
    $options->set('isRemoteEnabled', true); // Permitir cargar imágenes remotas si las hubiera
    $options->set('defaultFont', 'Helvetica');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($pdf_html);
    $dompdf->setPaper('A4', 'portrait'); // Tamaño de papel y orientación
    $dompdf->render();

    // Enviar el PDF al navegador
    $dompdf->stream("reporte-" . $tipo_reporte . "-" . date("Y-m-d") . ".pdf", ["Attachment" => 0]); // 0 para previsualizar, 1 para descargar
    
    exit(); // Detener la ejecución del script
}

require_once __DIR__ . '/includes/footer.php';
?>