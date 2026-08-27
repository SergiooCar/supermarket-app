<?php
/**
 *
 * Vista: Importación diferencial de productos CSV.
 *
 * Flujo en dos pasos:
 *  1. Subida del CSV → el servidor calcula las diferencias (nuevos,
 *     modificados, eliminados) y las muestra en un AgGrid de revisión.
 *  2. El usuario confirma → se aplican los cambios en BD y se sincronizan
 *     con ZKONG (ok_bd / ok_zkong se reportan por separado).
 *
 * También muestra el historial de importaciones anteriores con opción de
 * reintentar la sincronización ZKONG de las que fallaron.
 */
?>
<div class="row">
    <div class="col-lg-6 col-md-8 col-12">
        <div class="card">
            <div class="card-body">
                <form id="form-importar">
                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-file-csv"></i></span>
                            </div>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="input-csv" accept=".csv" required>
                                <label class="custom-file-label" for="input-csv">Seleccionar fichero CSV...</label>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" id="btn-subir">
                        <i class="fas fa-search"></i> Analizar diferencias
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="seccion-diferencias" style="display:none;">
    <div class="row mb-2">
        <div class="col-12">
            <div class="d-flex align-items-center flex-wrap mb-2">
                <span class="font-weight-bold">Revisar cambios detectados</span>
            </div>
            <div id="resumen-badges" class="mb-2"></div>
            <hr class="mt-0 mb-2">
            <div class="d-flex align-items-center flex-wrap">
                <input type="text" id="input-buscar" class="form-control form-control-sm mr-2 mb-1"
                       placeholder="Buscar producto..." style="width:200px;">
                <select id="filtro-estado" class="form-control form-control-sm mr-2 mb-1" style="width:160px;">
                    <option value="">Todos los estados</option>
                    <option value="nuevo">Nuevos</option>
                    <option value="modificado">Modificados</option>
                    <option value="eliminado">Eliminados</option>
                </select>
                <button id="btn-confirmar" class="btn btn-success mb-1" disabled>
                    <i class="fas fa-check"></i> Confirmar importación
                </button>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div id="tabla-diferencias" class="ag-theme-alpine" style="height:480px;"></div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card" id="card-historial">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Historial de importaciones</h3>
                <button class="btn btn-sm btn-secondary" id="btn-recargar-historial">
                    <i class="fas fa-sync"></i>
                </button>
            </div>
            <div class="card-body p-0">
                <div id="tabla-historial" class="ag-theme-alpine" style="height:300px;"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    let gridDiferencias = null;
    let resumen         = null;
    let todosDatos      = [];

    const formatPrecio = v => (v !== null && v !== undefined) ? Number(v).toFixed(2) + ' €' : '—';

    const cambiaCelda = (vn, va, estado) =>
        estado === 'modificado' && va !== null && va !== undefined &&
        String(Number(vn).toFixed(4)) !== String(Number(va).toFixed(4));

    const estiloMod = { backgroundColor: '#ffe082', fontWeight: 'bold' };

    const columnDefs = [
        {
            field: 'estado', headerName: 'Estado', width: 130, filter: true,
            cellStyle: p => {
                const c = { nuevo: '#d4edda', modificado: '#fff3cd', eliminado: '#f8d7da' };
                const base = { display: 'flex', alignItems: 'center', justifyContent: 'center' };
                return c[p.value] ? { ...base, background: c[p.value] } : base;
            },
            cellRenderer: p => {
                const cfg = { nuevo: ['success','Nuevo'], modificado: ['warning','Modificado'], eliminado: ['danger','Eliminado'] };
                const [cls, txt] = cfg[p.value] ?? ['secondary', p.value];
                return `<span class="badge badge-${cls}" style="font-size:0.85rem;padding:0.4em 0.7em;">${txt}</span>`;
            }
        },
        { field: 'codigoBarras', headerName: 'Código', width: 120, sortable: true, filter: true, cellStyle: { textAlign: 'center' } },
        {
            field: 'nombre', headerName: 'Nombre', flex: 1, sortable: true, filter: true,
            cellStyle: p => cambiaCelda(p.data.nombre, p.data.nombreAnterior, p.data.estado) ? estiloMod : null,
            tooltipValueGetter: p => p.data.nombreAnterior ? 'Antes: ' + p.data.nombreAnterior : null,
        },
        {
            field: 'precioVenta', headerName: 'Precio venta', width: 130,
            valueFormatter: p => formatPrecio(p.value),
            cellStyle: p => cambiaCelda(p.data.precioVenta, p.data.precioVentaAnterior, p.data.estado) ? estiloMod : null,
            tooltipValueGetter: p => p.data.precioVentaAnterior !== null && p.data.precioVentaAnterior !== undefined
                ? 'Antes: ' + formatPrecio(p.data.precioVentaAnterior) : null,
        },
        {
            field: 'precioOferta', headerName: 'Precio oferta', width: 130,
            valueFormatter: p => formatPrecio(p.value),
            cellStyle: p => cambiaCelda(p.data.precioOferta, p.data.precioOfertaAnterior, p.data.estado) ? estiloMod : null,
        },
        {
            field: 'precioUnidad', headerName: 'Precio/unidad', width: 130,
            valueFormatter: p => formatPrecio(p.value),
            cellStyle: p => cambiaCelda(p.data.precioUnidad, p.data.precioUnidadAnterior, p.data.estado) ? estiloMod : null,
        },
        { field: 'tipoUnidad', headerName: 'Tipo unidad', width: 110 },
        { field: 'cantCaja',   headerName: 'Cant. caja',  width: 100 },
        { field: 'referencia', headerName: 'Referencia',  width: 110 },
    ];

    const gridDifOptions = {
        gridId: "diff-table",
        defaultColDef: { resizable: true },
        columnDefs: columnDefs,
        rowData: [],

        pagination: true,
        paginationPageSize: 50,
        paginationPageSizeSelector: [25, 50, 100, 200],
        
        tooltipShowDelay: 300,
        getRowStyle: p => {
            const c = { nuevo: '#82d082', modificado: '#fff3cd', eliminado: '#d08282' };
            if (!c[p.data.estado]) return null;
            const extra = p.data.estado !== 'modificado' ? { fontWeight: 'bold' } : {};
            return { background: c[p.data.estado], ...extra };
        },
    };

