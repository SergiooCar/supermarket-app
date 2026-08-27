<?php

/**
 */

declare(strict_types=1);

namespace App\Controladores;

use App\Core\Controlador;
use App\Modelos\DAOs\ImportacionDAO;
use App\Modelos\DAOs\LogZkongDAO;
use App\Modelos\DAOs\ProductoDAO;
use App\Servicios\ZkongServicio;

/**
 * Controlador del panel principal (dashboard).
 *
 * Expone dos acciones:
 *  - index(): renderiza el esqueleto HTML con datos mínimos de sesión.
 *  - datos(): endpoint JSON que agrega métricas de BD y estado ZKONG en tiempo real.
 *
 * La separación entre ambas acciones permite que la vista haga una petición AJAX
 * a /dashboard/datos sin bloquear el renderizado inicial.
 */
class DashboardControlador extends Controlador
{
    /**
     * Renderiza la vista del dashboard con el estado básico de la sesión ZKONG.
     *
     * Los datos pesados (estado de etiquetas, antenas, gráfico) se cargan
     * de forma asíncrona desde la vista llamando a la acción datos().
     *
     * @return void
     */
    public function index(): void
    {
        $logDAO = new LogZkongDAO();

        $this->render('dashboard/index', [
            'tituloPagina'   => 'Dashboard',
            'paginaActual'   => 'dashboard',
            'zkongToken'     => $_SESSION['zkong_token'] ?? null,
            'zkongStoreId'   => $_SESSION['zkong_store_id'] ?? null,
            'ultimosErrores' => $logDAO->ultimosErrores(5),
            'totalLogs'      => $logDAO->contar(),
        ]);
    }

    /**
     * Devuelve las métricas del dashboard en formato JSON.
     *
     * Consulta contadores locales (productos, importaciones) y, si ZKONG
     * responde, agrega estado de etiquetas y antenas. El bloque ZKONG está
     * envuelto en try/catch para que el dashboard sea funcional incluso cuando
     * la API no está disponible; en ese caso los contadores de etiquetas y
     * antenas vuelven a cero.
     *
     * @return void  Emite JSON directamente al buffer de salida.
     */
    public function datos(): void
    {
        header('Content-Type: application/json');

        $importacionDAO = new ImportacionDAO();
        $productoDAO    = new ProductoDAO();

        // Métricas locales (no requieren ZKONG)
        $totalProductos    = count($productoDAO->obtenerTodos());
        $ultimasImportaciones = $importacionDAO->obtenerTodos();
        $ultimaImportacion = $ultimasImportaciones[0] ?? null;

        // Últimas 10 para el gráfico
        $grafico = array_slice(array_reverse($ultimasImportaciones), 0, 10);

        // Estado etiquetas y antenas desde ZKONG (no fatal si falla)
        $etiquetasOnline  = 0;
        $etiquetasOffline = 0;
        $antenasOnline    = 0;
        $antenasOffline   = 0;

        try {
            $zkong   = new ZkongServicio();
            $eslData = $zkong->obtenerEstadoEtiquetas(0, 200);
            $items   = $eslData['content'] ?? (is_array($eslData) ? $eslData : []);
            foreach ($items as $esl) {
                $state = strtolower((string) ($esl['state'] ?? ''));
                if ($state === 'online' || $state === '1') {
                    $etiquetasOnline++;
                } else {
                    $etiquetasOffline++;
                }
            }

            $apData = $zkong->obtenerEstadoAntenas();
            $aps    = is_array($apData) ? ($apData['list'] ?? $apData) : [];
            foreach ($aps as $ap) {
                if ($ap['online'] ?? false) {
                    $antenasOnline++;
                } else {
                    $antenasOffline++;
                }
            }
        } catch (\Throwable) {
            // ZKONG no disponible — los contadores quedan en 0
        }

        $logDAO         = new LogZkongDAO();
        $ultimosErrores = $logDAO->ultimosErrores(5);
        $totalLogs      = $logDAO->contar();

        echo json_encode([
            'totalProductos'       => $totalProductos,
            'etiquetasOnline'      => $etiquetasOnline,
            'etiquetasOffline'     => $etiquetasOffline,
            'antenasOnline'        => $antenasOnline,
            'antenasOffline'       => $antenasOffline,
            'ultimaImportacion'    => $ultimaImportacion,
            'ultimasImportaciones' => array_slice($ultimasImportaciones, 0, 5),
            'grafico'              => array_map(fn($i) => [
                'fecha'       => substr($i['creado_en'] ?? '', 0, 10),
                'modificados' => (int) ($i['modificados'] ?? 0),
                'nuevos'      => (int) ($i['nuevos'] ?? 0),
            ], $grafico),
            'zkongConectado'  => !empty($_SESSION['zkong_token']),
            'zkongStoreId'    => $_SESSION['zkong_store_id'] ?? null,
            'totalLogs'       => $totalLogs,
            'ultimosErrores'  => $ultimosErrores,
            'apiUrl'          => $_ENV['ZKONG_BASE_URL'] ?? null,
            'authMode'        => $_ENV['ZKONG_AUTH_MODE'] ?? 'token',
            'sslInsecure'     => ($_ENV['ZKONG_SSL_INSECURE'] ?? 'true') === 'true',
        ]);
    }
}
