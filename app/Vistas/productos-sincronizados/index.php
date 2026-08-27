<?php
/**
 *
 * Vista: Productos sincronizados con ZKONG.
 *
 * AgGrid con todos los productos cuyo estado_zkong es 'sincronizado'.
 * Incluye exportación a CSV del contenido visible. Los datos se cargan
 * vía AJAX (cabecera X-Requested-With) desde ProductosSincronizadosControlador.
 */
?>

<div class="row mb-2">
    <div class="col-12 d-flex align-items-center">
        <span id="badge-total" class="badge badge-success mr-2"></span>
        <input type="text" id="input-buscar" class="form-control form-control-sm mr-2"
               placeholder="Buscar producto..." style="width:220px;">
        <button class="btn btn-sm btn-success" id="btn-exportar-csv">
            <i class="fas fa-file-csv"></i> Exportar CSV
        </button>
        <button class="btn btn-sm btn-secondary ml-1" id="btn-recargar">
            <i class="fas fa-sync"></i> Actualizar
        </button>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div id="tabla-productos" class="ag-theme-alpine" style="height:560px;"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    let gridApi    = null;
    let todosDatos = [];

    const numberFmt = p => p.value != null ? p.value.toFixed(2).replace('.', ',') + ' €' : '—';

    const columnDefs = [
        { field: 'codigo_barras', headerName: 'Código barras', width: 140, sortable: true, filter: true },
        { field: 'referencia',    headerName: 'Referencia',     width: 110 },
        { field: 'nombre',        headerName: 'Nombre',         flex: 1,   sortable: true, filter: true },
        { field: 'precio_venta',  headerName: 'Precio venta',   width: 130, sortable: true, cellRenderer: numberFmt },
        { field: 'precio_oferta', headerName: 'Precio oferta',  width: 130, cellRenderer: numberFmt },
        { field: 'precio_tarifa', headerName: 'Precio tarifa',  width: 130, cellRenderer: numberFmt },
        { field: 'precio_frio',   headerName: 'Precio frío',    width: 120, cellRenderer: numberFmt },
        { field: 'precio_unidad', headerName: 'Precio unidad',  width: 130, cellRenderer: numberFmt },
        { field: 'tipo_unidad',   headerName: 'Unidad',         width: 90 },
        { field: 'cant_caja',     headerName: 'Cant/caja',      width: 100 },
    ];

    const gridOptions = {
        columnDefs,
        rowData: [],
        defaultColDef: { resizable: true },
        pagination: true,
        paginationPageSize: 100,
    };

    function cargar() {
        $.ajax({
            url: BASE_URL + '/productos-sincronizados',
            method: 'GET',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function (datos) {
                todosDatos = datos;
                $('#badge-total').text(datos.length + ' productos');
                if (!gridApi) {
                    gridApi = agGrid.createGrid(
                        document.getElementById('tabla-productos'), 
                        gridOptions
                    );
                }
                gridApi.setGridOption('rowData', datos);
                aplicarFiltros();
            },
            error: function () {
                Swal.fire('Error', 'No se pudieron cargar los productos.', 'error');
            }
        });
    }

    function aplicarFiltros() {
        if (!gridApi) return;
        const texto = $('#input-buscar').val().toLowerCase();
        const filtrados = todosDatos.filter(fila =>
            !texto
            || (fila.nombre ?? '').toLowerCase().includes(texto)
            || (fila.codigo_barras ?? '').toLowerCase().includes(texto)
            || (fila.referencia ?? '').toLowerCase().includes(texto)
        );
        gridApi.setGridOption('rowData', filtrados);
    }

    $('#input-buscar').on('input', aplicarFiltros);
    $('#btn-recargar').on('click', cargar);

    function formatNum(v) {
        return typeof v === 'number' ? v.toFixed(2).replace('.', ',') : '';
    }

    function escapeCsv(val) {
        return val != null ? String(val) : '';
    }

    $('#btn-exportar-csv').on('click', function () {
        if (!gridApi || gridApi.getDisplayedRowCount() === 0) {
            Swal.fire('Sin datos', 'No hay productos para exportar.', 'info');
            return;
        }

        var filas = [];
        filas.push(['ref', 'idArt', 'nombre', 'pvp', 'cantCaja', 'precioUnidad', 'tipoUnidad', 'pvpOferta', 'pvpTarifa', 'pvpFrio'].join(';'));

        var datos = [];
        gridApi.forEachNodeAfterFilterAndSort(function (node) {
            datos.push(node.data);
        });

        datos.forEach(function (r) {
            filas.push([
                escapeCsv(r.referencia),
                escapeCsv(r.codigo_barras),
                escapeCsv(r.nombre),
                formatNum(r.precio_venta),
                formatNum(r.cant_caja),
                formatNum(r.precio_unidad),
                escapeCsv(r.tipo_unidad),
                formatNum(r.precio_oferta),
                formatNum(r.precio_tarifa),
                formatNum(r.precio_frio),
            ].join(';'));
        });

        var csv = filas.join('\r\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'productos-sincronizados.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
    });

    cargar();
});
</script>