function inicializarGridDiferencias(datos) {
    todosDatos = datos;
    if (!gridDiferencias) {
        gridDiferencias = agGrid.createGrid(
            document.getElementById('tabla-diferencias'),
            gridDifOptions
        );
    }
    gridDiferencias.setGridOption('rowData', datos);
}

    function aplicarFiltros() {
        if (!gridDiferencias) return;
        const texto  = $('#input-buscar').val().toLowerCase();
        const estado = $('#filtro-estado').val();

        const filtrados = todosDatos.filter(f => {
            // 1. Filtrar por estado.
            const coincideEstado = estado === "" || f.estado === estado;

            // 2. Filtrar por texto.
            const coincideTexto = 
                texto === "" ||
                (f.nombre ?? "").toLowerCase().includes(texto) ||
                (f.codigoBarras ?? "").toLowerCase().includes(texto);

            return coincideEstado && coincideTexto;
        });

        gridDiferencias.setGridOption('rowData', filtrados)

        /* gridDiferencias.setGridOption('rowData', todosDatos.filter(f =>
            (!estado || f.estado === estado) &&
            (!texto  || (f.nombre ?? '').toLowerCase().includes(texto) || (f.codigoBarras ?? '').toLowerCase().includes(texto))
        )); */
    }

    $('#input-buscar').on('input', aplicarFiltros);
    $('#filtro-estado').on('change', aplicarFiltros);

    $('#input-csv').on('change', function () {
        $(this).siblings('.custom-file-label').text(this.files[0]?.name ?? 'Seleccionar fichero CSV...');
    });

    $('#form-importar').on('submit', function (e) {
        e.preventDefault();
        const archivo = $('#input-csv')[0].files[0];
        if (!archivo) { Swal.fire('Error', 'Selecciona un fichero CSV.', 'error'); return; }

        Swal.fire({ title: 'Analizando...', text: 'Comparando con la base de datos.',
            allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });

        const fd = new FormData();
        fd.append('csv', archivo);

        $.ajax({
            url: BASE_URL + '/importar/comparar', 
            method: 'POST',
            data: fd, 
            processData: false, 
            contentType: false, 
            dataType: 'json',
            success: function (r) {
                Swal.close();
                resumen = r.resumen;
                $('#resumen-badges').html(
                    `<span class="badge badge-success mr-2" style="font-size:0.9rem;padding:0.4em 0.75em;">Nuevos: ${resumen.nuevos}</span>
                     <span class="badge badge-warning mr-2" style="font-size:0.9rem;padding:0.4em 0.75em;">Modificados: ${resumen.modificados}</span>
                     <span class="badge badge-danger" style="font-size:0.9rem;padding:0.4em 0.75em;">Eliminados: ${resumen.eliminados}</span>`
                );
                inicializarGridDiferencias(r.diferencias);
                $('#seccion-diferencias').show();
                $('#card-historial').hide();
                const total = resumen.nuevos + resumen.modificados + resumen.eliminados;
                $('#btn-confirmar').prop('disabled', total === 0);
                if (total === 0) {
                    Swal.fire({ icon: 'info', title: 'Sin cambios', text: 'El CSV no tiene diferencias respecto a la base de datos.' });
                }
            },
            error: function (xhr) {
                Swal.fire({ icon: 'error', title: 'Error en el análisis',
                    text: xhr.responseJSON?.error ?? 'Error al procesar el fichero.' });
            }
        });
    });

    const FASES = [
        'Actualizando base de datos local...',
        'Conectando con el cloud ZKONG...',
        'Enviando productos nuevos y modificados...',
        'Eliminando productos del cloud...',
        'Forzando refresco de etiquetas...',
    ];

    $('#btn-confirmar').on('click', function () {
        Swal.fire({
            icon: 'question',
            title: '¿Confirmar importación?',
            html: `Se van a <strong>crear ${resumen.nuevos}</strong> productos,
                   <strong>modificar ${resumen.modificados}</strong>
                   y <strong>eliminar ${resumen.eliminados}</strong> del cloud.`,
            showCancelButton: true,
            confirmButtonText: 'Confirmar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#28a745',
        }).then(result => {
            if (!result.isConfirmed) return;

            let faseIdx = 0;
            Swal.fire({
                title: 'Procesando importación',
                html: '<span id="fase-actual">' + FASES[0] + '</span>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => Swal.showLoading()
            });

            const intervalo = setInterval(() => {
                faseIdx = Math.min(faseIdx + 1, FASES.length - 1);
                const el = document.getElementById('fase-actual');
                if (el) el.textContent = FASES[faseIdx];
            }, 2500);

            $.ajax({
                url: BASE_URL + '/importar/confirmar', method: 'POST', dataType: 'json',
                success: function (r) {
                    clearInterval(intervalo);
                    if (r.ok_zkong) {
                        Swal.fire({ icon: 'success', title: '¡Completado!', text: r.message });
                    } else if (r.ok_bd && !r.ok_zkong) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'BD actualizada · Cloud con errores',
                            html: r.message + '<br><small class="text-muted">' + (r.detalle_error ?? '') + '</small><br>Puedes reintentar desde el historial.',
                        });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: r.error ?? r.message ?? 'Error desconocido.' });
                    }
                    $('#seccion-diferencias').hide();
                    $('#card-historial').show();
                    $('#form-importar')[0].reset();
                    $('.custom-file-label').text('Seleccionar fichero CSV...');
                    todosDatos = [];
                    if (gridDiferencias) { gridDiferencias.setGridOption('rowData', []); }
                    cargarHistorial();
                },
                error: function (xhr) {
                    clearInterval(intervalo);
                    $('#card-historial').show();
                    Swal.fire({ icon: 'error', title: 'Error en la confirmación',
                        text: xhr.responseJSON?.error ?? 'Error al aplicar los cambios.' });
                }
            });
        });
    });

    // ── Historial ──────────────────────────────────────────────────────────
    const colDefsHistorial = [
        { field: 'id', headerName: 'ID', width: 70 },
        { field: 'nombre_fichero', headerName: 'Fichero', flex: 1 },
        { field: 'nuevos',      headerName: 'Nuevos',     width: 90 },
        { field: 'modificados', headerName: 'Modif.',     width: 90 },
        { field: 'eliminados',  headerName: 'Elim.',      width: 90 },
        {
            field: 'estado', headerName: 'BD', width: 110,
            cellRenderer: p => {
                const cls = p.value === 'completada' ? 'success' : 'danger';
                return `<span class="badge badge-${cls}">${p.value}</span>`;
            }
        },
        {
            field: 'estado_zkong', headerName: 'ZKONG', width: 140,
            cellRenderer: p => {
                if (p.value === 'sincronizado') return '<span class="badge badge-success">Sincronizado</span>';
                if (p.value === 'fallido') return `<span class="badge badge-danger mr-1">Fallido</span>
                    <button class="btn btn-xs btn-danger btn-reintentar" data-id="${p.data.id}">Reintentar</button>`;
                return '<span class="badge badge-warning">Pendiente</span>';
            }
        },
        { field: 'creado_en', headerName: 'Fecha', width: 160 },
    ];

    let gridHistorial = null;

    function cargarHistorial() {
        $.ajax({
            url: BASE_URL + '/importar/historial', 
            method: 'GET', 
            dataType: 'json',
            success: function (datos) {
                datos = datos ?? [];
                if (!gridHistorial) {
                    gridHistorial = agGrid.createGrid(
                        document.getElementById('tabla-historial'), 
                        { 
                            columnDefs: colDefsHistorial, 
                            rowData: datos, 
                            defaultColDef: { resizable: true } 
                        }
                    );
                }
                gridHistorial.setGridOption('rowData', datos);
            }
        });
    }

    document.getElementById('tabla-historial').addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-reintentar');
        if (!btn) return;
        const id = btn.dataset.id;

        Swal.fire({
            icon: 'question', title: '¿Reintentar sincronización?',
            text: 'Se enviará el catálogo completo actual al cloud ZKONG.',
            showCancelButton: true, confirmButtonText: 'Reintentar', cancelButtonText: 'Cancelar',
        }).then(r => {
            if (!r.isConfirmed) return;
            Swal.fire({ title: 'Reintentando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            $.ajax({
                url: BASE_URL + '/importar/reintentar', method: 'POST',
                data: { id }, dataType: 'json',
                success: function (res) {
                    Swal.fire({ icon: 'success', title: 'Sincronización completada', text: res.message });
                    cargarHistorial();
                },
                error: function (xhr) {
                    Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.error ?? 'Error en el reintento.' });
                    cargarHistorial();
                }
            });
        });
    });

    $('#btn-recargar-historial').on('click', cargarHistorial);
    cargarHistorial();
});
</script>
