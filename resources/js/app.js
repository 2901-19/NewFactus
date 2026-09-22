import './bootstrap';
import * as bootstrap from 'bootstrap/dist/js/bootstrap.bundle.min.js';
import Alpine from 'alpinejs';
import $ from 'jquery';
import 'datatables.net-bs5';
import Swal from 'sweetalert2';
import Chart from 'chart.js/auto';

window.bootstrap = bootstrap;
window.Alpine = Alpine;
window.$ = $;
window.jQuery = $;
window.Swal = Swal;
window.Chart = Chart;

// Formato de números local (español: 1.234,56). Excluir inputs type=number.
window.fmtMoneda = (valor, decimales = 2) =>
    Number(valor).toLocaleString('es-VE', {
        minimumFractionDigits: decimales,
        maximumFractionDigits: decimales,
    });

// SweetAlert2 config por defecto
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
    },
});
window.Toast = Toast;

// Reemplazar alerts flash nativos por SweetAlert2
document.addEventListener('DOMContentLoaded', function () {
    const successMsg = document.getElementById('flash-success')?.value;
    const errorMsg = document.getElementById('flash-error')?.value;

    if (successMsg) {
        Toast.fire({ icon: 'success', title: successMsg });
    }
    if (errorMsg) {
        Toast.fire({ icon: 'error', title: errorMsg });
    }
});

// DataTables default config
$.extend($.fn.dataTable.defaults, {
    language: {
        processing: 'Procesando...',
        lengthMenu: 'Mostrar _MENU_ registros',
        zeroRecords: 'No se encontraron resultados',
        emptyTable: 'Ningún dato disponible en esta tabla',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
        infoEmpty: 'Mostrando 0 a 0 de 0 registros',
        infoFiltered: '(filtrado de _MAX_ registros totales)',
        infoThousands: '.',
        loadingRecords: 'Cargando...',
        search: 'Buscar:',
        paginate: {
            first: 'Primero',
            last: 'Último',
            next: 'Siguiente',
            previous: 'Anterior',
        },
        aria: {
            sortAscending: 'Activar para ordenar ascendente',
            sortDescending: 'Activar para ordenar descendente',
        },
    },
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100],
});

Alpine.start();
