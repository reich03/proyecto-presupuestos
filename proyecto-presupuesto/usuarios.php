<?php
// Archivo: proyecto-presupuesto/usuarios.php
$page_title = 'Gestión de Usuarios';
require_once __DIR__ . '/includes/header.php';

// --- LÓGICA PHP (sin cambios) ---
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
if (!hasPermission('usuarios_crear')) {
    $_SESSION['error_message'] = 'No tienes permisos para gestionar usuarios';
    redirect('dashboard.php');
}
$roles = $db->fetchAll('SELECT * FROM roles WHERE activo = 1 ORDER BY nombre');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    switch ($action) {
        case 'create':
            $usuario = sanitizeInput($_POST['usuario']);
            $email = sanitizeInput($_POST['email']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            $nombre = sanitizeInput($_POST['nombre']);
            $apellido = sanitizeInput($_POST['apellido']);
            $rol_id = $_POST['rol_id'];
            if (empty($usuario) || empty($email) || empty($password) || empty($nombre) || empty($apellido) || empty($rol_id)) {
                $_SESSION['error_message'] = 'Todos los campos requeridos deben ser completados';
            } elseif (!validateEmail($email)) {
                $_SESSION['error_message'] = 'El formato del email es inválido';
            } elseif ($password !== $confirm_password) {
                $_SESSION['error_message'] = 'Las contraseñas no coinciden';
            } elseif (strlen($password) < 6) {
                $_SESSION['error_message'] = 'La contraseña debe tener al menos 6 caracteres';
            } else {
                $existing_user = $db->fetchOne('SELECT id FROM usuarios WHERE usuario = ? OR email = ?', [$usuario, $email]);
                if ($existing_user) {
                    $_SESSION['error_message'] = 'El usuario o email ya existe';
                } else {
                    $data = ['usuario' => $usuario, 'email' => $email, 'password_hash' => hashPassword($password), 'nombre' => $nombre, 'apellido' => $apellido, 'rol_id' => $rol_id];
                    if ($db->insert('usuarios', $data)) {
                        $_SESSION['success_message'] = 'Usuario creado exitosamente';
                        logActivity($_SESSION['user_id'], 'create_user', "Usuario creado: $usuario");
                        redirect('usuarios.php');
                    } else {
                        $_SESSION['error_message'] = 'Error al crear el usuario';
                    }
                }
            }
            break;
        case 'update':
            $id = $_POST['id'];
            $usuario = sanitizeInput($_POST['usuario']);
            $email = sanitizeInput($_POST['email']);
            $nombre = sanitizeInput($_POST['nombre']);
            $apellido = sanitizeInput($_POST['apellido']);
            $rol_id = $_POST['rol_id'];
            $password = $_POST['password'];
            if (empty($usuario) || empty($email) || empty($nombre) || empty($apellido) || empty($rol_id)) {
                $_SESSION['error_message'] = 'Todos los campos requeridos deben ser completados';
            } elseif (!validateEmail($email)) {
                $_SESSION['error_message'] = 'El formato del email es inválido';
            } else {
                $existing_user = $db->fetchOne('SELECT id FROM usuarios WHERE (usuario = ? OR email = ?) AND id != ?', [$usuario, $email, $id]);
                if ($existing_user) {
                    $_SESSION['error_message'] = 'El usuario o email ya existe';
                } else {
                    $data = ['usuario' => $usuario, 'email' => $email, 'nombre' => $nombre, 'apellido' => $apellido, 'rol_id' => $rol_id];
                    if (!empty($password)) {
                        if (strlen($password) < 6) {
                            $_SESSION['error_message'] = 'La contraseña debe tener al menos 6 caracteres';
                            redirect("usuarios.php?action=edit&id=$id");
                            exit;
                        }
                        $data['password_hash'] = hashPassword($password);
                    }
                    if ($db->update('usuarios', $data, 'id = ?', [$id])) {
                        $_SESSION['success_message'] = 'Usuario actualizado exitosamente';
                        logActivity($_SESSION['user_id'], 'update_user', "Usuario actualizado: $usuario");
                        redirect('usuarios.php');
                    } else {
                        $_SESSION['error_message'] = 'Error al actualizar el usuario';
                    }
                }
            }
            break;
        case 'toggle_status':
            $id = $_POST['id'];
            $current_status_row = $db->fetchOne('SELECT activo FROM usuarios WHERE id = ?', [$id]);
            if ($current_status_row) {
                $new_status = $current_status_row['activo'] == 1 ? 0 : 1;
                if ($db->update('usuarios', ['activo' => $new_status], 'id = ?', [$id])) {
                    $_SESSION['success_message'] = 'Estado del usuario actualizado exitosamente';
                    logActivity($_SESSION['user_id'], 'toggle_user_status', "Estado del usuario ID $id cambiado a " . ($new_status ? 'Activo' : 'Inactivo'));
                } else {
                    $_SESSION['error_message'] = 'Error al actualizar el estado del usuario.';
                }
            } else {
                $_SESSION['error_message'] = 'Usuario no encontrado.';
            }
            redirect('usuarios.php');
            break;
    }
}
switch ($action) {
    case 'create':
    case 'edit':
        $usuario = null;
        if ($action === 'edit' && $id) {
            $usuario = $db->fetchOne('SELECT u.*, r.nombre as rol_nombre FROM usuarios u LEFT JOIN roles r ON u.rol_id = r.id WHERE u.id = ?', [$id]);
            if (!$usuario) {
                $_SESSION['error_message'] = 'Usuario no encontrado';
                redirect('usuarios.php');
            }
        }
        break;
    default:
        $usuarios = $db->fetchAll('SELECT u.*, r.nombre as rol_nombre FROM usuarios u LEFT JOIN roles r ON u.rol_id = r.id ORDER BY u.created_at DESC');
        break;
}
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.tailwindcss.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.tailwindcss.min.css">

