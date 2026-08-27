<?php /** @var string $tituloPagina */ ?>

<style>
.ag-theme-alpine .ag-pinned-right-header,
.ag-theme-alpine .ag-pinned-right-cols-container {
    border-left: none !important;
}
</style>

<div class="row mb-2">
    <div class="col-12 d-flex align-items-center flex-wrap" style="gap:8px;">
        <span id="badge-total" class="badge badge-secondary" style="font-size:0.9rem;"></span>
        <span id="badge-pendientes" class="badge badge-warning" style="font-size:0.9rem; display:none;"></span>
        <input type="text" id="input-buscar" class="form-control form-control-sm"
               placeholder="Buscar por nombre, código o referencia..." style="width:260px;">
        <button class="btn btn-sm btn-warning" id="btn-sync-pendientes" style="display:none;">
            <i class="fas fa-cloud-upload-alt"></i> <span id="lbl-sync-btn">Enviar pendientes al cloud</span>
        </button>
        <button class="btn btn-sm btn-success" id="btn-exportar-csv">
            <i class="fas fa-file-csv"></i> Exportar CSV
        </button>
        <button class="btn btn-sm btn-secondary" id="btn-recargar">
            <i class="fas fa-sync-alt"></i> Actualizar
        </button>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div id="tabla-productos" class="ag-theme-alpine" style="height:560px;"></div>
    </div>
</div>

