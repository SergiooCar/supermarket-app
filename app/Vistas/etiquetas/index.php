<?php
/**
 *
 * Vista: Estado de etiquetas ESL.
 *
 * AgGrid con todas las etiquetas registradas en ZKONG: código MAC, código
 * de barras del artículo vinculado, estado de batería y conectividad.
 * Soporta filtrado por MAC, barcode y estado directamente en el grid.
 */
?>

<div class="row mb-2">
    <div class="col-12 d-flex align-items-center flex-wrap">
        <input type="text" id="input-buscar-mac" class="form-control form-control-sm mr-2 mb-1"
               placeholder="Filtrar por MAC etiqueta..." style="width:220px;">
        <input type="text" id="input-buscar-barcode" class="form-control form-control-sm mr-2 mb-1"
               placeholder="Filtrar por cód. barras..." style="width:220px;">
        <select id="filtro-estado" class="form-control form-control-sm mr-2 mb-1" style="width:160px;">
            <option value="">Todos los estados</option>
            <option value="online">En línea</option>
            <option value="offline">Desconectado</option>
        </select>
        <button class="btn btn-sm btn-secondary mb-1" id="btn-recargar">
            <i class="fas fa-sync"></i> Actualizar
        </button>
        <span id="badge-total" class="badge badge-secondary ml-2 mb-1" style="font-size:0.9rem;"></span>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div id="tabla-etiquetas" class="ag-theme-alpine" style="height:520px;"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    let gridApi   = null;
    let todosDatos = [];

    const estadoRenderer = p => {
        const v = (p.value ?? '').toString().toLowerCase();
        if (v === 'online' || v === '1' || v === 'true') {
            return '<span class="badge badge-success">En línea</span>';
        }
        return '<span class="badge badge-secondary">Desconectado</span>';
    };

    const columnDefs = [
        { field: 'eslBarcode',       headerName: 'Cód. etiqueta (ESL)', width: 180, filter: true },
        { field: 'itemBarCode',      headerName: 'Cód. barras artículo', width: 160, filter: true },
        { field: 'itemTitle',        headerName: 'Artículo',             flex: 1,    filter: true },
        { field: 'state',            headerName: 'Estado',               width: 130, cellRenderer: estadoRenderer },
        { field: 'model',            headerName: 'Modelo',               width: 120 },
        { field: 'size',             headerName: 'Tamaño',               width: 90  },
        {
            field: 'batteryLevel',   headerName: 'Batería (%)',          width: 110,
            valueFormatter: p => p.value !== null && p.value !== undefined ? p.value + ' %' : '—',
        },
        { field: 'apSignal',         headerName: 'Señal AP',             width: 100 },
        {
            field: 'lastCommuTime',  headerName: 'Última comunicación',  width: 190,
            sortable: true,
        },
        {
            headerName: 'Preview',   width: 100, sortable: false, filter: false,
            cellRenderer: p => `<button class="btn btn-xs btn-info btn-preview"
                data-barcode="${p.data.itemBarCode ?? ''}" title="Vista previa">
                <i class="fas fa-eye"></i> Preview</button>`,
        },
    ];

    function cargar() {
        Swal.fire({ title: 'Cargando etiquetas...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        $('#badge-total').text('Cargando...');
        $.ajax({
            url: BASE_URL + '/etiquetas/lista', 
            method: 'GET', 
            dataType: 'json',
            success: function (resp) {
                Swal.close();
                todosDatos = resp.items ?? [];
                $('#badge-total').text(todosDatos.length + ' etiquetas');
                if (!gridApi) {
                    gridApi = agGrid.createGrid(
                        document.getElementById('tabla-etiquetas'),
                        {
                            columnDefs,
                            defaultColDef: { resizable: true },
                            pagination: true,
                            paginationPageSize: 100,
                        }
                    );
                }
                gridApi.setGridOption('rowData', todosDatos);
                aplicarFiltros();
                if (!resp.ok) {
                    Swal.fire({ icon: 'warning', title: 'Aviso ZKONG', text: resp.detalle_error });
                }
            },
            error: function (xhr) {
                Swal.close();
                $('#badge-total').text('Error');
                Swal.fire('Error', xhr.responseJSON?.detalle_error ?? 'No se pudieron cargar las etiquetas.', 'error');
            }
        });
    }

    function aplicarFiltros() {
        if (!gridApi) return;
        const mac     = $('#input-buscar-mac').val().toLowerCase();
        const barcode = $('#input-buscar-barcode').val().toLowerCase();
        const estado  = $('#filtro-estado').val().toLowerCase();

        const filtrados = todosDatos.filter(f => {
            const eslOk    = !mac    || (f.eslBarcode ?? '').toLowerCase().includes(mac);
            const bcOk     = !barcode || (f.itemBarCode ?? '').toLowerCase().includes(barcode);
            const stateVal = (f.state ?? '').toString().toLowerCase();
            const isOnline = stateVal === 'online' || stateVal === '1' || stateVal === 'true';
            const stateOk  = !estado
                || (estado === 'online'  && isOnline)
                || (estado === 'offline' && !isOnline);
            return eslOk && bcOk && stateOk;
        });

        gridApi.setGridOption('rowData', filtrados);
        $('#badge-total').text(filtrados.length + ' / ' + todosDatos.length + ' etiquetas');
    }

    $('#input-buscar-mac, #input-buscar-barcode').on('input', aplicarFiltros);
    $('#filtro-estado').on('change', aplicarFiltros);
    $('#btn-recargar').on('click', cargar);

    document.getElementById('tabla-etiquetas').addEventListener('click', function (e) {
        const btn     = e.target.closest('.btn-preview');
        if (!btn) return;
        const barcode = btn.dataset.barcode;
        if (!barcode) {
            Swal.fire('Sin vinculación', 'Esta etiqueta no tiene artículo asociado.', 'info');
            return;
        }

        Swal.fire({ title: 'Cargando vista previa...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        $.ajax({
            url: BASE_URL + '/emparejar/vista-previa',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ barcode }),
            dataType: 'json',
            success: function (r) {
                if (r.ok && r.imagen) {
                    Swal.fire({
                        title: 'Vista previa — ' + barcode,
                        html: `<img src="${r.imagen}" alt="Vista previa ESL"
                                    style="max-width:100%;border:1px solid #dee2e6;border-radius:4px;">`,
                        width: 500,
                        showCloseButton: true,
                        showConfirmButton: false,
                    });
                } else {
                    Swal.fire({ icon: 'info', title: 'Sin imagen', text: r.detalle_error ?? 'No hay imagen disponible.' });
                }
            },
            error: function (xhr) {
                Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.detalle_error ?? 'Error al obtener la vista previa.' });
            }
        });
    });

    cargar();
});
</script>
