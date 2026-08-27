<?php

/**
 */

declare(strict_types=1);

namespace App\Controladores;

use App\Core\Controlador;
use App\Modelos\DTOs\ProductoDTO;
use App\Modelos\Entidades\ResultadoDiferencias;
use App\Modelos\DAOs\ImportacionDAO;
use App\Modelos\DAOs\ProductoDAO;
use App\Servicios\ImportacionServicio;
use App\Servicios\ZkongServicio;

/**
 * Controlador de importación diferencial de productos desde CSV.
 *
 * Implementa un flujo de dos pasos:
 *  1. comparar()  → analiza el CSV, calcula diferencias y las guarda en sesión.
 *  2. confirmar() → aplica las diferencias a BD y las sincroniza con ZKONG.
 *
 * El flujo en dos pasos permite que el usuario revise los cambios antes de
 * confirmarlos. El contrato JSON de confirmar() distingue explícitamente
 * ok_bd y ok_zkong para comunicar fallos parciales al cliente.
 */
class ImportacionControlador extends Controlador
{
    /** Tamaño máximo de fichero CSV aceptado (10 MB). */
    private const TAMANO_MAXIMO = 10 * 1024 * 1024;

    /** Directorio donde se guardan los CSV subidos temporalmente. */
    private const DIR_STORAGE   = __DIR__ . '/../../storage/';

    private ImportacionServicio $servicio;
    private ImportacionDAO      $importacionDAO;

    public function __construct()
    {
        $this->servicio       = new ImportacionServicio();
        $this->importacionDAO = new ImportacionDAO();
    }

    /**
     * Renderiza la vista de importación de CSV.
     *
     * @return void
     */
    public function index(): void
    {
        $this->render('importacion/index', [
            'tituloPagina' => 'Importar CSV',
            'paginaActual' => 'importar',
        ]);
    }