<!-- Modal editar producto -->
<div class="modal fade" id="modal-editar" tabindex="-1" role="dialog" aria-labelledby="modal-editar-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-editar-titulo">Editar producto</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p class="text-muted small mb-0">Código de barras</p>
                        <p class="font-weight-bold mb-0" id="lbl-codigo-barras" style="font-family:monospace;"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="mb-0" for="fld-referencia">Referencia</label>
                        <input type="text" id="fld-referencia" class="form-control form-control-sm">
                    </div>
                </div>

                <div class="form-group">
                    <label for="fld-nombre">Nombre <span class="text-danger">*</span></label>
                    <input type="text" id="fld-nombre" class="form-control">
                </div>

                <div class="row">
                    <div class="col-md-4 col-sm-6">
                        <div class="form-group">
                            <label for="fld-pvp">Precio venta <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="number" id="fld-pvp" class="form-control" step="0.01" min="0">
                                <div class="input-group-append"><span class="input-group-text">€</span></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="form-group">
                            <label for="fld-oferta">Precio oferta</label>
                            <div class="input-group input-group-sm">
                                <input type="number" id="fld-oferta" class="form-control" step="0.01" min="0" placeholder="—">
                                <div class="input-group-append"><span class="input-group-text">€</span></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="form-group">
                            <label for="fld-tarifa">Precio tarifa</label>
                            <div class="input-group input-group-sm">
                                <input type="number" id="fld-tarifa" class="form-control" step="0.01" min="0" placeholder="—">
                                <div class="input-group-append"><span class="input-group-text">€</span></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="form-group">
                            <label for="fld-frio">Precio frío</label>
                            <div class="input-group input-group-sm">
                                <input type="number" id="fld-frio" class="form-control" step="0.01" min="0" placeholder="—">
                                <div class="input-group-append"><span class="input-group-text">€</span></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="form-group">
                            <label for="fld-precio-unidad">Precio unidad</label>
                            <div class="input-group input-group-sm">
                                <input type="number" id="fld-precio-unidad" class="form-control" step="0.0001" min="0">
                                <div class="input-group-append"><span class="input-group-text">€</span></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <div class="form-group">
                            <label for="fld-tipo-unidad">Tipo unidad</label>
                            <input type="text" id="fld-tipo-unidad" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <div class="form-group">
                            <label for="fld-cant-caja">Cant/caja</label>
                            <input type="number" id="fld-cant-caja" class="form-control form-control-sm" step="1" min="0">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-guardar">
                    <i class="fas fa-save"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    let gridApi      = null;
    let todosDatos   = [];
    let codigoActual = '';

    const numberFmt = function (params) {
        return params.value != null ? Number(params.value).toFixed(2).replace('.', ',') + ' €' : '—';
    };

    const estadoRenderer = function (params) {
        const config = {
            sincronizado: ['success', 'Sincronizado'],
            pendiente:    ['warning', 'Pendiente'],
            fallido:      ['danger',  'Fallido'],
        };
        const [cls, label] = config[params.value] || ['secondary', params.value || '—'];
        return '<span class="badge badge-' + cls + '">' + label + '</span>';
    };

    const accionesRenderer = function (params) {
        var barcode = params.data.codigo_barras;
        var html = '<button class="btn btn-sm btn-secondary btn-editar-fila mr-1" '
            + 'data-barcode="' + barcode + '" title="Editar">'
            + '<i class="fas fa-pen fa-xs"></i></button>';
        if (params.data.estado_zkong !== 'sincronizado') {
            html += '<button class="btn btn-sm btn-primary btn-sync-fila" '
                + 'data-barcode="' + barcode + '" title="Enviar al cloud">'
                + '<i class="fas fa-cloud-upload-alt fa-xs"></i></button>';
        }
        return '<div style="display:flex;align-items:center;justify-content:center;height:100%;">' + html + '</div>';
    };

    const columnDefs = [
        { field: 'estado_zkong',  headerName: 'Estado',      width: 135, sortable: true, filter: true, cellRenderer: estadoRenderer },
        { field: 'codigo_barras', headerName: 'Cód. barras', width: 145, sortable: true, filter: true },
        { field: 'referencia',    headerName: 'Referencia',  width: 115, sortable: true },
        { field: 'nombre',        headerName: 'Nombre',      flex: 1,    sortable: true, filter: true },
        { field: 'precio_venta',  headerName: 'PVP',         width: 105, sortable: true, cellRenderer: numberFmt },
        { field: 'precio_oferta', headerName: 'Oferta',      width: 105, cellRenderer: numberFmt },
        { field: 'precio_tarifa', headerName: 'Tarifa',      width: 105, cellRenderer: numberFmt },
        { field: 'precio_frio',   headerName: 'Frío',        width: 95,  cellRenderer: numberFmt },
        { field: 'precio_unidad', headerName: 'P.unidad',    width: 105, cellRenderer: numberFmt },
        { field: 'tipo_unidad',   headerName: 'Unidad',      width: 85 },
        { field: 'cant_caja',     headerName: 'Cant/caja',   width: 95 },
        {
            headerName: '',
            width: 95,
            sortable: false,
            filter: false,
            resizable: false,
            pinned: 'right',
            lockPinned: true,
            suppressMovable: true,
            cellStyle: { padding: '0' },
            cellRenderer: accionesRenderer,
        },
    ];

    const gridOptions = {
        columnDefs: columnDefs,
        rowData: [],
        defaultColDef: { resizable: true },
        pagination: true,
        paginationPageSize: 100,
    };

    function actualizarContadores(datos) {
        const nPendientes = datos.filter(function (d) { return d.estado_zkong !== 'sincronizado'; }).length;
        $('#badge-total').text(datos.length + ' productos');
        if (nPendientes > 0) {
            $('#badge-pendientes').text(nPendientes + ' pendiente' + (nPendientes !== 1 ? 's' : '')).show();
            $('#lbl-sync-btn').text('Enviar ' + nPendientes + ' al cloud');
            $('#btn-sync-pendientes').show();
        } else {
            $('#badge-pendientes').hide();
            $('#btn-sync-pendientes').hide();
        }
    }

    function cargar() {
        $.ajax({
            url: BASE_URL + '/productos-sincronizados',
            method: 'GET',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function (datos) {
                todosDatos = datos;
                actualizarContadores(datos);
                if (gridApi) {
                    gridApi.setGridOption('rowData', datos);
                } else {
                    gridApi = agGrid.createGrid(document.getElementById('tabla-productos'), gridOptions);
                    gridApi.setGridOption('rowData', datos);
                }
            },
            error: function () {
                Swal.fire('Error', 'No se pudieron cargar los productos.', 'error');
            }
        });
    }

    $('#input-buscar').on('input', function () {
        if (!gridApi) return;
        var texto = $(this).val().toLowerCase();
        var filtrados = todosDatos.filter(function (fila) {
            return !texto
                || (fila.nombre        || '').toLowerCase().includes(texto)
                || (fila.codigo_barras || '').toLowerCase().includes(texto)
                || (fila.referencia    || '').toLowerCase().includes(texto);
        });
        gridApi.setGridOption('rowData', filtrados);
    });

    $('#btn-recargar').on('click', cargar);

    document.getElementById('tabla-productos').addEventListener('click', function (e) {
        var editBtn = e.target.closest('.btn-editar-fila');
        if (editBtn) {
            var fila = todosDatos.find(function (d) { return d.codigo_barras === editBtn.dataset.barcode; });
            if (fila) abrirModal(fila);
            return;
        }
        var syncBtn = e.target.closest('.btn-sync-fila');
        if (syncBtn && !syncBtn.disabled) sincronizarFila(syncBtn);
    });

    function abrirModal(fila) {
        codigoActual = fila.codigo_barras;
        $('#lbl-codigo-barras').text(fila.codigo_barras);
        $('#fld-referencia').val(fila.referencia    || '');
        $('#fld-nombre').val(fila.nombre             || '');
        $('#fld-pvp').val(fila.precio_venta          != null ? fila.precio_venta          : '');
        $('#fld-oferta').val(fila.precio_oferta      != null ? fila.precio_oferta         : '');
        $('#fld-tarifa').val(fila.precio_tarifa      != null ? fila.precio_tarifa         : '');
        $('#fld-frio').val(fila.precio_frio          != null ? fila.precio_frio           : '');
        $('#fld-precio-unidad').val(fila.precio_unidad != null ? fila.precio_unidad       : '');
        $('#fld-tipo-unidad').val(fila.tipo_unidad   || '');
        $('#fld-cant-caja').val(fila.cant_caja       != null ? fila.cant_caja             : '');
        $('#btn-guardar').prop('disabled', false);
        $('#modal-editar').modal('show');
    }

    $('#btn-guardar').on('click', function () {
        $('#btn-guardar').prop('disabled', true);
        $.ajax({
            url: BASE_URL + '/productos/editar',
            method: 'POST',
            data: {
                codigo_barras: codigoActual,
                referencia:    $('#fld-referencia').val(),
                nombre:        $('#fld-nombre').val(),
                precio_venta:  $('#fld-pvp').val(),
                precio_oferta: $('#fld-oferta').val(),
                precio_tarifa: $('#fld-tarifa').val(),
                precio_frio:   $('#fld-frio').val(),
                precio_unidad: $('#fld-precio-unidad').val(),
                tipo_unidad:   $('#fld-tipo-unidad').val(),
                cant_caja:     $('#fld-cant-caja').val(),
            },
            dataType: 'json',
            success: function (resp) {
                $('#modal-editar').modal('hide');
                cargar();
                Swal.fire({ icon: 'success', title: 'Guardado', text: resp.message, timer: 1800, showConfirmButton: false });
            },
            error: function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Error desconocido.';
                Swal.fire('Error', msg, 'error');
            },
            complete: function () { $('#btn-guardar').prop('disabled', false); }
        });
    });

    function sincronizarFila(btn) {
        var barcode      = btn.dataset.barcode;
        var htmlOriginal = btn.innerHTML;
        btn.disabled  = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin fa-xs"></i>';
        $.ajax({
            url: BASE_URL + '/productos/sincronizar-uno',
            method: 'POST',
            data: { codigo_barras: barcode },
            dataType: 'json',
            success: function (resp) {
                cargar();
                Swal.fire({ icon: 'success', title: 'Enviado al cloud', text: resp.message, timer: 2000, showConfirmButton: false });
            },
            error: function (xhr) {
                btn.disabled  = false;
                btn.innerHTML = htmlOriginal;
                var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Error al enviar al cloud.';
                Swal.fire('Error', msg, 'error');
            }
        });
    }

    $('#btn-sync-pendientes').on('click', function () {
        Swal.fire({
            title:             '¿Enviar al cloud?',
            text:              'Se enviarán al cloud de ZKONG todos los productos pendientes.',
            icon:              'question',
            showCancelButton:  true,
            confirmButtonText: 'Sí, enviar',
            cancelButtonText:  'Cancelar',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $('#btn-sync-pendientes').prop('disabled', true);
            $.ajax({
                url:      BASE_URL + '/productos/sincronizar-pendientes',
                method:   'POST',
                dataType: 'json',
                success: function (resp) {
                    cargar();
                    Swal.fire({
                        icon:             'success',
                        title:            'Listo',
                        text:             resp.message,
                        timer:            2500,
                        showConfirmButton: false,
                    });
                },
                error: function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Error al sincronizar.';
                    Swal.fire('Error', msg, 'error');
                },
                complete: function () {
                    $('#btn-sync-pendientes').prop('disabled', false);
                }
            });
        });
    });

    function formatCsv(v, decimales) {
        decimales = decimales || 2;
        return typeof v === 'number' ? v.toFixed(decimales).replace('.', ',') : '';
    }

    $('#btn-exportar-csv').on('click', function () {
        if (!gridApi || gridApi.getDisplayedRowCount() === 0) {
            Swal.fire('Sin datos', 'No hay productos para exportar.', 'info');
            return;
        }

        var filas = [['ref', 'idArt', 'nombre', 'pvp', 'cantCaja', 'precioUnidad', 'tipoUnidad', 'pvpOferta', 'pvpTarifa', 'pvpFrio'].join(';')];

        gridApi.forEachNodeAfterFilterAndSort(function (node) {
            var r = node.data;
            filas.push([
                r.referencia    || '',
                r.codigo_barras || '',
                r.nombre        || '',
                formatCsv(r.precio_venta),
                r.cant_caja     != null ? r.cant_caja : '',
                formatCsv(r.precio_unidad, 4),
                r.tipo_unidad   || '',
                r.precio_oferta != null ? formatCsv(r.precio_oferta) : '',
                r.precio_tarifa != null ? formatCsv(r.precio_tarifa) : '',
                r.precio_frio   != null ? formatCsv(r.precio_frio)   : '',
            ].join(';'));
        });

        var csv  = '﻿' + filas.join('\r\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        var link = document.createElement('a');
        link.href     = URL.createObjectURL(blob);
        link.download = 'productos.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
    });

    cargar();
});
</script>
