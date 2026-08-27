<?php

/**
 */

declare(strict_types=1);

namespace App\Core;

use App\Controladores\AuthControlador;
use App\Controladores\DashboardControlador;
use App\Controladores\EmparejamientoControlador;
use App\Controladores\EtiquetasControlador;
use App\Controladores\ImportacionControlador;
use App\Controladores\AdminControlador;
use App\Controladores\ProductosSincronizadosControlador;
use App\Controladores\ProductosControlador;

/**
 * Enrutador frontal de la aplicación.
 *
 * Registra en construcción todas las rutas disponibles como tripletas
 * (clase controlador, método, nivel de protección) indexadas por método HTTP
 * y URI normalizada. Al llamar a despachar() resuelve la petición entrante,
 * aplica el middleware correspondiente e invoca la acción del controlador.
 *
 * Niveles de protección:
 *   - 'publica'  → sin restricción de sesión.
 *   - 'auth'     → requiere sesión activa (cualquier rol).
 *   - 'admin'    → requiere sesión activa con rol = 'admin'.
 *
 * @see Middleware  Para la implementación de las guardas de acceso.
 */
class Router
{
    /** @var array<string, array<string, array{0: class-string, 1: string, 2: string}>> */
    private array $rutas = [
        'GET'  => [],
        'POST' => [],
    ];

    public function __construct()
    {
        $this->registrarRutas();
    }

    /**
     * Declara el mapa completo de rutas de la aplicación.
     *
     * Cada entrada asocia una URI a [clase, acción, nivel_protección].
     * El orden no importa; el despacho busca por clave exacta.
     */
    private function registrarRutas(): void
    {
        $this->rutas ['GET']  ['/auth/login']              = [AuthControlador::class,           'formularioLogin',          'publica'];
        $this->rutas ['POST'] ['/auth/login']              = [AuthControlador::class,           'procesarLogin',            'publica'];
        $this->rutas ['POST'] ['/logout']                  = [AuthControlador::class,           'logout',                   'auth'];

        $this->rutas ['GET']  ['/']                        = [DashboardControlador::class,      'index',                    'auth'];
        $this->rutas ['GET']  ['/dashboard']               = [DashboardControlador::class,      'index',                    'auth'];
        $this->rutas ['GET']  ['/dashboard/datos']         = [DashboardControlador::class,      'datos',                    'auth'];

        $this->rutas ['GET']  ['/emparejar']               = [EmparejamientoControlador::class, 'index',                    'auth'];
        $this->rutas ['GET']  ['/emparejar/lista']         = [EmparejamientoControlador::class, 'listar',                   'auth'];
        $this->rutas ['POST'] ['/emparejar']               = [EmparejamientoControlador::class, 'emparejar',                'auth'];
        $this->rutas ['POST'] ['/emparejar/vista-previa']  = [EmparejamientoControlador::class, 'vistaPrevia',              'auth'];
        $this->rutas ['POST'] ['/desemparejar']            = [EmparejamientoControlador::class, 'desemparejar',             'auth'];

        $this->rutas ['GET']  ['/etiquetas']               = [EtiquetasControlador::class,      'index',                    'auth'];
        $this->rutas ['GET']  ['/etiquetas/lista']         = [EtiquetasControlador::class,      'lista',                    'auth'];
        $this->rutas ['GET']  ['/antenas']                 = [EtiquetasControlador::class,      'antenas',                  'auth'];
        $this->rutas ['GET']  ['/antenas/lista']           = [EtiquetasControlador::class,      'listaAntenas',             'auth'];
        
        $this->rutas ['GET']  ['/importar']                = [ImportacionControlador::class,    'index',                    'admin'];
        $this->rutas ['GET']  ['/importar/historial']      = [ImportacionControlador::class,    'historial',                'admin'];
        $this->rutas ['POST'] ['/importar/comparar']       = [ImportacionControlador::class,    'comparar',                 'admin'];
        $this->rutas ['POST'] ['/importar/confirmar']      = [ImportacionControlador::class,    'confirmar',                'admin'];
        $this->rutas ['POST'] ['/importar/reintentar']     = [ImportacionControlador::class,    'reintentarSincronizacion', 'admin'];
        
        $this->rutas ['GET']  ['/zkong-cloud']             = [AdminControlador::class,          'zkongCloud',               'auth'];
        $this->rutas ['GET']  ['/zkong-cloud/proxy']       = [AdminControlador::class,          'zkongCloudProxy',          'auth'];
        $this->rutas ['GET']  ['/admin/logs-zkong']        = [AdminControlador::class,          'logsZkong',                'admin'];
        $this->rutas ['GET']  ['/admin/importaciones']     = [AdminControlador::class,          'importaciones',            'admin'];
        $this->rutas ['GET']  ['/admin/zkong/health']      = [AdminControlador::class,          'health',                   'admin'];
        $this->rutas ['POST'] ['/admin/zkong/conectar']    = [AdminControlador::class,          'conectarZkong',            'admin'];
        $this->rutas ['GET']  ['/admin/usuarios']          = [AdminControlador::class,          'usuarios',                 'admin'];
        $this->rutas ['POST'] ['/admin/usuarios/crear']    = [AdminControlador::class,          'crearUsuario',             'admin'];
        $this->rutas ['POST'] ['/admin/usuarios/editar']   = [AdminControlador::class,          'editarUsuario',            'admin'];
        $this->rutas ['POST'] ['/admin/usuarios/toggle']   = [AdminControlador::class,          'toggleActivoUsuario',      'admin'];
        $this->rutas ['POST'] ['/admin/usuarios/eliminar'] = [AdminControlador::class,          'eliminarUsuario',          'admin'];

        $this->rutas ['GET']  ['/productos-sincronizados']          = [ProductosControlador::class, 'index',                 'auth'];
        $this->rutas ['POST'] ['/productos/editar']                  = [ProductosControlador::class, 'editar',                'auth'];
        $this->rutas ['POST'] ['/productos/sincronizar-uno']         = [ProductosControlador::class, 'sincronizarUno',        'auth'];
        $this->rutas ['POST'] ['/productos/sincronizar-pendientes']  = [ProductosControlador::class, 'sincronizarPendientes', 'auth'];
    }

    /**
     * Resuelve la petición HTTP actual y ejecuta la acción correspondiente.
     *
     * Normaliza la URI quitando el subdirectorio base (BASE_URL) y elimina
     * la barra final para que las rutas sean independientes de si el navegador
     * la incluye o no. Responde con 404 si la ruta no existe.
     *
     * @return void
     */
    public function despachar(): void
    {
        $metodo = $_SERVER['REQUEST_METHOD'];
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        $uri    = '/' . ltrim(substr($uri, strlen($base)), '/');
        $uri    = rtrim($uri, '/') ?: '/';

        if (!isset($this->rutas[$metodo][$uri])) {
            http_response_code(404);
            include dirname(__DIR__, 1) . '/Vistas/errors/404.php';
            return;
        }

        [$clase, $accion, $proteccion] = $this->rutas[$metodo][$uri];

        match ($proteccion) {
            'auth'  => Middleware::requiereLogin(),
            'admin' => Middleware::requiereAdmin(),
            default => null,
        };

        $controlador = new $clase();
        $controlador->$accion();
    }
}