<style>
    /* Estilo para el input de búsqueda */
    .dataTables_filter input {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid #D1D5DB; /* gray-300 */
        border-radius: 0.375rem; /* rounded-md */
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); /* shadow-sm */
    }
    .dataTables_filter input:focus {
        outline: 2px solid transparent;
        outline-offset: 2px;
        --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
        --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
        box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
        --tw-ring-color: #3B82F6; /* ring-blue-500 */
        border-color: #3B82F6; /* focus:border-blue-500 */
    }
    
    /* Estilo para el selector de cantidad de registros */
    .dataTables_length select {
        padding: 0.5rem 2rem 0.5rem 0.75rem;
        border: 1px solid #D1D5DB; /* gray-300 */
        border-radius: 0.375rem; /* rounded-md */
        background-position: right 0.5rem center;
        background-size: 1.5em 1.5em;
    }

    /* Estilos para la paginación */
    .dataTables_paginate .paginate_button {
        padding: 0.5rem 1rem;
        margin-left: 0.25rem;
        border-radius: 0.375rem; /* rounded-md */
        border: 1px solid transparent;
        transition: background-color 0.2s, color 0.2s;
    }
    .dataTables_paginate .paginate_button.current, .dataTables_paginate .paginate_button.current:hover {
        background-color: #3B82F6 !important; /* bg-blue-600 */
        color: white !important;
        border-color: #3B82F6 !important;
    }
    .dataTables_paginate .paginate_button:hover {
        background-color: #E5E7EB !important; /* bg-gray-200 */
        border-color: #D1D5DB !important; /* border-gray-300 */
    }
    .dataTables_paginate .paginate_button.disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
</style>

