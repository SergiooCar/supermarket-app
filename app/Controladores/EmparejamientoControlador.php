<?php

/**
 */

declare(strict_types=1);

namespace App\Controladores;

use App\Core\Controlador;
use App\Modelos\DAOs\EmparejamientoDAO;
use App\Modelos\DAOs\ProductoDAO;
use App\Servicios\ZkongServicio;

/**
 * Controlador de emparejamiento de etiquetas ESL.
 *
 * Gestiona el vínculo entre etiquetas electrónicas (ESL) y artículos del
 * catálogo. Cada acción que modifica datos escribe primero en ZKONG y luego
 * en la BD local, salvo el caso del código de error 15069 (AP offline) que
 * se guarda igualmente en local para mantener consistencia.
 *
 * Todas las acciones que modifican estado devuelven JSON.
 */
class EmparejamientoControlador extends Controlador
{
    private EmparejamientoDAO $dao;

    public function __construct()
    {
        $this->dao = new EmparejamientoDAO();
    }

    /**
     * Renderiza la vista de gestión de emparejamientos.
     *
     * @return void
     */
    public function index(): void
    {
        $this->render('emparejamiento/index', [
            'tituloPagina' => 'Emparejar etiquetas',
            'paginaActual' => 'emparejar',
        ]);
    }

    /**
     * Devuelve la lista de emparejamientos vigentes en formato JSON.
     *
     * @return void  Emite JSON con el array de emparejamientos activos.
     */
    public function listar(): void
    {
        header('Content-Type: application/json');

        $locales = $this->dao->obtenerVigentes();
        $localesPorTag = [];

        foreach ($locales as &$l) {
            $l['origen'] = 'local';
            $localesPorTag[$l['codigo_etiqueta']] = $l;
        }
        unset($l);

        try {
            $zkong = new ZkongServicio();
            $productoDAO = new ProductoDAO();
            $cloudTags = $zkong->obtenerEtiquetasVinculadas();

            foreach ($cloudTags as $c) {
                $tag = $c['codigo_etiqueta'];
                if (isset($localesPorTag[$tag])) {
                    continue;
                }
                if ($c['barcode_articulo'] === '') {
                    continue;
                }
                $nombre = $c['nombre_articulo'];
                $producto = $productoDAO->obtenerPorCodigoBarras($c['barcode_articulo']);
                if ($producto !== null) {
                    $nombre = $producto->getNombre();
                }
                $localesPorTag[$tag] = [
                    'codigo_etiqueta'  => $tag,
                    'barcode_articulo' => $c['barcode_articulo'],
                    'nombre_articulo'  => $nombre,
                    'store_id'         => '',
                    'emparejado_en'    => null,
                    'origen'           => 'cloud',
                ];
            }
        } catch (\RuntimeException) {
            // si el cloud no responde, se devuelven solo los locales
        }

        echo json_encode(array_values($localesPorTag));
    }

