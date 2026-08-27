<?php
/**
 *
 * Vista: Histórico de importaciones CSV (solo admin).
 *
 * AgGrid con todas las importaciones registradas: fichero, contadores
 * (nuevos, modificados, eliminados), estado de BD y estado ZKONG.
 * Las importaciones con estado_zkong distinto de 'sincronizado' muestran
 * un botón de reintento que vuelve a enviar los productos a ZKONG.
 */
?>
<div class="row mb-2">
    <div class="col-12 d-flex align-items-center">
        <span id="badge-total" class="badge badge-secondary mr-2" style="font-size:0.9rem;"></span>
        <button class="btn btn-sm btn-secondary" id="btn-recargar">
            <i class="fas fa-sync"></i> Actualizar
        </button>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div id="tabla-importaciones" class="ag-theme-alpine" style="height:560px;"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    let gridApi = null;

    const estadoBadge = v => {
        const map = {
            completada: 'success', fallida: 'danger', procesando: 'warning'
        };
        return `<span class="badge badge-${map[v] ?? 'secondary'}">${v ?? '—'}</span>`;
    };

    const zkongBadge = v => {
        const map = {
            sincronizado: 'success', fallido: 'danger', pendiente: 'warning'
        };
        return `<span class="badge badge-${map[v] ?? 'secondary'}">${v ?? '—'}</span>`;
    };

    const cols = [
        { field: 'id',               headerName: '#',          width: 70 },
        { field: 'creado_en',        headerName: 'Fecha',      width: 170, sortable: true, sort: 'desc' },
        { field: 'nombre_fichero',   headerName: 'Fichero',    flex: 1,    filter: true },
        { field: 'total_productos',  headerName: 'Total',      width: 80  },
        { field: 'nuevos',           headerName: 'Nuevos',     width: 85,
          cellRenderer: p => p.value > 0 ? `<span class="text-success font-weight-bold">${p.value}</span>` : p.value },
        { field: 'modificados',      headerName: 'Modif.',     width: 85,
          cellRenderer: p => p.value > 0 ? `<span class="text-warning font-weight-bold">${p.value}</span>` : p.value },
        { field: 'eliminados',       headerName: 'Elim.',      width: 85,
          cellRenderer: p => p.value > 0 ? `<span class="text-danger font-weight-bold">${p.value}</span>` : p.value },
        { field: 'estado',           headerName: 'BD',         width: 120, cellRenderer: p => estadoBadge(p.value) },
        { field: 'estado_zkong',     headerName: 'ZKONG',      width: 130, cellRenderer: p => zkongBadge(p.value) },
        {
            headerName: 'Acción', width: 130, sortable: false, filter: false,
            cellRenderer: p => p.data.estado_zkong === 'fallido'
                ? `<button class="btn btn-xs btn-warning btn-reintentar" data-id="${p.data.id}">
                       <i class="fas fa-redo"></i> Reintentar
                   </button>`
                : '',
        },
    ];

    function cargar() {
        $.ajax({
            url: BASE_URL + '/admin/importaciones', 
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            dataType: 'json',
            success: function (datos) {
                $('#badge-total').text(datos.length + ' importaciones');
                if (!gridApi) {
                    gridApi = agGrid.createGrid(
                        document.getElementById('tabla-importaciones'), 
                        {
                            columnDefs: cols,
                            rowData: datos,
                            defaultColDef: { resizable: true },
                            pagination: true,
                            paginationPageSize: 50,
                        }
                    );
                }
                gridApi.setGridOption('rowData', datos);
            }
        });
    }

    document.getElementById('tabla-importaciones').addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-reintentar');
        if (!btn) return;

        const id = btn.dataset.id;
        Swal.fire({
            title: 'Reintentando sincronización...', allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        $.ajax({
            url: BASE_URL + '/importar/reintentar', method: 'POST',
            contentType: 'application/json', data: JSON.stringify({ id: parseInt(id) }), dataType: 'json',
            success: function (r) {
                if (r.success) {
                    Swal.fire({ icon: 'success', title: 'Sincronizado', text: r.message, timer: 2000, showConfirmButton: false });
                    cargar();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: r.error });
                }
            },
            error: xhr => Swal.fire('Error', xhr.responseJSON?.error ?? 'Error al reintentar.', 'error'),
        });
    });

    $('#btn-recargar').on('click', cargar);
    cargar();
});
</script>
