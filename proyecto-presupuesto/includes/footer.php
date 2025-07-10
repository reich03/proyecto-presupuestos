<?php
?>
        </main>
    </div>
    
    <script>
        function showAlert(message, type = 'info') {
            const alertContainer = document.createElement('div');
            alertContainer.className = `alert fixed top-4 right-4 z-50 px-4 py-3 rounded border max-w-sm ${getAlertClasses(type)}`;
            alertContainer.innerHTML = `
                <span class="block sm:inline">
                    <i class="${getAlertIcon(type)} mr-2"></i>
                    ${message}
                </span>
                <button onclick="this.parentElement.remove()" class="ml-4 text-lg font-bold">&times;</button>
            `;
            
            document.body.appendChild(alertContainer);
            
            setTimeout(() => {
                alertContainer.remove();
            }, 5000);
        }
        
        function getAlertClasses(type) {
            switch(type) {
                case 'success': return 'bg-green-100 border-green-400 text-green-700';
                case 'error': return 'bg-red-100 border-red-400 text-red-700';
                case 'warning': return 'bg-yellow-100 border-yellow-400 text-yellow-700';
                default: return 'bg-blue-100 border-blue-400 text-blue-700';
            }
        }
        
        function getAlertIcon(type) {
            switch(type) {
                case 'success': return 'fas fa-check-circle';
                case 'error': return 'fas fa-exclamation-triangle';
                case 'warning': return 'fas fa-exclamation-circle';
                default: return 'fas fa-info-circle';
            }
        }
        
        // Función para confirmar eliminación
        function confirmDelete(message = '¿Está seguro que desea eliminar este elemento?') {
            return confirm(message);
        }
        
        // Función para formatear moneda
        function formatCurrency(amount) {
            return new Intl.NumberFormat('es-CO', {
                style: 'currency',
                currency: 'COP'
            }).format(amount);
        }
        
        // Función para formatear fecha
        function formatDate(date) {
            return new Date(date).toLocaleDateString('es-CO');
        }
        
        // Inicializar DataTables por defecto
        $(document).ready(function() {
            $('.datatable').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                },
                responsive: true,
                pageLength: 10,
                order: [[0, 'desc']]
            });
        });
    </script>
</body>
</html>