    /**
     * Empareja una etiqueta ESL con un artículo del catálogo.
     *
     * Precondición: el artículo debe existir en la BD local (lo que garantiza
     * que ya fue sincronizado con ZKONG). Si la etiqueta aún no está registrada
     * en ZKONG (código 15069, AP offline), el emparejamiento se guarda solo en
     * local — se actualizará en el cloud cuando el AP vuelva a estar en línea.
     *
     * @return void  Emite JSON con {ok, estado, nombre, aviso?} o {ok, estado, detalle_error}.
     */
    public function emparejar(): void
    {
        header('Content-Type: application/json');

        $body            = json_decode(file_get_contents('php://input'), true) ?? [];
        $codigoEtiqueta  = trim((string) ($body['codigoEtiqueta']  ?? ''));
        $barcodeArticulo = trim((string) ($body['barcodeArticulo'] ?? ''));

        if ($codigoEtiqueta === '' || $barcodeArticulo === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'estado' => 'error', 'detalle_error' => 'Ambos campos son obligatorios.']);
            return;
        }

        // El nombre se obtiene de BD local si el artículo está importado; si no, se usa el barcode.
        $productoDAO = new ProductoDAO();
        $producto    = $productoDAO->obtenerPorCodigoBarras($barcodeArticulo);
        $nombre      = $producto?->getNombre() ?? $barcodeArticulo;

        // Precondición: el producto debe existir en BD local (= está sincronizado con el cloud).
        if ($producto === null) {
            http_response_code(422);
            echo json_encode([
                'ok'            => false,
                'estado'        => 'precondicion_fallida',
                'detalle_error' => "El artículo con barcode '{$barcodeArticulo}' no está importado. Importa el CSV antes de emparejar.",
            ]);
            return;
        }

        $avisoZkong = null;

        try {
            $zkong = new ZkongServicio();
            $zkong->emparejarProductoEtiqueta($barcodeArticulo, $codigoEtiqueta);
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            // 15069 = etiqueta no registrada en ZKONG (AP offline / nunca conectada).
            // Se guarda localmente para que la vista previa funcione en cuanto el AP esté online.
            if (str_contains($msg, '15069')) {
                $avisoZkong = 'Etiqueta no registrada en ZKONG aún (AP offline). Se guardará cuando el AP esté online.';
            } else {
                http_response_code(500);
                echo json_encode([
                    'ok'            => false,
                    'estado'        => 'error_zkong',
                    'detalle_error' => $msg,
                ]);
                return;
            }
        }

        $storeId = (string) ($_SESSION['zkong_store_id'] ?? '');

        try {
            $this->dao->insertar($codigoEtiqueta, $barcodeArticulo, $storeId);
        } catch (\Throwable $e) {
            $avisoZkong = ($avisoZkong ? $avisoZkong . ' | ' : '') . 'Guardado local: ' . $e->getMessage();
        }

        echo json_encode([
            'ok'     => true,
            'estado' => $avisoZkong === null ? 'emparejado' : 'emparejado_local',
            'nombre' => $nombre,
            'aviso'  => $avisoZkong,
        ]);
    }

    /**
     * Elimina el vínculo entre una etiqueta ESL y su artículo asociado.
     *
     * Llama primero a ZKONG para desemparejar en el cloud y, si tiene éxito,
     * actualiza la BD local. Si ZKONG falla, la BD local no se modifica para
     * evitar inconsistencias.
     *
     * @return void  Emite JSON con {ok, estado} o {ok, estado, detalle_error}.
     */
    public function desemparejar(): void
    {
        header('Content-Type: application/json');

        $body           = json_decode(file_get_contents('php://input'), true) ?? [];
        $codigoEtiqueta = trim((string) ($body['codigoEtiqueta'] ?? ''));

        if ($codigoEtiqueta === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'estado' => 'error', 'detalle_error' => 'El código de etiqueta es obligatorio.']);
            return;
        }

        try {
            $zkong = new ZkongServicio();
            $zkong->desemparejarEtiquetas([$codigoEtiqueta]);

            $this->dao->desemparejar($codigoEtiqueta);

            echo json_encode(['ok' => true, 'estado' => 'desemparejado']);
        } catch (\RuntimeException $e) {
            http_response_code(500);
            echo json_encode([
                'ok'           => false,
                'estado'       => 'error_zkong',
                'detalle_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtiene la imagen de vista previa de una etiqueta ESL desde el cloud ZKONG.
     *
     * Invoca el endpoint /zk/bind/viewBindPicByBarcode que devuelve un BMP en
     * base64. ZkongServicio convierte ese BMP a PNG server-side (si GD está
     * disponible) para maximizar la compatibilidad con los navegadores.
     *
     * @return void  Emite JSON con {ok, imagen: string|null, detalle_error?}.
     */
    public function vistaPrevia(): void
    {
        header('Content-Type: application/json');

        $body            = json_decode(file_get_contents('php://input'), true) ?? [];
        $barcodeArticulo = trim((string) ($body['barcode'] ?? ''));

        if ($barcodeArticulo === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'imagen' => null, 'detalle_error' => 'El barcode del artículo es obligatorio.']);
            return;
        }

        try {
            $zkong     = new ZkongServicio();
            $resultado = $zkong->obtenerVistaPrevia($barcodeArticulo);

            if ($resultado['imagen'] === null) {
                $detalle = empty($resultado['bindInfos'])
                    ? 'Sin vinculación o producto no subido al cloud.'
                    : 'El cloud no devolvió imagen para este artículo.';

                echo json_encode(['ok' => false, 'imagen' => null, 'detalle_error' => $detalle]);
                return;
            }

            echo json_encode(['ok' => true, 'imagen' => $resultado['imagen'], 'detalle_error' => null]);
        } catch (\RuntimeException $e) {
            http_response_code(500);
            echo json_encode([
                'ok'           => false,
                'imagen'       => null,
                'detalle_error' => $e->getMessage(),
            ]);
        }
    }
}
