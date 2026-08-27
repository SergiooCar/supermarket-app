<?php

/**
 */

declare(strict_types=1);

namespace App\Core;

/**
 * Clase base abstracta para todos los controladores de la aplicación.
 *
 * Provee el método render() que envuelve cualquier vista dentro del layout
 * principal (app/Vistas/layouts/main.php), garantizando que cabecera,
 * barra lateral y pie de página sean siempre coherentes.
 */
abstract class Controlador
{
    /**
     * Renderiza una vista dentro del layout principal.
     *
     * La ruta de la vista se almacena en $_vistaPath con prefijo de guion bajo
     * para evitar colisiones con las variables que se exponen via extract().
     * El layout lee $_vistaPath y la incluye en el área de contenido.
     *
     * @param string $vista Ruta relativa bajo app/Vistas/ sin extensión .php
     *                      (p. ej. 'dashboard/index', 'admin/usuarios').
     * @param array  $datos Variables que se expondrán en la vista y el layout.
     * @return void
     */
    protected function render(string $vista, array $datos = []): void
    {
        $_vistaPath =  dirname(__DIR__, 1) . '/Vistas/' . $vista . '.php';
        extract($datos, EXTR_SKIP);
        include  dirname(__DIR__, 1) . '/Vistas/layouts/main.php';
    }
}
