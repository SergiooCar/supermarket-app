<?php
/**
 *
 * Vista: Estado de antenas (Access Points) ZKONG.
 *
 * AgGrid con la lista de APs de la tienda: MAC, estado online/offline,
 * versión de firmware y número de etiquetas asociadas. Los datos llegan
 * desde EtiquetasControlador::listaAntenas() vía petición AJAX.
 */
?>

<div class="row mb-3">
    <div class="col-12">
        <h4>Estado de antenas (Access Points)</h4>
    </div>
</div>

<div class="row mb-2">
    <div class="col-12 d-flex align-items-center flex-wrap">
        <button class="btn btn-sm btn-secondary mr-2 mb-1" id="btn-recargar">
            <i class="fas fa-sync"></i> Actualizar
        </button>
        <span id="badge-total" class="badge badge-secondary mr-2 mb-1" style="font-size:0.9rem;"></span>
        <div class="alert alert-info alert-dismissible mt-0 mb-0 py-1 px-2">
            <i class="fas fa-info-circle"></i> Solo lectura — los AP son gestionados por el proveedor ZKONG.
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div id="tabla-antenas" class="ag-theme-alpine" style="height:400px;"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    let gridApi = null;

    const estadoRenderer = p => {
        const v = (p.value ?? '').toString().toLowerCase();
        if (v === 'online' || v === '1' || v === 'true') {
            return '<span class="badge badge-success">En línea</span>';
        }
        return '<span class="badge badge-secondary">Desconectado</span>';
    };

    const columnDefs = [
        { field: 'apMac',         headerName: 'MAC Antena',          width: 180, filter: true },
        { field: 'apName',        headerName: 'Nombre',              flex: 1,    filter: true },
        { field: 'state',         headerName: 'Estado',              width: 130, cellRenderer: estadoRenderer },
        { field: 'ip',            headerName: 'IP',                  width: 140 },
        { field: 'softVersion',   headerName: 'Firmware',            width: 130 },
        { field: 'lastHeartTime', headerName: 'Último latido',       width: 190, sortable: true },
        { field: 'bindTagCount',  headerName: 'Etiquetas vinculadas', width: 160,
          valueFormatter: p => p.value !== null && p.value !== undefined ? p.value : '—' },
    ];

    function cargar() {
        $.ajax({
            url: BASE_URL + '/antenas/lista', 
            method: 'GET', 
            dataType: 'json',
            success: function (resp) {
                const datos = resp.items ?? [];
                $('#badge-total').text(datos.length + ' antenas');
                if (!gridApi) {
                    gridApi = agGrid.createGrid(
                        document.getElementById('tabla-antenas'),
                        {
                            columnDefs,
                            defaultColDef: { resizable: true },
                        }
                    );
                }
                gridApi.setGridOption('rowData', datos);
                if (!resp.ok) {
                    Swal.fire({ icon: 'warning', title: 'Aviso ZKONG', text: resp.detalle_error });
                }
            },
            error: function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.detalle_error ?? 'No se pudo cargar el estado de antenas.', 'error');
            }
        });
    }

    $('#btn-recargar').on('click', cargar);
    cargar();
});
</script>
