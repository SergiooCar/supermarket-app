<!DOCTYPE html>
<?php
/**
 *
 * Vista: Error 403 — Acceso denegado.
 *
 * Página autónoma (sin layout) mostrada por Middleware cuando el usuario
 * no tiene el rol requerido para acceder a la ruta solicitada.
 */
?>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 | Supermarket App</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
</head>
<body class="hold-transition">
<div class="wrapper">
    <div class="content-wrapper">
        <section class="content">
            <div class="error-page mt-5">
                <h2 class="headline text-danger">403</h2>
                <div class="error-content">
                    <h3><i class="fas fa-ban text-danger"></i> Acceso denegado</h3>
                    <p>No tienes permisos para acceder a esta página.</p>
                    <a href="<?= BASE_URL ?>/dashboard" class="btn btn-primary">Volver al dashboard</a>
                </div>
            </div>
        </section>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>
</body>
</html>
