<?php

/**
 */

declare(strict_types=1);

namespace App\Controladores;

use App\Core\Controlador;
use App\Modelos\DAOs\ImportacionDAO;
use App\Modelos\DAOs\LogZkongDAO;
use App\Modelos\DAOs\UsuarioDAO;
use App\Servicios\ZkongServicio;

/**
 * Controlador de administración del sistema.
 *
 * Agrupa las funciones reservadas al rol 'admin':
 *  - Diagnóstico y reconexión ZKONG.
 *  - Visualización paginada de logs de la API ZKONG.
 *  - Histórico de importaciones.
 *  - CRUD completo de usuarios del sistema.
 *  - Acceso al iframe del cloud ZKONG.
 *
 * Las acciones que reciben peticiones AJAX (identificadas por la cabecera
 * X-Requested-With) devuelven JSON; el resto renderiza la vista HTML.
 */
class AdminControlador extends Controlador
{
    /**
     * Renderiza la vista de iframe del cloud ZKONG.
     *
     * @return void
     */
    public function zkongCloud(): void
    {
        $this->render('zkong-cloud/index', [
            'tituloPagina' => 'ZKONG Cloud',
            'paginaActual' => 'zkong-cloud',
        ]);
    }

    public function zkongCloudProxy(): void
    {
        $zkongBase = rtrim($_ENV['ZKONG_BASE_URL'] ?? 'https://etiquetas.ausiasmarch.net', '/');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $zkongBase . '/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);

        $html     = (string) curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $html === '') {
            http_response_code(502);
            echo '<p style="font-family:sans-serif;padding:2rem;color:#c0392b">
                    No se pudo cargar el ZKONG Cloud (HTTP ' . $httpCode . ').
                    Comprueba la conexión con <code>' . htmlspecialchars($zkongBase) . '</code>.
                  </p>';
            return;
        }

        $html = preg_replace('/<head(\s[^>]*)?>/', '<head$1><base href="' . $zkongBase . '/">', $html, 1);

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');

