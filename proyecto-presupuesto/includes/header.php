<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'auth/check_session.php';

$current_user = getCurrentUser();
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.tailwindcss.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.tailwindcss.min.css">
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.tailwindcss.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .sidebar-transition {
            transition: margin-left 0.3s ease-in-out;
        }
        
        .content-transition {
            transition: margin-left 0.3s ease-in-out;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
        }
        
        /* Estilos personalizados para DataTables */
        .dataTables_wrapper {
            padding: 1rem;
        }
        
        .dataTables_filter input {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.5rem;
            margin-left: 0.5rem;
        }
        
        .dataTables_length select {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.25rem;
            margin: 0 0.5rem;
        }
        
        table.dataTable thead th {
            background-color: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            color: #6b7280;
            padding: 1rem 1.5rem;
        }
        
        table.dataTable tbody tr:hover {
            background-color: #f9fafb;
        }
        
        table.dataTable tbody td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .dt-buttons {
            margin-bottom: 1rem;
        }
        
        .dt-button {
            background-color: #3b82f6;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            margin-right: 0.5rem;
            font-size: 0.875rem;
        }
        
        .dt-button:hover {
            background-color: #2563eb;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <div id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-gray-800 text-white transform -translate-x-full transition-transform duration-300 ease-in-out md:translate-x-0 sidebar">
        <div class="flex items-center justify-center h-16 bg-gray-900">
            <h1 class="text-xl font-bold"><?php echo APP_NAME; ?></h1>
        </div>
        
        <nav class="mt-8">
            <a href="dashboard.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors <?php echo $current_page === 'dashboard' ? 'bg-gray-700 text-white border-r-4 border-blue-500' : ''; ?>">
                <i class="fas fa-tachometer-alt mr-3"></i>
                Dashboard
            </a>
            
            <a href="presupuestos.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors <?php echo $current_page === 'presupuestos' ? 'bg-gray-700 text-white border-r-4 border-blue-500' : ''; ?>">
                <i class="fas fa-calculator mr-3"></i>
                Presupuestos
            </a>
            
            <a href="presupuesto_items.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors <?php echo $current_page === 'presupuesto_items' ? 'bg-gray-700 text-white border-r-4 border-blue-500' : ''; ?>">
                <i class="fas fa-list mr-3"></i>
                Items de Presupuesto
            </a>
            
            <a href="transacciones.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors <?php echo $current_page === 'transacciones' ? 'bg-gray-700 text-white border-r-4 border-blue-500' : ''; ?>">
                <i class="fas fa-exchange-alt mr-3"></i>
                Transacciones
            </a>
            
            <a href="metas.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors <?php echo $current_page === 'metas' ? 'bg-gray-700 text-white border-r-4 border-blue-500' : ''; ?>">
                <i class="fas fa-bullseye mr-3"></i>
                Metas
            </a>
            
            <a href="reportes.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors <?php echo $current_page === 'reportes' ? 'bg-gray-700 text-white border-r-4 border-blue-500' : ''; ?>">
                <i class="fas fa-chart-bar mr-3"></i>
                Reportes
            </a>
            
            <a href="categorias.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors <?php echo $current_page === 'categorias' ? 'bg-gray-700 text-white border-r-4 border-blue-500' : ''; ?>">
                <i class="fas fa-tags mr-3"></i>
                Categorías
            </a>
            
            <?php if (hasPermission('usuarios_crear')): ?>
            <a href="usuarios.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors <?php echo $current_page === 'usuarios' ? 'bg-gray-700 text-white border-r-4 border-blue-500' : ''; ?>">
                <i class="fas fa-users mr-3"></i>
                Usuarios
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('roles_gestionar')): ?>
            <a href="roles.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors <?php echo $current_page === 'roles' ? 'bg-gray-700 text-white border-r-4 border-blue-500' : ''; ?>">
                <i class="fas fa-user-shield mr-3"></i>
                Roles
            </a>
            <?php endif; ?>
        </nav>
        
        <div class="absolute bottom-0 w-full p-4">
            <div class="flex items-center mb-4">
                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-user text-white text-sm"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium"><?php echo $current_user['nombre'] . ' ' . $current_user['apellido']; ?></p>
                    <p class="text-xs text-gray-400"><?php echo $current_user['rol_nombre']; ?></p>
                </div>
            </div>
            
            <button onclick="confirmLogout()" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white rounded transition-colors w-full text-left">
                <i class="fas fa-sign-out-alt mr-3"></i>
                Cerrar Sesión
            </button>
        </div>
    </div>
    
    <!-- Overlay para móviles -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black opacity-50 z-40 hidden md:hidden"></div>
    
    <!-- Contenido principal -->
    <div class="md:ml-64 transition-all duration-300">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="flex items-center justify-between px-6 py-4">
                <div class="flex items-center">
                    <button id="sidebar-toggle" class="md:hidden text-gray-600 hover:text-gray-800">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h2 class="text-xl font-semibold text-gray-800 ml-4 md:ml-0">
                        <?php echo $page_title ?? 'Dashboard'; ?>
                    </h2>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <button class="flex items-center text-gray-600 hover:text-gray-800 p-2 rounded-full hover:bg-gray-100">
                            <i class="fas fa-bell text-xl"></i>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">3</span>
                        </button>
                    </div>
                    
                    <div class="relative">
                        <button class="flex items-center text-gray-600 hover:text-gray-800 p-2 rounded-lg hover:bg-gray-100">
                            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-white text-sm"></i>
                            </div>
                            <span class="ml-2 hidden md:inline font-medium"><?php echo $current_user['nombre']; ?></span>
                            <i class="fas fa-chevron-down ml-2 text-sm"></i>
                        </button>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Contenido de la página -->
        <main class="p-6">
            <?php if (isset($_SESSION['success_message'])): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        showSuccessAlert('<?php echo addslashes($_SESSION['success_message']); ?>');
                    });
                </script>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        showErrorAlert('<?php echo addslashes($_SESSION['error_message']); ?>');
                    });
                </script>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

    <script>
        // Toggle sidebar en móviles
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('hidden');
        });
        
        // Cerrar sidebar al hacer clic en el overlay
        document.getElementById('sidebar-overlay').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            
            sidebar.classList.remove('active');
            overlay.classList.add('hidden');
        });
        
        // Funciones SweetAlert
        function showSuccessAlert(message) {
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: message,
                timer: 3000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        }
        
        function showErrorAlert(message) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message,
                timer: 5000,
                showConfirmButton: true,
                toast: true,
                position: 'top-end'
            });
        }
        
        function confirmDelete(message = '¿Está seguro que desea eliminar este elemento?') {
            return Swal.fire({
                title: '¿Está seguro?',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                return result.isConfirmed;
            });
        }
        
        function confirmLogout() {
            Swal.fire({
                title: '¿Cerrar sesión?',
                text: 'Se cerrará tu sesión actual',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, cerrar sesión',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'auth/logout.php';
                }
            });
        }
        
        // Inicializar DataTables mejoradas
        $(document).ready(function() {
            if ($('.datatable').length > 0) {
                $('.datatable').each(function() {
                    const table = $(this);
                    
                    // Solo inicializar si la tabla tiene filas de datos
                    if (table.find('tbody tr').length > 0 && !table.find('tbody tr:first td').attr('colspan')) {
                        table.DataTable({
                            responsive: true,
                            language: {
                                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                            },
                            dom: 'Bfrtip',
                            buttons: [
                                {
                                    extend: 'excel',
                                    text: '<i class="fas fa-file-excel mr-2"></i>Excel',
                                    className: 'dt-button'
                                },
                                {
                                    extend: 'pdf',
                                    text: '<i class="fas fa-file-pdf mr-2"></i>PDF',
                                    className: 'dt-button'
                                },
                                {
                                    extend: 'print',
                                    text: '<i class="fas fa-print mr-2"></i>Imprimir',
                                    className: 'dt-button'
                                }
                            ],
                            pageLength: 25,
                            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                            order: [[0, 'desc']]
                        });
                    }
                });
            }
        });
    </script>