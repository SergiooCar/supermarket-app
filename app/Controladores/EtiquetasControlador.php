<?php

/**
 */

declare(strict_types=1);

namespace App\Controladores;

use App\Core\Controlador;
use App\Servicios\ZkongServicio;

/**
 * Controlador de estado de etiquetas ESL y antenas (Access Points).
 *
 * Las acciones index() y antenas() solo renderizan la vista HTML; los datos
 * reales se cargan de forma asíncrona por las acciones lista() y listaAntenas()
 * que actúan como endpoints JSON de solo lectura hacia el cloud ZKONG.
 */
class EtiquetasControlador extends Controlador
{
    /**
     * Renderiza la vista de estado de etiquetas ESL.
     *
     * @return void
     */
    public function index(): void
    {
        $this->render('etiquetas/index', [
            'tituloPagina' => 'Estado de etiquetas ESL',
            'paginaActual' => 'etiquetas',
        ]);
    }

    /**
     * Devuelve la lista paginada de etiquetas desde ZKONG en formato JSON.
     *
     * La respuesta de ZKONG puede venir como objeto paginado con clave 'content'
     * o directamente como array de items, según la versión de la API. Ambos
     * casos se normalizan antes de emitir la respuesta.
     *
     * @return void  Emite JSON con {ok, items, pagina} o {ok, items, detalle_error}.
     */
    public function lista(): void
    {
        header('Content-Type: application/json');

        $pagina = max(0, (int) ($_GET['pagina'] ?? 0));
        $tamano = min(200, max(1, (int) ($_GET['tamano'] ?? 100)));

        try {
            $zkong = new ZkongServicio();
            $data  = $zkong->obtenerEstadoEtiquetas($pagina, $tamano);

            // data puede ser array con 'content' (paginado) o directamente el array de items
            $items = $data['content'] ?? (is_array($data) ? $data : []);
            echo json_encode(['ok' => true, 'items' => $items, 'pagina' => $pagina]);
        } catch (\RuntimeException $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'items' => [], 'detalle_error' => $e->getMessage()]);
        }
    }

    /**
     * Renderiza la vista de estado de antenas (Access Points).
     *
     * @return void
     */
    public function antenas(): void
    {
        $this->render('etiquetas/antenas', [
            'tituloPagina' => 'Estado de antenas (AP)',
            'paginaActual' => 'antenas',
        ]);
    }

    /**
     * Devuelve la lista de antenas desde ZKONG en formato JSON.
     *
     * @return void  Emite JSON con {ok, items} o {ok, items, detalle_error}.
     */
    public function listaAntenas(): void
    {
        header('Content-Type: application/json');

        try {
            $zkong = new ZkongServicio();
            $data  = $zkong->obtenerEstadoAntenas();

            $items = $data['content'] ?? (is_array($data) ? $data : []);
            echo json_encode(['ok' => true, 'items' => $items]);
        } catch (\RuntimeException $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'items' => [], 'detalle_error' => $e->getMessage()]);
        }
    }
}
