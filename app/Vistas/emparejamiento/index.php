<?php
/**
 *
 * Vista: Emparejamiento de etiquetas ESL.
 *
 * Formulario para vincular un código de barras de artículo con el código
 * físico de una etiqueta ESL. También permite desemparejar etiquetas
 * seleccionadas en el AgGrid y previsualizar la imagen que muestra cada
 * etiqueta (BMP→PNG convertido en el servidor).
 */
?>

<div class="row">
    <div class="col-lg-5 col-md-7 col-12">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Emparejar etiqueta ESL con artículo</h3></div>
            <div class="card-body">
                <div class="form-group">
                    <label for="input-etiqueta">Código de etiqueta ESL</label>
                    <input type="text" id="input-etiqueta" class="form-control"
                           placeholder="Ej. 812B07049" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="input-barcode">Código de barras artículo</label>
                    <input type="text" id="input-barcode" class="form-control"
                           placeholder="Ej. 167834" autocomplete="off">
                </div>
                <button id="btn-emparejar" class="btn btn-primary btn-block">
                    <i class="fas fa-link"></i> Emparejar
                </button>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Emparejamientos vigentes</h3>
                <button class="btn btn-sm btn-secondary" id="btn-recargar">
                    <i class="fas fa-sync"></i> Actualizar
                </button>
            </div>
            <div class="card-body p-0">
                <div id="tabla-emparejamientos" class="ag-theme-alpine" style="height:420px;"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    let gridApi = null;

    const columnDefs = [
        { field: 'codigo_etiqueta',  headerName: 'Etiqueta ESL',   width: 150, filter: true },
        { field: 'barcode_articulo', headerName: 'Cód. barras',    width: 140, filter: true },
        { field: 'nombre_articulo',  headerName: 'Artículo',       flex: 1,    filter: true },

        {
            headerName: 'Acciones', width: 140, sortable: false, filter: false,
            cellRenderer: p => `
                <button class="btn btn-xs btn-danger btn-desemparejar"
                        data-etiqueta="${p.data.codigo_etiqueta}"
                        data-nombre="${p.data.nombre_articulo ?? p.data.barcode_articulo}">
                    <i class="fas fa-unlink"></i> Desemparejar
                </button>`,
        },
    ];

    function cargar() {
        $.ajax({
            url: BASE_URL + '/emparejar/lista', 
            method: 'GET', 
            dataType: 'json',
            success: function (datos) {
                if (!gridApi) {
                    gridApi = agGrid.createGrid(
                        document.getElementById('tabla-emparejamientos'),
                        {
                            columnDefs,
                            rowData: datos,
                            defaultColDef: { resizable: true },
                            pagination: true,
                            paginationPageSize: 50,
                        }
                    );
                }
                gridApi.setGridOption('rowData', datos);
            },
            error: function () {
                Swal.fire('Error', 'No se pudieron cargar los emparejamientos.', 'error');
            }
        });
    }

    $('#btn-emparejar').on('click', function () {
        const codigoEtiqueta  = $('#input-etiqueta').val().trim();
        const barcodeArticulo = $('#input-barcode').val().trim();

        if (!codigoEtiqueta || !barcodeArticulo) {
            Swal.fire('Campos obligatorios', 'Introduce el código de etiqueta y el código de barras del artículo.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Emparejando...',
            text: 'Conectando con el cloud ZKONG.',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        $.ajax({
            url: BASE_URL + '/emparejar',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ codigoEtiqueta, barcodeArticulo }),
            dataType: 'json',
            success: function (r) {
                if (r.ok) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Emparejado!',
                        html: `Etiqueta <strong>${codigoEtiqueta}</strong> vinculada a <strong>${r.nombre ?? barcodeArticulo}</strong>.`,
                    });
                    $('#input-etiqueta').val('');
                    $('#input-barcode').val('');
                    cargar();
                } else {
                    const icono = r.estado === 'precondicion_fallida' ? 'warning' : 'error';
                    Swal.fire({ icon: icono, title: 'No se pudo emparejar', text: r.detalle_error });
                }
            },
            error: function (xhr) {
                const msg = xhr.responseJSON?.detalle_error ?? 'Error al emparejar.';
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
            }
        });
    });

    document.getElementById('tabla-emparejamientos').addEventListener('click', function (e) {
        const btnDesemparejar = e.target.closest('.btn-desemparejar');

        if (btnDesemparejar) {
            const codigoEtiqueta = btnDesemparejar.dataset.etiqueta;
            const nombre         = btnDesemparejar.dataset.nombre;

            Swal.fire({
                icon: 'question',
                title: '¿Desemparejar?',
                html: `Se eliminará el vínculo de la etiqueta <strong>${codigoEtiqueta}</strong> (${nombre}).`,
                showCancelButton: true,
                confirmButtonText: 'Desemparejar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
            }).then(res => {
                if (!res.isConfirmed) return;

                Swal.fire({ title: 'Desemparejando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

                $.ajax({
                    url: BASE_URL + '/desemparejar',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ codigoEtiqueta }),
                    dataType: 'json',
                    success: function (r) {
                        if (r.ok) {
                            Swal.fire({ icon: 'success', title: 'Desemparejado', text: `Etiqueta ${codigoEtiqueta} desvinculada.` });
                            cargar();
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: r.detalle_error });
                        }
                    },
                    error: function (xhr) {
                        Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.detalle_error ?? 'Error al desemparejar.' });
                    }
                });
            });
        }
    });

    $('#btn-recargar').on('click', cargar);
    cargar();
});
</script>
