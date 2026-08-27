<?php

/**
 */

declare(strict_types=1);

/**
 * Configuración general de la aplicación.
 *
 * NOTA: Este fichero no es cargado por ninguna clase PHP de la app en producción.
 * APP_ENV y APP_DEBUG se leen directamente desde $_ENV (cargado por dotenv).
 * Se conserva como documentación de las variables de entorno disponibles y
 * como punto de arranque si en el futuro se necesita una fachada Config::get().
 */
return [
    'nombre'  => 'Supermarket App',
    'entorno' => $_ENV['APP_ENV']   ?? 'production',
    'debug'   => filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
    'url'     => 'http://localhost/supermarket-app/public',
];