    /**
     * Analiza el CSV subido y devuelve las diferencias respecto a la BD local.
     *
     * Llama a session_write_close() antes del procesamiento porque parsear y
     * comparar un CSV grande puede tardar varios segundos. Sin cerrar la sesión,
     * cualquier otra petición del mismo usuario quedaría bloqueada esperando
     * el lock de sesión de PHP.
     *
     * Las diferencias calculadas se guardan en sesión para que confirmar()
     * las recupere sin necesidad de re-parsear el fichero.
     *
     * @return void  Emite JSON con {success, diferencias, resumen} o {error}.
     */
    public function comparar(): void
    {
        header('Content-Type: application/json');
        set_time_limit(120);
        session_write_close();

        $archivo = $_FILES['csv'] ?? null;

        if (!$archivo || $archivo['error'] !== UPLOAD_ERR_OK) {
            http_response_code(422);
            echo json_encode(['error' => 'No se recibió ningún fichero.']);
            return;
        }

        if (strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION)) !== 'csv') {
            http_response_code(422);
            echo json_encode(['error' => 'Solo se permiten ficheros .csv.']);
            return;
        }

        if ($archivo['size'] > self::TAMANO_MAXIMO) {
            http_response_code(422);
            echo json_encode(['error' => 'El fichero supera el tamaño máximo de 10 MB.']);
            return;
        }

        $nombreFichero = 'importacion_' . date('Ymd_His') . '.csv';
        $rutaDestino   = self::DIR_STORAGE . $nombreFichero;

        if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo guardar el fichero en el servidor.']);
            return;
        }

        try {
            $productosCSV = $this->servicio->parsearCSV($rutaDestino);
            $diferencias  = $this->servicio->calcularDiferencias($productosCSV);

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['diferencias_pendientes']  = $diferencias;
            $_SESSION['nombre_fichero_pendiente'] = $nombreFichero;
            session_write_close();

            echo json_encode([
                'success'     => true,
                'diferencias' => $this->serializarParaGrid($diferencias),
                'resumen'     => [
                    'nuevos'      => $diferencias->totalNuevos(),
                    'modificados' => $diferencias->totalModificados(),
                    'eliminados'  => $diferencias->totalEliminados(),
                ],
            ]);
        } catch (\RuntimeException $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Aplica las diferencias pendientes a la BD y las sincroniza con ZKONG.
     *
     * El registro en importaciones se crea con estado 'procesando' antes de
     * ejecutar nada, de modo que un fallo posterior quede trazado en el historial.
     *
     * El contrato de respuesta es honesto: ok_bd y ok_zkong son independientes,
     * lo que permite al cliente distinguir si solo falló la sincronización con
     * el cloud (recuperable con "Reintentar") de si también falló la BD.
     *
     * @return void  Emite JSON con {ok_bd, ok_zkong, estado_zkong, message} o {error}.
     */
    public function confirmar(): void
    {
        header('Content-Type: application/json');

        $diferencias = $_SESSION['diferencias_pendientes'] ?? null;

        if (!$diferencias instanceof ResultadoDiferencias) {
            http_response_code(422);
            echo json_encode(['error' => 'No hay diferencias pendientes de confirmar.']);
            return;
        }

        $nombreFichero = $_SESSION['nombre_fichero_pendiente'] ?? 'desconocido.csv';

        // Registrar con estado_zkong = 'procesando' antes de tocar nada
        $idImportacion = $this->importacionDAO->registrar(
            $nombreFichero,
            $diferencias->totalNuevos() + $diferencias->totalModificados() + $diferencias->totalEliminados(),
            $diferencias->totalNuevos(),
            $diferencias->totalModificados(),
            $diferencias->totalEliminados(),
            'procesando'
        );

        try {
            $this->servicio->aplicarDiferencias($diferencias);
            $this->importacionDAO->actualizarEstadoZkong($idImportacion, 'pendiente');
            $this->importacionDAO->actualizarEstado($idImportacion, 'completada');
        } catch (\Exception $e) {
            $this->importacionDAO->actualizarEstado($idImportacion, 'fallida');
            http_response_code(500);
            echo json_encode(['ok_bd' => false, 'ok_zkong' => false, 'error' => 'Error al actualizar la base de datos: ' . $e->getMessage()]);
            return;
        }

        unset($_SESSION['diferencias_pendientes'], $_SESSION['nombre_fichero_pendiente']);

        $productosAEnviar = array_merge(
            $diferencias->getNuevos(),
            $diferencias->getModificadosNuevos()
        );

        $codigosEliminados = array_map(
            fn(ProductoDTO $p) => $p->getCodigoBarras(),
            $diferencias->getEliminados()
        );

        $codigosAfectados = array_map(
            fn(ProductoDTO $p) => $p->getCodigoBarras(),
            $productosAEnviar
        );

        try {
            $zkong = new ZkongServicio();

            if (!empty($productosAEnviar)) {
                $zkong->importarProductos($productosAEnviar);
            }
            if (!empty($codigosEliminados)) {
                $zkong->eliminarProductos($codigosEliminados);
            }
        } catch (\RuntimeException $e) {
            $this->importacionDAO->actualizarEstadoZkong($idImportacion, 'fallido');

            // Contrato honesto: ok_bd y ok_zkong separados
            echo json_encode([
                'ok_bd'         => true,
                'ok_zkong'      => false,
                'estado_zkong'  => 'fallido',
                'detalle_error' => $e->getMessage(),
                'message'       => sprintf(
                    'BD actualizada (%d nuevos, %d modificados, %d eliminados) pero falló la sincronización con ZKONG.',
                    $diferencias->totalNuevos(),
                    $diferencias->totalModificados(),
                    $diferencias->totalEliminados()
                ),
            ]);
            return;
        }

        if (!empty($codigosAfectados)) {
            try {
                $zkong->forzarRefresco($codigosAfectados);
            } catch (\RuntimeException) {
                // 13040 = sin emparejamientos activos; no es error crítico
            }
        }

        $this->importacionDAO->actualizarEstadoZkong($idImportacion, 'sincronizado');

        if (!empty($codigosAfectados)) {
            (new ProductoDAO())->marcarSincronizados($codigosAfectados);
        }

        echo json_encode([
            'ok_bd'        => true,
            'ok_zkong'     => true,
            'estado_zkong' => 'sincronizado',
            'message'      => sprintf(
                'Importación completada y sincronizada: %d nuevos, %d modificados, %d eliminados.',
                $diferencias->totalNuevos(),
                $diferencias->totalModificados(),
                $diferencias->totalEliminados()
            ),
        ]);
    }

    /**
     * Devuelve el historial completo de importaciones en formato JSON.
     *
     * @return void  Emite JSON con el array de importaciones ordenadas por fecha desc.
     */
    public function historial(): void
    {
        header('Content-Type: application/json');
        echo json_encode($this->importacionDAO->obtenerTodos());
    }

    /**
     * Reintenta la sincronización ZKONG para una importación previamente fallida.
     *
     * Envía el catálogo completo actual de la BD (no solo los productos de esa
     * importación) porque ZKONG no conserva historial de intentos fallidos: la
     * única forma fiable de garantizar la consistencia es re-sincronizar todo.
     *
     * @return void  Emite JSON con {success, message} o {error}.
     */
    public function reintentarSincronizacion(): void
    {
        header('Content-Type: application/json');

        $idImportacion = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$idImportacion) {
            http_response_code(422);
            echo json_encode(['error' => 'ID de importación inválido.']);
            return;
        }

        try {
            $productoDAO = new ProductoDAO();
            $todos       = $productoDAO->obtenerTodos();

            $zkong = new ZkongServicio();

            if (!empty($todos)) {
                $zkong->importarProductos($todos);

                $codigos = array_map(fn(ProductoDTO $p) => $p->getCodigoBarras(), $todos);
                try {
                    $zkong->forzarRefresco($codigos);
                } catch (\RuntimeException) {
                    // 13040 = sin emparejamientos activos; no es error crítico
                }
            }

            $this->importacionDAO->actualizarEstadoZkong($idImportacion, 'sincronizado');

            echo json_encode(['success' => true, 'message' => 'Sincronización completada correctamente.']);
        } catch (\RuntimeException $e) {
            $this->importacionDAO->actualizarEstadoZkong($idImportacion, 'fallido');
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Serializa un ResultadoDiferencias en el formato de filas que consume AgGrid.
     *
     * @param ResultadoDiferencias $diferencias Resultado del cálculo de diferencias.
     * @return array<int, array<string, mixed>>  Filas planas con campo 'estado' y datos del producto.
     */
    private function serializarParaGrid(ResultadoDiferencias $diferencias): array
    {
        $filas = [];

        foreach ($diferencias->getNuevos() as $p) {
            $filas[] = $this->productoAFila($p, 'nuevo', null);
        }
        foreach ($diferencias->getModificados() as $par) {
            $filas[] = $this->productoAFila($par['nuevo'], 'modificado', $par['anterior']);
        }
        foreach ($diferencias->getEliminados() as $p) {
            $filas[] = $this->productoAFila($p, 'eliminado', null);
        }

        return $filas;
    }

    /**
     * Convierte un ProductoDTO en una fila plana para el grid de diferencias.
     *
     * Incluye los valores anteriores cuando el estado es 'modificado' para que
     * el grid pueda resaltar las celdas que cambiaron.
     *
     * @param ProductoDTO      $nuevo    Datos del CSV (estado nuevo o valor actualizado).
     * @param string           $estado   'nuevo' | 'modificado' | 'eliminado'.
     * @param ProductoDTO|null $anterior Datos de BD antes del cambio (solo para 'modificado').
     * @return array<string, mixed>
     */
    private function productoAFila(ProductoDTO $nuevo, string $estado, ?ProductoDTO $anterior): array
    {
        return [
            'estado'               => $estado,
            'codigoBarras'         => $nuevo->getCodigoBarras(),
            'referencia'           => $nuevo->getReferencia(),
            'nombre'               => $nuevo->getNombre(),
            'nombreAnterior'       => $anterior?->getNombre(),
            'precioVenta'          => $nuevo->getPrecioVenta(),
            'precioVentaAnterior'  => $anterior?->getPrecioVenta(),
            'precioOferta'         => $nuevo->getPrecioOferta(),
            'precioOfertaAnterior' => $anterior?->getPrecioOferta(),
            'precioTarifa'         => $nuevo->getPrecioTarifa(),
            'precioFrio'           => $nuevo->getPrecioFrio(),
            'precioUnidad'         => $nuevo->getPrecioUnidad(),
            'precioUnidadAnterior' => $anterior?->getPrecioUnidad(),
            'tipoUnidad'           => $nuevo->getTipoUnidad(),
            'cantCaja'             => $nuevo->getCantCaja(),
        ];
    }
}
