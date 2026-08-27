<?php
/**
 *
 * Vista: Layout principal de la aplicación.
 *
 * Orquesta los tres parciales del layout AdminLTE:
 *  - header.php  → <html>, <head>, topbar y apertura del .wrapper.
 *  - navbar.php  → sidebar de navegación lateral.
 *  - [vista]     → contenido específico del módulo ($_vistaPath).
 *  - footer.php  → scripts JS, dark-mode toggle y cierre de HTML.
 *
 * $_vistaPath usa underscore inicial para evitar colisión con variables
 * extraídas por extract() en Controlador::render().
 */
?>
<?php include __DIR__ . '/header.php'; ?>
<?php include __DIR__ . '/navbar.php'; ?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0"><?= htmlspecialchars($tituloPagina ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
        </div>
    </div>
    <section class="content">
        <div class="container-fluid">
            <?php include $_vistaPath; ?>
        </div>
    </section>
</div>

<?php include __DIR__ . '/footer.php'; ?>
