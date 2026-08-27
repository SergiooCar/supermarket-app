<?php
/**
 *
 * Vista: Gestión de usuarios del sistema (solo admin).
 *
 * AgGrid con CRUD completo: crear usuario (nombre, email, contraseña, rol),
 * editar nombre/email/rol, activar/desactivar cuenta y eliminar. Las acciones
 * se realizan mediante peticiones AJAX a AdminControlador con confirmación
 * SweetAlert2 para las operaciones destructivas.
 */
?>
<div class="row">
    <div class="col-12">
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title" id="form-titulo">Nuevo usuario</h3>
            </div>
            <div class="card-body">
                <input type="hidden" id="form-id" value="0">
                <div class="form-row align-items-end">
                    <div class="form-group col-md-3 mb-0">
                        <label>Nombre</label>
                        <input type="text" id="form-nombre" class="form-control" placeholder="Nombre completo">
                    </div>
                    <div class="form-group col-md-3 mb-0">
                        <label>Email</label>
                        <input type="email" id="form-email" class="form-control" placeholder="correo@ejemplo.com">
                    </div>
                    <div class="form-group col-md-2 mb-0" id="grupo-password">
                        <label>Contraseña</label>
                        <input type="password" id="form-password" class="form-control" placeholder="Mínimo 6 caracteres">
                    </div>
                    <div class="form-group col-md-2 mb-0">
                        <label>Rol</label>
                        <select id="form-rol" class="form-control">
                            <option value="user">Usuario</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    <div class="form-group col-md-2 mb-0 d-flex">
                        <button id="btn-guardar" class="btn btn-primary mr-1 flex-fill">
                            <i class="fas fa-save mr-1"></i>Guardar
                        </button>
                        <button id="btn-cancelar" class="btn btn-secondary" style="display:none" title="Cancelar edición">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">Usuarios del sistema</h3>
                <span id="badge-total" class="badge badge-secondary" style="font-size:0.9rem;"></span>
            </div>
            <div class="card-body p-0">
                <div id="tabla-usuarios" class="ag-theme-alpine" style="height:500px;"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let gridApi = null;

    const badgeActivo = v => `<span class="badge badge-${v ? 'success' : 'secondary'}">${v ? 'Activo' : 'Inactivo'}</span>`;
    const badgeRol    = v => `<span class="badge badge-${v === 'admin' ? 'danger' : 'info'}">${v}</span>`;

    const cols = [
        { field: 'id',     headerName: 'ID',     width: 70 },
        { field: 'nombre', headerName: 'Nombre',  flex: 1, filter: true },
        { field: 'email',  headerName: 'Email',   flex: 1, filter: true },
        { field: 'rol',    headerName: 'Rol',     width: 110, cellRenderer: p => badgeRol(p.value) },
        { field: 'activo', headerName: 'Estado',  width: 110, cellRenderer: p => badgeActivo(p.value) },
        {
            headerName: 'Acciones', width: 280, sortable: false, filter: false,
            cellRenderer: p => `
                <button class="btn btn-xs btn-primary btn-editar mr-1"
                        data-id="${p.data.id}" data-nombre="${p.data.nombre}"
                        data-email="${p.data.email}" data-rol="${p.data.rol}">
                    <i class="fas fa-edit"></i> Editar
                </button>
                <button class="btn btn-xs btn-warning btn-toggle mr-1"
                        data-id="${p.data.id}" data-activo="${p.data.activo ? '1' : '0'}">
                    <i class="fas fa-power-off"></i> ${p.data.activo ? 'Desact.' : 'Activ.'}
                </button>
                <button class="btn btn-xs btn-danger btn-eliminar"
                        data-id="${p.data.id}" data-nombre="${p.data.nombre}">
                    <i class="fas fa-trash"></i> Eliminar
                </button>`,
        },
    ];

    function cargar() {
        $.ajax({
            url: BASE_URL + '/admin/usuarios', 
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            dataType: 'json',
            success: function (datos) {
                $('#badge-total').text(datos.length + ' usuarios');
                if (!gridApi) {
                    gridApi = agGrid.createGrid(
                        document.getElementById('tabla-emparejamientos'),
                        {
                            cols,
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

    function modoNuevo() {
        $('#form-titulo').text('Nuevo usuario');
        $('#form-id').val('0');
        $('#form-nombre, #form-email, #form-password').val('');
        $('#form-rol').val('user');
        $('#grupo-password label').text('Contraseña');
        $('#form-password').attr('placeholder', 'Mínimo 6 caracteres');
        $('#grupo-password').show();
        $('#btn-cancelar').hide();
    }

    function modoEditar(datos) {
        $('#form-titulo').text('Editando: ' + datos.nombre);
        $('#form-id').val(datos.id);
        $('#form-nombre').val(datos.nombre);
        $('#form-email').val(datos.email);
        $('#form-rol').val(datos.rol);
        $('#form-password').val('');
        $('#grupo-password label').text('Nueva contraseña');
        $('#form-password').attr('placeholder', 'Dejar vacío para mantener la actual');
        $('#grupo-password').show();
        $('#btn-cancelar').show();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    $('#btn-cancelar').on('click', modoNuevo);

    $('#btn-guardar').on('click', function () {
        const id        = parseInt($('#form-id').val()) || 0;
        const nombre    = $('#form-nombre').val().trim();
        const email     = $('#form-email').val().trim();
        const password  = $('#form-password').val().trim();
        const rol       = $('#form-rol').val();
        const esEdicion = id > 0;

        if (!nombre || !email) {
            Swal.fire('Campos obligatorios', 'Nombre y email son obligatorios.', 'warning'); return;
        }
        if (!esEdicion && !password) {
            Swal.fire('Contraseña requerida', 'Introduce una contraseña para el nuevo usuario.', 'warning'); return;
        }

        const url  = esEdicion ? BASE_URL + '/admin/usuarios/editar' : BASE_URL + '/admin/usuarios/crear';
        const data = esEdicion ? { id, nombre, email, rol, password: password || undefined } : { nombre, email, password, rol };

        $.ajax({
            url, method: 'POST', contentType: 'application/json',
            data: JSON.stringify(data), dataType: 'json',
            success: function (r) {
                if (r.ok) {
                    if (r.self && r.nombre) {
                        $('#topbar-nombre').text(r.nombre);
                    }
                    Swal.fire({ icon: 'success', title: esEdicion ? 'Actualizado' : 'Creado', timer: 1500, showConfirmButton: false });
                    modoNuevo();
                    cargar();
                } else {
                    Swal.fire('Error', r.error, 'error');
                }
            },
            error: function (xhr) {
                var msg = 'Error al guardar.';
                try {
                    var parsed = JSON.parse(xhr.responseText);
                    if (parsed && parsed.error) msg = parsed.error;
                } catch (ex) {
                    if (xhr.responseText) msg = xhr.responseText.substring(0, 300);
                }
                Swal.fire('Error al crear usuario', msg, 'error');
            }
        });
    });

    document.getElementById('tabla-usuarios').addEventListener('click', function (e) {
        const btnEditar   = e.target.closest('.btn-editar');
        const btnToggle   = e.target.closest('.btn-toggle');
        const btnEliminar = e.target.closest('.btn-eliminar');

        if (btnEditar) {
            modoEditar({
                id:     parseInt(btnEditar.dataset.id),
                nombre: btnEditar.dataset.nombre,
                email:  btnEditar.dataset.email,
                rol:    btnEditar.dataset.rol,
            });
        }

        if (btnToggle) {
            const id     = parseInt(btnToggle.dataset.id);
            const activo = btnToggle.dataset.activo === '1';
            Swal.fire({
                icon: 'question',
                title: activo ? '¿Desactivar usuario?' : '¿Activar usuario?',
                showCancelButton: true, confirmButtonText: 'Sí', cancelButtonText: 'No',
            }).then(r => {
                if (!r.isConfirmed) return;
                $.ajax({
                    url: BASE_URL + '/admin/usuarios/toggle', method: 'POST',
                    contentType: 'application/json', data: JSON.stringify({ id }), dataType: 'json',
                    success: () => cargar(),
                    error: () => Swal.fire('Error', 'No se pudo cambiar el estado.', 'error'),
                });
            });
        }

        if (btnEliminar) {
            const id     = parseInt(btnEliminar.dataset.id);
            const nombre = btnEliminar.dataset.nombre;
            Swal.fire({
                icon: 'warning',
                title: `¿Eliminar a ${nombre}?`,
                text: 'Esta acción no se puede deshacer.',
                showCancelButton: true,
                confirmButtonText: 'Eliminar',
                confirmButtonColor: '#dc3545',
                cancelButtonText: 'Cancelar',
            }).then(r => {
                if (!r.isConfirmed) return;
                $.ajax({
                    url: BASE_URL + '/admin/usuarios/eliminar', method: 'POST',
                    contentType: 'application/json', data: JSON.stringify({ id }), dataType: 'json',
                    success: function (resp) {
                        if (resp.ok) { cargar(); } else { Swal.fire('Error', resp.error, 'error'); }
                    },
                    error: xhr => Swal.fire('Error', xhr.responseJSON?.error ?? 'Error al eliminar.', 'error'),
                });
            });
        }
    });

    cargar();
});
</script>
