<?php
declare(strict_types=1);

/**
 * Front controller de la aplicación.
 *
 * Es el único fichero PHP accesible desde el exterior (DocumentRoot apunta a public/).
 * Realiza el bootstrap completo antes de ceder el control al Router:
 *  1. Carga el autoloader de Composer (PSR-4: App\ → app/).
 *  2. Carga y valida las variables de entorno desde .env (falla explícitamente
 *     si falta alguna variable requerida).
 *  3. Define BASE_URL a partir de SCRIPT_NAME para que el Router pueda
 *     normalizar las URIs independientemente del subdirectorio de instalación.
 *  4. Configura la sesión PHP (directorio personalizado, duración 24h,
 *     cookie SameSite=Lax, nombre de sesión propio).
 *  5. Carga scripts/ZkongAPIExtended.php (funciones procedurales de soporte ZKONG).
 *  6. Instancia el Router y llama a despachar().
 */

require_once dirname(__DIR__, 1) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 1));
$dotenv->load();
$dotenv->required(['APP_ENV', 'APP_DEBUG']);
$dotenv->required(['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD']);
$dotenv->required(['ZKONG_BASE_URL', 'ZKONG_ACCOUNT', 'ZKONG_PASSWORD', 'ZKONG_STORE_ID']);

define('BASE_URL', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'));

$_sessionDir = realpath(dirname(__DIR__, 1) . '/storage/sessions');
ini_set('session.save_path',      $_sessionDir);
ini_set('session.gc_maxlifetime', 86400);
ini_set('session.cookie_lifetime', 86400);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '0');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_path',    BASE_URL ?: '/');
session_name('SMKT_SESS');
session_start();
unset($_sessionDir);

use App\Core\Router;

$router = new Router();
$router->despachar();
