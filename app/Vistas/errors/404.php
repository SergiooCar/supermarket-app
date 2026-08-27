<!DOCTYPE html>
<?php
/**
 *
 * Vista: Error 404 — Página no encontrada.
 *
 * Página autónoma (sin layout) mostrada por Router cuando la URI no
 * coincide con ninguna ruta registrada en el mapa de rutas.
 */
?>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 | Supermarket App</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
</head>
<body class="hold-transition">
<div class="wrapper">
    <div class="content-wrapper">
        <section class="content">
            <div class="error-page mt-5">
                <h2 class="headline text-warning">404</h2>
                <div class="error-content">
                    <h3><i class="fas fa-exclamation-triangle text-warning"></i> Página no encontrada</h3>
                    <p>La ruta que buscas no existe.</p>
                    <a href="/dashboard" class="btn btn-primary">Volver al dashboard</a>
                </div>
            </div>
        </section>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>
</body>
</html>