        echo $html;
    }

    // ──────────────────────────────────────────────
    // Salud y conexión ZKONG
    // ──────────────────────────────────────────────

    /**
     * Devuelve el estado de salud de la integración ZKONG en formato JSON.
     *
     * Incluye si hay token en sesión, los IDs de tienda/agencia/merchant,
     * estadísticas de logs y configuración de entorno. Útil para diagnóstico.
     *
     * @return void  Emite JSON con el diagnóstico completo.
     */
    public function health(): void
    {
        header('Content-Type: application/json');

        $logDAO = new LogZkongDAO();

        echo json_encode([
            'conectado'        => !empty($_SESSION['zkong_token']),
            'store_id'         => $_SESSION['zkong_store_id']    ?? null,
            'agency_id'        => $_SESSION['zkong_agency_id']   ?? null,
            'merchant_id'      => $_SESSION['zkong_merchant_id'] ?? null,
            'total_peticiones' => $logDAO->contar(),
            'ultimos_errores'  => $logDAO->ultimosErrores(5),
            'api_url'          => $_ENV['ZKONG_BASE_URL'] ?? null,
            'auth_mode'        => $_ENV['ZKONG_AUTH_MODE'] ?? 'token',
            'ssl_insecure'     => ($_ENV['ZKONG_SSL_INSECURE'] ?? 'true') === 'true',
            'timestamp'        => date('c'),
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Fuerza una reconexión con ZKONG invalidando el token de sesión actual.
     *
     * Limpia todos los datos ZKONG de la sesión para forzar un login limpio.
     * Usa obtenerEstadoAntenas() como llamada de prueba porque es la operación
     * más ligera que requiere token válido. Si el token se obtiene aunque la
     * llamada falle, se considera conexión exitosa.
     *
     * @return void  Emite JSON con {ok, storeId, agencyId, merchantId} o {ok, error}.
     */
    public function conectarZkong(): void
    {
        header('Content-Type: application/json');

        unset(
            $_SESSION['zkong_token'],
            $_SESSION['zkong_agency_id'],
            $_SESSION['zkong_merchant_id'],
            $_SESSION['zkong_store_id'],
            $_SESSION['zkong_login_intentos']
        );

        try {
            $zkong = new ZkongServicio();
            $zkong->obtenerEstadoAntenas();
        } catch (\Throwable $e) {
            if (empty($_SESSION['zkong_token'])) {
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
                return;
            }
        }

        echo json_encode([
            'ok'         => !empty($_SESSION['zkong_token']),
            'storeId'    => $_SESSION['zkong_store_id']    ?? null,
            'agencyId'   => $_SESSION['zkong_agency_id']   ?? null,
            'merchantId' => $_SESSION['zkong_merchant_id'] ?? null,
        ]);
    }

    // ──────────────────────────────────────────────
    // Logs ZKONG
    // ──────────────────────────────────────────────

    /**
     * Muestra los logs de llamadas a la API ZKONG con paginación.
     *
     * En petición AJAX devuelve JSON paginado {total, items}.
     * En petición normal renderiza la vista con el grid vacío que luego
     * cargará los datos via AJAX.
     *
     * @return void
     */
    public function logsZkong(): void
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            $dao    = new LogZkongDAO();
            $pagina = max(0, (int) ($_GET['pagina'] ?? 0));
            $tamano = min(200, max(10, (int) ($_GET['tamano'] ?? 50)));
            echo json_encode($dao->obtenerPaginado($pagina, $tamano));
            return;
        }

        $this->render('admin/logs-zkong', [
            'tituloPagina' => 'Logs ZKONG',
            'paginaActual' => 'logs-zkong',
        ]);
    }

    // ──────────────────────────────────────────────
    // Histórico de importaciones
    // ──────────────────────────────────────────────

    /**
     * Muestra el histórico completo de importaciones CSV.
     *
     * En petición AJAX devuelve JSON con el array de importaciones.
     * En petición normal renderiza la vista con el grid.
     *
     * @return void
     */
    public function importaciones(): void
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode((new ImportacionDAO())->obtenerTodos());
            return;
        }

        $this->render('admin/importaciones', [
            'tituloPagina' => 'Histórico de importaciones',
            'paginaActual' => 'admin-importaciones',
        ]);
    }

    // ──────────────────────────────────────────────
    // CRUD usuarios
    // ──────────────────────────────────────────────

    /**
     * Lista todos los usuarios del sistema o renderiza la vista de gestión.
     *
     * En petición AJAX devuelve JSON con los datos públicos de cada usuario
     * (sin el hash de contraseña). En petición normal renderiza la vista.
     *
     * @return void
     */
    public function usuarios(): void
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            $lista = (new UsuarioDAO())->obtenerTodos();
            echo json_encode(array_map(fn($u) => [
                'id'        => $u->getId(),
                'nombre'    => $u->getNombre(),
                'email'     => $u->getEmail(),
                'rol'       => $u->getRol(),
                'activo'    => $u->isActivo(),
            ], $lista));
            return;
        }

        $this->render('admin/usuarios', [
            'tituloPagina' => 'Gestión de usuarios',
            'paginaActual' => 'admin-usuarios',
        ]);
    }

    /**
     * Crea un nuevo usuario del sistema.
     *
     * Lee nombre, email, password y rol del cuerpo JSON. La contraseña se
     * almacena siempre como hash bcrypt, nunca en texto plano.
     *
     * @return void  Emite JSON con {ok, id} o {ok, error} (HTTP 422/409).
     */
    public function crearUsuario(): void
    {
        header('Content-Type: application/json');
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $nombre   = trim((string) ($body['nombre']   ?? ''));
        $email    = trim((string) ($body['email']    ?? ''));
        $password = trim((string) ($body['password'] ?? ''));
        $rol      = in_array($body['rol'] ?? '', ['admin', 'user'], true) ? $body['rol'] : 'user';

        if ($nombre === '' || $email === '' || $password === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Nombre, email y contraseña son obligatorios.']);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Email inválido.']);
            return;
        }

        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $id   = (new UsuarioDAO())->crear($nombre, $email, $hash, $rol);
            echo json_encode(['ok' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            $raw = $e->getMessage();
            $msg = (str_contains($raw, 'Duplicate') || str_contains($raw, 'duplicate'))
                ? 'El email ya está en uso.'
                : $raw;
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => $msg]);
        }
    }

    /**
     * Actualiza nombre, email y rol de un usuario existente.
     *
     * Si el usuario editado es el mismo que realiza la petición, se actualiza
     * también el nombre en la sesión activa para que el topbar lo refleje.
     *
     * @return void  Emite JSON con {ok, self, nombre} o {ok, error} (HTTP 422/409).
     */
    public function editarUsuario(): void
    {
        header('Content-Type: application/json');
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $id     = (int) ($body['id']     ?? 0);
        $nombre = trim((string) ($body['nombre'] ?? ''));
        $email  = trim((string) ($body['email']  ?? ''));
        $rol    = in_array($body['rol'] ?? '', ['admin', 'user'], true) ? $body['rol'] : 'user';

        if ($id <= 0 || $nombre === '' || $email === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Datos incompletos.']);
            return;
        }

        try {
            (new UsuarioDAO())->editar($id, $nombre, $email, $rol);
            $self = isset($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] === $id;
            if ($self) {
                $_SESSION['nombre'] = $nombre;
            }
            echo json_encode(['ok' => true, 'self' => $self, 'nombre' => $nombre]);
        } catch (\PDOException $e) {
            $msg = str_contains($e->getMessage(), 'Duplicate') ? 'El email ya está en uso.' : 'Error al editar.';
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => $msg]);
        }
    }

    /**
     * Alterna el estado activo/inactivo de un usuario.
     *
     * @return void  Emite JSON con {ok} o {ok, error} (HTTP 422).
     */
    public function toggleActivoUsuario(): void
    {
        header('Content-Type: application/json');
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = (int) ($body['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'ID inválido.']);
            return;
        }

        (new UsuarioDAO())->toggleActivo($id);
        echo json_encode(['ok' => true]);
    }

    /**
     * Elimina un usuario del sistema.
     *
     * El stored procedure sp_eliminar_usuario evita que un admin se elimine a
     * sí mismo. Se pasa el adminId para que la validación ocurra en la BD.
     *
     * @return void  Emite JSON con {ok} o {ok, error} (HTTP 422).
     */
    public function eliminarUsuario(): void
    {
        header('Content-Type: application/json');
        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $id      = (int) ($body['id'] ?? 0);
        $adminId = (int) ($_SESSION['usuario_id'] ?? 0);

        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'ID inválido.']);
            return;
        }

        try {
            (new UsuarioDAO())->eliminar($id, $adminId);
            echo json_encode(['ok' => true]);
        } catch (\PDOException $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}
