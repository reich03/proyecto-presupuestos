
<?php

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación - Sistema de Presupuestos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-2xl">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Instalación del Sistema</h1>
            <p class="text-gray-600">Configure su aplicación de presupuestos personales</p>
        </div>

        <?php
        $step = $_GET['step'] ?? 1;
        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($step == 1) {
                // Verificar conexión a la base de datos
                try {
                    $host = $_POST['host'];
                    $database = $_POST['database'];
                    $username = $_POST['username'];
                    $password = $_POST['password'];

                    $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
                    $pdo = new PDO($dsn, $username, $password, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);

                    // Crear archivo de configuración
                    $config_content = "<?php\n";
                    $config_content .= "define('DB_HOST', '$host');\n";
                    $config_content .= "define('DB_NAME', '$database');\n";
                    $config_content .= "define('DB_USER', '$username');\n";
                    $config_content .= "define('DB_PASS', '$password');\n";
                    $config_content .= "define('DB_CHARSET', 'utf8mb4');\n";
                    $config_content .= "?>";

                    file_put_contents('config/database_config.php', $config_content);

                    header('Location: install.php?step=2');
                    exit();

                } catch (PDOException $e) {
                    $error = 'Error de conexión: ' . $e->getMessage();
                }
            } elseif ($step == 2) {
                // Crear usuario administrador
                require_once 'config/database.php';

                $admin_user = $_POST['admin_user'];
                $admin_email = $_POST['admin_email'];
                $admin_password = $_POST['admin_password'];
                $admin_name = $_POST['admin_name'];
                $admin_lastname = $_POST['admin_lastname'];

                $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);

                try {
                    $sql = "UPDATE usuarios SET 
                            usuario = ?, 
                            email = ?, 
                            password_hash = ?, 
                            nombre = ?, 
                            apellido = ? 
                            WHERE id = 1";

                    $stmt = $db->getConnection()->prepare($sql);
                    $stmt->execute([$admin_user, $admin_email, $password_hash, $admin_name, $admin_lastname]);

                    // Crear archivo de marca de instalación
                    file_put_contents('.installed', date('Y-m-d H:i:s'));

                    header('Location: install.php?step=3');
                    exit();

                } catch (Exception $e) {
                    $error = 'Error al crear el administrador: ' . $e->getMessage();
                }
            }
        }
        ?>

        <?php if ($step == 1): ?>
            <!-- Paso 1: Configuración de base de datos -->
            <div class="mb-6">
                <div class="flex items-center mb-4">
                    <div
                        class="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center text-sm font-bold">
                        1</div>
                    <div class="ml-3">
                        <h3 class="text-lg font-semibold">Configuración de Base de Datos</h3>
                        <p class="text-gray-600">Configure la conexión a su base de datos MySQL</p>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="host" class="block text-sm font-medium text-gray-700 mb-2">Host</label>
                        <input type="text" id="host" name="host" value="db" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="database" class="block text-sm font-medium text-gray-700 mb-2">Base de Datos</label>
                        <input type="text" id="database" name="database" value="presupuesto_app" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Usuario</label>
                        <input type="text" id="username" name="username" value="app_user" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Contraseña</label>
                        <input type="password" id="password" name="password" value="app_password123" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                    Probar Conexión y Continuar
                </button>
            </form>

        <?php elseif ($step == 2): ?>
            <!-- Paso 2: Crear administrador -->
            <div class="mb-6">
                <div class="flex items-center mb-4">
                    <div
                        class="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center text-sm font-bold">
                        2</div>
                    <div class="ml-3">
                        <h3 class="text-lg font-semibold">Crear Administrador</h3>
                        <p class="text-gray-600">Configure el usuario administrador del sistema</p>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="admin_user" class="block text-sm font-medium text-gray-700 mb-2">Usuario</label>
                        <input type="text" id="admin_user" name="admin_user" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="admin_email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" id="admin_email" name="admin_email" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="admin_name" class="block text-sm font-medium text-gray-700 mb-2">Nombre</label>
                        <input type="text" id="admin_name" name="admin_name" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="admin_lastname" class="block text-sm font-medium text-gray-700 mb-2">Apellido</label>
                        <input type="text" id="admin_lastname" name="admin_lastname" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div>
                    <label for="admin_password" class="block text-sm font-medium text-gray-700 mb-2">Contraseña</label>
                    <input type="password" id="admin_password" name="admin_password" required minlength="6"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                    Crear Administrador
                </button>
            </form>

        <?php elseif ($step == 3): ?>
            <!-- Paso 3: Instalación completada -->
            <div class="text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check text-2xl text-green-600"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">¡Instalación Completada!</h3>
                <p class="text-gray-600 mb-6">Su sistema de presupuestos personales está listo para usar.</p>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <h4 class="font-semibold text-blue-800 mb-2">Próximos pasos:</h4>
                    <ul class="text-blue-700 text-sm space-y-1">
                        <li>• Elimine el archivo install.php por seguridad</li>
                        <li>• Configure las categorías según sus necesidades</li>
                        <li>• Cree sus primeros presupuestos</li>
                        <li>• Defina sus metas financieras</li>
                    </ul>
                </div>

                <a href="auth/login.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-md inline-block">
                    <i class="fas fa-sign-in-alt mr-2"></i>Ir al Sistema
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>

