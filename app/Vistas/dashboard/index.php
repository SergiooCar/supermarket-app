<?php
/**
 *
 * Vista: Dashboard — panel principal.
 *
 * Muestra el estado de la conexión ZKONG (token en sesión, storeId, últimos
 * errores) y métricas básicas del sistema. Los datos llegan desde
 * DashboardControlador::datos() vía petición AJAX al cargar la página.
 */
?>
<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-<?= $zkongToken ? 'success' : 'danger' ?>">
            <div class="inner">
                <h3><?= $zkongToken ? 'Conectado' : 'Desconectado' ?></h3>
                <p>Estado ZKONG</p>
            </div>
            <div class="icon"><i class="fas fa-cloud"></i></div>
            <a href="<?= BASE_URL ?>/admin/zkong/health" class="small-box-footer" target="_blank">
                JSON - Diagnosticar <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-<?= $totalLogs === 0 ? 'success' : (empty($ultimosErrores) ? 'info' : 'warning') ?>">
            <div class="inner">
                <h3><?= $totalLogs ?></h3>
                <p>Peticiones API</p>
            </div>
            <div class="icon"><i class="fas fa-exchange-alt"></i></div>
            <a href="<?= BASE_URL ?>/admin/logs-zkong" class="small-box-footer">
                Ver logs <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?= htmlspecialchars($zkongStoreId ?? '—') ?></h3>
                <p>Store ID</p>
            </div>
            <div class="icon"><i class="fas fa-store"></i></div>
            <span class="small-box-footer" style="visibility:hidden;pointer-events:none;">&nbsp;</span>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-<?= empty($ultimosErrores) ? 'success' : 'danger' ?>">
            <div class="inner">
                <h3><?= count($ultimosErrores) ?></h3>
                <p>Últimos errores</p>
            </div>
            <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
            <span class="small-box-footer" style="visibility:hidden;pointer-events:none;">&nbsp;</span>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Estado de la integración ZKONG</h3>
                <a href="<?= rtrim($_ENV['ZKONG_BASE_URL'] ?? 'https://etiquetas.ausiasmarch.net', '/') ?>/#/login"
                   target="_blank" rel="noopener" class="btn btn-sm btn-primary">
                    <i class="fas fa-cloud"></i> Abrir ZKONG Cloud
                </a>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th>API URL</th>
                        <td><code><?= htmlspecialchars($_ENV['ZKONG_BASE_URL'] ?? '—') ?></code></td>
                    </tr>
                    <tr>
                        <th>Modo autenticación</th>
                        <td><code><?= htmlspecialchars($_ENV['ZKONG_AUTH_MODE'] ?? 'token') ?></code></td>
                    </tr>
                    <tr>
                        <th>Token activo</th>
                        <td><?= $zkongToken ? '<span class="badge badge-success">Válido</span>' : '<span class="badge badge-danger">No hay token</span>' ?></td>
                    </tr>
                    <tr>
                        <th>Store ID</th>
                        <td><code><?= htmlspecialchars($zkongStoreId ?? 'No asignado') ?></code></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($ultimosErrores)): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Últimos errores ZKONG</h3></div>
            <div class="card-body p-0">
                <table class="table table-striped">
                    <thead>
                        <tr><th>Endpoint</th><th>Código</th><th>Mensaje</th><th>Duración</th><th>Fecha</th></tr>
                    </thead>
                    <tbody>
<?php foreach ($ultimosErrores as $e): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($e['endpoint']) ?></code></td>
                            <td><?= (int) $e['codigo_respuesta'] ?></td>
                            <td><?= htmlspecialchars(mb_substr($e['mensaje_respuesta'] ?? '', 0, 80)) ?></td>
                            <td><?= (int) $e['duracion_ms'] ?> ms</td>
                            <td><?= htmlspecialchars($e['creado_en']) ?></td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
