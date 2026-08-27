<?php

/**
 */

declare(strict_types=1);

/**
 * Parámetros de conexión a la base de datos MySQL.
 *
 * Cargado exclusivamente por Database::obtenerInstancia().
 * Los valores se leen desde $_ENV (populado por vlucas/phpdotenv en public/index.php).
 * Los valores por defecto son seguros para entorno local con XAMPP.
 */
return [
    'host'     => $_ENV['DB_HOST']     ?? 'localhost',
    'port'     => $_ENV['DB_PORT']     ?? '3306',
    'dbname'   => $_ENV['DB_NAME']     ?? 'supermarket_app',
    'user'     => $_ENV['DB_USER']     ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset'  => 'utf8mb4',
];