<?php
// Archivo: proyecto-presupuesto/export.php
$page_title = 'Exportar Datos';
require_once 'includes/header.php';

$user_id = $_SESSION['user_id'];
$tipo_export = $_GET['tipo'] ?? 'transacciones';
$formato = $_GET['formato'] ?? 'csv';

// Procesar exportación
if (isset($_GET['download'])) {
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');

    switch ($tipo_export) {
        case 'transacciones':
            $sql = "SELECT 
                t.fecha,
                t.descripcion,
                cp.nombre as categoria,
                sc.nombre as subcategoria,
                pi.nombre as item,
                p.nombre as presupuesto,
                t.tipo,
                t.monto
            FROM transacciones t
            JOIN presupuesto_items pi ON t.presupuesto_item_id = pi.id
            JOIN presupuestos p ON pi.presupuesto_id = p.id
            JOIN subcategorias sc ON pi.subcategoria_id = sc.id
            JOIN categorias_padre cp ON sc.categoria_padre_id = cp.id
            WHERE p.usuario_id = ? AND t.fecha BETWEEN ? AND ?
            ORDER BY t.fecha DESC";

            $data = $db->fetchAll($sql, [$user_id, $fecha_inicio, $fecha_fin]);
            $filename = "transacciones_{$fecha_inicio}_{$fecha_fin}";
            break;

        case 'presupuestos':
            $sql = "SELECT 
                p.nombre as presupuesto,
                p.fecha_inicio,
                p.fecha_fin,
                pi.nombre as item,
                pi.tipo,
                pi.monto_planificado,
                pi.monto_real,
                cp.nombre as categoria,
                sc.nombre as subcategoria
            FROM presupuestos p
            LEFT JOIN presupuesto_items pi ON p.id = pi.presupuesto_id
            LEFT JOIN subcategorias sc ON pi.subcategoria_id = sc.id
            LEFT JOIN categorias_padre cp ON sc.categoria_padre_id = cp.id
            WHERE p.usuario_id = ? AND p.activo = 1
            ORDER BY p.fecha_inicio DESC, pi.tipo, cp.nombre";

            $data = $db->fetchAll($sql, [$user_id]);
            $filename = "presupuestos_" . date('Y-m-d');
            break;

        case 'metas':
            $sql = "SELECT 
                m.nombre as meta,
                m.descripcion,
                m.monto_objetivo,
                m.monto_actual,
                m.fecha_objetivo,
                m.completado,
                cp.nombre as categoria,
                ROUND((m.monto_actual / m.monto_objetivo) * 100, 2) as porcentaje_progreso
            FROM metas m
            LEFT JOIN categorias_padre cp ON m.categoria_id = cp.id
            WHERE m.usuario_id = ? AND m.activo = 1
            ORDER BY m.fecha_objetivo ASC";

            $data = $db->fetchAll($sql, [$user_id]);
            $filename = "metas_" . date('Y-m-d');
            break;
    }

    // Exportar según formato
    if ($formato === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename={$filename}.csv");

        $output = fopen('php://output', 'w');

        // BOM para UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        if (!empty($data)) {
            // Encabezados
            fputcsv($output, array_keys($data[0]), ';');

            // Datos
            foreach ($data as $row) {
                fputcsv($output, $row, ';');
            }
        }

        fclose($output);
        exit();

    } elseif ($formato === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header("Content-Disposition: attachment; filename={$filename}.json");

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }
}
?>

