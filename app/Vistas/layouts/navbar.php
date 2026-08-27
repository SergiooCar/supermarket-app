<?php
/**
 *
 * Vista: Sidebar de navegación lateral (AdminLTE).
 *
 * Renderiza el <aside> con el menú de rutas. El menú de Administración
 * (Importar CSV, Importaciones, Logs ZKONG) solo se muestra a usuarios
 * con rol 'admin'. El enlace activo se resalta comparando $paginaActual
 * con el identificador de cada ruta.
 */
$pagina = $paginaActual ?? '';
$esAdmin = ($_SESSION['rol'] ?? '') === 'admin';
$adminActivo = in_array($pagina, ['admin-importaciones','logs-zkong','importar'], true);
?>
<aside class="main-sidebar sidebar-dark-primary elevation-4">

    <a href="<?= BASE_URL ?>/dashboard" class="brand-link">
        <i class="fas fa-store brand-image img-circle elevation-3 ml-3 mr-2" style="font-size:1.5rem; line-height:1.5rem;"></i>
        <span class="brand-text font-weight-light">Supermarket App</span>
    </a>

    <div class="sidebar">
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">

                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/dashboard" class="nav-link <?= $pagina === 'dashboard' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/productos-sincronizados" class="nav-link <?= $pagina === 'productos-sincronizados' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-check-circle"></i>
                        <p>Productos</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/emparejar" class="nav-link <?= $pagina === 'emparejar' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-tag"></i>
                        <p>Emparejar etiquetas</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/etiquetas" class="nav-link <?= $pagina === 'etiquetas' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-wifi"></i>
                        <p>Estado etiquetas</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/antenas" class="nav-link <?= $pagina === 'antenas' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-broadcast-tower"></i>
                        <p>Estado antenas</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/zkong-cloud" class="nav-link <?= $pagina === 'zkong-cloud' ? 'active' : '' ?>">
                        <i class="fas fa-cloud nav-icon"></i>
                        <p>ZKONG Cloud</p>
                    </a>
                </li>

                <?php if ($esAdmin): ?>
                <li class="nav-item has-treeview <?= $adminActivo ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= $adminActivo ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-users-cog"></i>
                        <p>Administración<i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?= BASE_URL ?>/importar" class="nav-link <?= $pagina === 'importar' ? 'active' : '' ?>">
                                <i class="far fa-circle nav-icon"></i><p>Importar CSV</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= BASE_URL ?>/admin/importaciones" class="nav-link <?= $pagina === 'admin-importaciones' ? 'active' : '' ?>">
                                <i class="far fa-circle nav-icon"></i><p>Importaciones</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= BASE_URL ?>/admin/logs-zkong" class="nav-link <?= $pagina === 'logs-zkong' ? 'active' : '' ?>">
                                <i class="far fa-circle nav-icon"></i><p>Logs ZKONG</p>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

            </ul>
        </nav>
    </div>
</aside>