<?php if ($action === 'list'): ?>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Gestión de Usuarios</h1>
        <a href="usuarios.php?action=create" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md shadow-sm flex items-center">
            <i class="fas fa-plus mr-2"></i>Nuevo Usuario
        </a>
    </div>
    
    <div class="bg-white shadow-md rounded-lg overflow-x-auto p-4">
        <table id="usuariosTable" class="w-full text-sm text-left text-gray-500">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3">Usuario</th>
                    <th scope="col" class="px-6 py-3">Email</th>
                    <th scope="col" class="px-6 py-3">Nombre</th>
                    <th scope="col" class="px-6 py-3">Rol</th>
                    <th scope="col" class="px-6 py-3">Estado</th>
                    <th scope="col" class="px-6 py-3">Último Login</th>
                    <th scope="col" class="px-6 py-3 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($usuarios as $user): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap"><?php echo htmlspecialchars($user['usuario']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($user['email']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($user['nombre'] . ' ' . $user['apellido']); ?></td>
                        <td class="px-6 py-4">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                <?php echo htmlspecialchars($user['rol_nombre']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $user['activo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $user['activo'] ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4"><?php echo $user['ultimo_login'] ? formatDateTime($user['ultimo_login']) : 'Nunca'; ?></td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center space-x-2">
                                <a href="usuarios.php?action=edit&id=<?php echo $user['id']; ?>" class="bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold py-2 px-3 rounded-md shadow-sm transition duration-150 ease-in-out flex items-center" title="Editar">
                                    <i class="fas fa-edit mr-1"></i> Editar
                                </a>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" action="usuarios.php" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que quieres cambiar el estado de este usuario?');">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                        <?php if ($user['activo']): ?>
                                            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white text-xs font-bold py-2 px-3 rounded-md shadow-sm transition duration-150 ease-in-out flex items-center" title="Desactivar">
                                                <i class="fas fa-user-slash mr-1"></i> Desactivar
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="bg-green-500 hover:bg-green-600 text-white text-xs font-bold py-2 px-3 rounded-md shadow-sm transition duration-150 ease-in-out flex items-center" title="Activar">
                                                <i class="fas fa-user-check mr-1"></i> Activar
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($action === 'create' || $action === 'edit'): ?>
    <div class="max-w-2xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900"><?php echo $action === 'create' ? 'Crear Nuevo Usuario' : 'Editar Usuario'; ?></h1>
            <a href="usuarios.php" class="text-gray-600 hover:text-gray-800 flex items-center"><i class="fas fa-arrow-left mr-2"></i>Volver</a>
        </div>
        <div class="bg-white shadow-md rounded-lg p-6">
            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" value="<?php echo $action === 'create' ? 'create' : 'update'; ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                <?php endif; ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="usuario" class="block text-sm font-medium text-gray-700 mb-2">Usuario *</label>
                        <input type="text" id="usuario" name="usuario" required value="<?php echo htmlspecialchars($usuario['usuario'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                        <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" required value="<?php echo htmlspecialchars($usuario['nombre'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="apellido" class="block text-sm font-medium text-gray-700 mb-2">Apellido *</label>
                        <input type="text" id="apellido" name="apellido" required value="<?php echo htmlspecialchars($usuario['apellido'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div>
                    <label for="rol_id" class="block text-sm font-medium text-gray-700 mb-2">Rol *</label>
                    <select id="rol_id" name="rol_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Seleccione un rol</option>
                        <?php foreach ($roles as $rol): ?>
                            <option value="<?php echo $rol['id']; ?>" <?php echo (isset($usuario['rol_id']) && $usuario['rol_id'] == $rol['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($rol['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Contraseña <?php echo $action === 'create' ? '*' : '(dejar vacío para no cambiar)'; ?></label>
                        <input type="password" id="password" name="password" <?php echo $action === 'create' ? 'required' : ''; ?> class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <?php if ($action === 'create'): ?>
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirmar Contraseña *</label>
                            <input type="password" id="confirm_password" name="confirm_password" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex justify-end space-x-4 pt-4">
                    <a href="usuarios.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-md shadow-sm">Cancelar</a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md shadow-sm">
                        <?php echo $action === 'create' ? 'Crear Usuario' : 'Actualizar Usuario'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.tailwindcss.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.tailwindcss.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
$(document).ready(function() {
    $('#usuariosTable').DataTable({
        destroy: true,
        language: {
            "lengthMenu": "Mostrar _MENU_ registros",
            "zeroRecords": "No se encontraron resultados",
            "info": "Mostrando del _START_ al _END_ de _TOTAL_ registros",
            "infoEmpty": "Mostrando 0 de 0 registros",
            "infoFiltered": "(filtrado de _MAX_ registros totales)",
            "sSearch": "Buscar:",
            "oPaginate": {
                "sFirst": "Primero",
                "sLast": "Último",
                "sNext": "Siguiente",
                "sPrevious": "Anterior"
            },
        },
        // ** CAMBIO: Simplifico el 'dom' para que los estilos CSS tengan mejor control **
        dom: '<"grid grid-cols-1 md:grid-cols-2 gap-4 mb-4" <"flex items-center"l> <"flex items-center justify-end"f> >' +
             't' +
             '<"grid grid-cols-1 md:grid-cols-2 gap-4 mt-4" <"flex items-center"i> <"flex items-center justify-end"p> >',
        responsive: true,
        columnDefs: [
            { "orderable": false, "targets": -1 } // Columna de acciones no ordenable
        ]
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>