<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Exportar Datos</h1>
        <a href="dashboard.php" class="text-gray-600 hover:text-gray-800">
            <i class="fas fa-arrow-left mr-2"></i>Volver
        </a>
    </div>

    <div class="bg-white shadow-md rounded-lg p-6">
        <form method="GET" class="space-y-6">
            <div>
                <label for="tipo" class="block text-sm font-medium text-gray-700 mb-2">Tipo de Datos</label>
                <select id="tipo" name="tipo"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="transacciones" <?php echo $tipo_export === 'transacciones' ? 'selected' : ''; ?>>
                        Transacciones</option>
                    <option value="presupuestos" <?php echo $tipo_export === 'presupuestos' ? 'selected' : ''; ?>>
                        Presupuestos</option>
                    <option value="metas" <?php echo $tipo_export === 'metas' ? 'selected' : ''; ?>>Metas</option>
                </select>
            </div>

            <div>
                <label for="formato" class="block text-sm font-medium text-gray-700 mb-2">Formato</label>
                <select id="formato" name="formato"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="csv" <?php echo $formato === 'csv' ? 'selected' : ''; ?>>CSV (Excel)</option>
                    <option value="json" <?php echo $formato === 'json' ? 'selected' : ''; ?>>JSON</option>
                </select>
            </div>

            <div id="date-range" style="display: <?php echo $tipo_export === 'transacciones' ? 'block' : 'none'; ?>;">
                <label class="block text-sm font-medium text-gray-700 mb-2">Rango de Fechas</label>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <input type="date" name="fecha_inicio" value="<?php echo date('Y-m-01'); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <input type="date" name="fecha_fin" value="<?php echo date('Y-m-t'); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="font-semibold text-blue-800 mb-2">Información sobre la exportación:</h4>
                <ul class="text-blue-700 text-sm space-y-1">
                    <li>• Los datos se exportarán según los filtros seleccionados</li>
                    <li>• El formato CSV es compatible con Excel y otras hojas de cálculo</li>
                    <li>• El formato JSON es útil para integraciones técnicas</li>
                    <li>• Todos los datos son exportados con codificación UTF-8</li>
                </ul>
            </div>

            <div class="flex justify-end space-x-3">
                <button type="submit" name="preview"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">
                    Vista Previa
                </button>
                <button type="submit" name="download" value="1"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                    <i class="fas fa-download mr-2"></i>Descargar
                </button>
            </div>
        </form>

        <?php if (isset($_GET['preview'])): ?>
            <div class="mt-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Vista Previa</h3>
                <div class="bg-gray-50 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-sm text-gray-700">
    <?php
    // Mostrar vista previa de los primeros 5 registros
    $preview_data = array_slice($data ?? [], 0, 5);
    if (!empty($preview_data)) {
        if ($formato === 'csv') {
            echo implode(';', array_keys($preview_data[0])) . "\n";
            foreach ($preview_data as $row) {
                echo implode(';', array_map(function ($val) {
                    return '"' . str_replace('"', '""', $val) . '"';
                }, $row)) . "\n";
            }
        } else {
            echo json_encode($preview_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    } else {
        echo "No hay datos para mostrar con los filtros seleccionados.";
    }
    ?>
                        </pre>
                </div>
                <?php if (!empty($data) && count($data) > 5): ?>
                    <p class="text-sm text-gray-600 mt-2">
                        Mostrando 5 de <?php echo count($data); ?> registros.
                        <a href="#" onclick="document.querySelector('button[name=download]').click();"
                            class="text-blue-600 hover:underline">
                            Descargar archivo completo
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.getElementById('tipo').addEventListener('change', function () {
        const dateRange = document.getElementById('date-range');
        if (this.value === 'transacciones') {
            dateRange.style.display = 'block';
        } else {
            dateRange.style.display = 'none';
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>