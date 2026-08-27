<?php
/**
 *
 * Vista: Parcial de pie de página HTML.
 *
 * Cierra el .wrapper de AdminLTE, emite el <footer> y carga todos los scripts JS
 * en el orden correcto: jQuery → Bootstrap → AdminLTE → SweetAlert2 → AgGrid.
 * AgGrid se carga aquí (no en <head>) para no bloquear el renderizado inicial.
 *
 * El toggle de dark-mode persiste la preferencia en localStorage y sincroniza
 * el icono (luna/sol) con el estado actual de la clase .dark-mode en <html>.
 */
?>
    <footer class="main-footer">
        <strong>Supermarket App</strong> &mdash; Panel de gestión interno.
    </footer>

    <div id="sidebar-overlay" data-widget="pushmenu"></div>

</div><!-- /.wrapper -->

<script>const BASE_URL = '<?= BASE_URL ?>';</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/ag-grid-community@32.0.0/dist/ag-grid-community.min.js"></script>
<script>
(function () {
    var toggle = document.getElementById('dark-mode-toggle');
    if (!toggle) return;
    var html = document.documentElement;
    var icon = toggle.querySelector('i');
    if (html.classList.contains('dark-mode') && icon) {
        icon.className = 'fas fa-sun';
    }
    toggle.addEventListener('click', function () {
        var isDark = html.classList.toggle('dark-mode');
        localStorage.setItem('darkMode', isDark ? '1' : '0');
        if (icon) icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
    });
}());
</script>
</body>
</html>
