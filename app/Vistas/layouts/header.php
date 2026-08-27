<!DOCTYPE html>
<?php
/**
 *
 * Vista: Parcial de cabecera HTML.
 *
 * Emite <!DOCTYPE>, <html>, <head> con todos los CSS (Font Awesome, Bootstrap 4,
 * AdminLTE 3, AgGrid 32, dark-mode.css) y la apertura del <body> con la topbar
 * de AdminLTE (botón de menú, toggle dark-mode, dropdown de perfil/logout).
 *
 * El script inline de dark-mode aplica la clase antes del primer renderizado
 * para evitar el flash de tema claro cuando el usuario prefiere oscuro.
 */
?>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($tituloPagina ?? 'Panel', ENT_QUOTES, 'UTF-8') ?> | Supermarket App</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community@32.0.0/styles/ag-grid.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community@32.0.0/styles/ag-theme-alpine.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dark-mode.css">
    <script>(function(){if(localStorage.getItem('darkMode')==='1')document.documentElement.classList.add('dark-mode');}());</script>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto align-items-center">
            <li class="nav-item mr-2">
                <button id="dark-mode-toggle" class="btn btn-sm btn-secondary" title="Modo oscuro">
                    <i class="fas fa-moon"></i>
                </button>
            </li>
            <?php if (!empty($_SESSION['nombre'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link px-3" href="#" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false" title="Perfil">
                        <i class="fas fa-user-cog fa-lg"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow-sm">
                        <div class="px-3 py-2">
                            <div class="d-flex align-items-center">
                                <span id="topbar-nombre" class="font-weight-semibold mr-2"><?= htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="badge badge-<?= $_SESSION['rol'] === 'admin' ? 'danger' : 'info' ?>">
                                    <?= $_SESSION['rol'] === 'admin' ? 'Administrador' : 'Usuario' ?>
                                </span>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <?php if (($_SESSION['rol'] ?? '') === 'admin'): ?>
                            <a href="<?= BASE_URL ?>/admin/usuarios" class="dropdown-item">
                                <i class="fas fa-users-cog mr-2"></i> Gestión de usuarios
                            </a>
                            <div class="dropdown-divider"></div>
                        <?php endif; ?>
                        <form action="<?= BASE_URL ?>/logout" method="POST">
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="fas fa-sign-out-alt mr-2"></i> Cerrar sesión
                            </button>
                        </form>
                    </div>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
