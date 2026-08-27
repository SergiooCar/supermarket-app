<?php
/**
 *
 * Vista: Logs de llamadas a la API ZKONG (solo admin).
 *
 * Tabla paginada con el historial de peticiones HTTP enviadas a ZKONG:
 * endpoint, método, cantidad de productos, código de respuesta, mensaje
 * y duración en ms. Permite filtrar por endpoint y ver errores recientes.
 * Es el primer sitio a consultar al depurar problemas de sincronización.
 */
?>
<style>
.ag-theme-alpine .ag-cell-wrap-text { white-space: normal !important; }
</style>

<div class="row mb-2">
    <div class="col-12 d-flex align-items-center">
        <input type="text" id="input-buscar" class="form-control form-control-sm mr-2"
               placeholder="Buscar endpoint..." style="width:220px;">
        <select id="filtro-resultado" class="form-control form-control-sm" style="width:160px;">
            <option value="">Todos</option>
            <option value="exito">Solo éxitos</option>
            <option value="error">Solo errores</option>
        </select>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div id="tabla-logs" class="ag-theme-alpine" style="height:520px;"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    let gridApi    = null;
    let todosDatos = [];

    const columnDefs = [
        {
            field: 'creado_en', headerName: 'Fecha / hora', width: 170, sortable: true,
            sort: 'desc',
        },
        { field: 'endpoint', headerName: 'Endpoint', flex: 1, sortable: true, filter: true },
        { field: 'metodo',   headerName: 'Método',   width: 90 },
        { field: 'cantidad_productos', headerName: 'Productos', width: 110 },
        {
            field: 'codigo_respuesta', headerName: 'Código', width: 100,
            cellStyle: params => params.value !== 10000 && params.value !== null
                ? { color: '#721c24', fontWeight: 'bold' } : null,
        },
        {
            field: 'mensaje_respuesta', headerName: 'Mensaje', flex: 1,
            wrapText: true,
            autoHeight: true,
            cellStyle: { 'white-space': 'normal', 'word-break': 'break-word', 'line-height': '1.5', 'padding-top': '6px', 'padding-bottom': '6px' },
        },
        {
            field: 'duracion_ms', headerName: 'Duración (ms)', width: 130,
            valueFormatter: p => p.value !== null ? p.value + ' ms' : '—',
        },
    ];

    const gridOptions = {
        columnDefs,
        rowData: [],
        defaultColDef: { resizable: true },
        pagination: true,
        paginationPageSize: 100,
        rowClassRules: {
            'table-danger': params =>
                params.data.codigo_respuesta !== 10000 && params.data.codigo_respuesta !== null,
        },
    };

    function cargar() {
        $.ajax({
            url: BASE_URL + '/admin/logs-zkong',
            method: 'GET',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function (resp) {
                var datos = resp.items || resp;
                todosDatos = datos;
                if (!gridApi) {
                    gridApi = agGrid.createGrid(
                        document.getElementById('tabla-logs'), 
                        gridOptions
                    );
                }
                gridApi.setGridOption('rowData', datos);
            },
            error: function () {
                Swal.fire('Error', 'No se pudieron cargar los logs.', 'error');
            }
        });
    }

    function aplicarFiltros() {
        if (!gridApi) return;
        const texto     = $('#input-buscar').val().toLowerCase();
        const resultado = $('#filtro-resultado').val();
        const filtrados = todosDatos.filter(fila => {
            const coincideTexto     = !texto || (fila.endpoint ?? '').toLowerCase().includes(texto);
            const esExito           = fila.codigo_respuesta === 10000;
            const coincideResultado = !resultado
                || (resultado === 'exito' && esExito)
                || (resultado === 'error' && !esExito);
            return coincideTexto && coincideResultado;
        });
        gridApi.setGridOption('rowData', filtrados);
    }

    $('#input-buscar').on('input', aplicarFiltros);
    $('#filtro-resultado').on('change', aplicarFiltros);

    cargar();
});
</script>
