<?php

/**
 */

declare(strict_types=1);

namespace App\Controladores;

use App\Core\Controlador;
use App\Modelos\DAOs\ProductoDAO;

/**
 * Controlador de visualización y exportación de productos sincronizados con ZKONG.
 *
 * Gestiona la vista que muestra únicamente los productos cuyo estado_zkong
 * es 'sincronizado', es decir, los que el cloud ZKONG conoce. La misma acción
 * index() sirve tanto el HTML (petición normal) como el JSON (petición AJAX).
 */
class ProductosSincronizadosControlador extends Controlador
{
    /**
     * Renderiza la vista de productos sincronizados o devuelve su JSON.
     *
     * La distinción entre HTML y JSON se hace por la cabecera X-Requested-With,
     * siguiendo el mismo patrón que el resto de vistas con AgGrid de esta app.
     *
     * @return void  Renderiza HTML o emite JSON con el array de productos sincronizados.
     */
    public function index(): void
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            $dao     = new ProductoDAO();
            $productos = $dao->obtenerSincronizados();
            echo json_encode(array_map(fn($p) => [
                'codigo_barras'  => $p->getCodigoBarras(),
                'referencia'     => $p->getReferencia(),
                'nombre'         => $p->getNombre(),
                'precio_venta'   => $p->getPrecioVenta(),
                'precio_oferta'  => $p->getPrecioOferta(),
                'precio_tarifa'  => $p->getPrecioTarifa(),
                'precio_frio'    => $p->getPrecioFrio(),
                'precio_unidad'  => $p->getPrecioUnidad(),
                'tipo_unidad'    => $p->getTipoUnidad(),
                'cant_caja'      => $p->getCantCaja(),
            ], $productos));
            return;
        }

        $this->render('productos-sincronizados/index', [
            'tituloPagina' => 'Productos Sincronizados',
            'paginaActual' => 'productos-sincronizados',
        ]);
    }
}
