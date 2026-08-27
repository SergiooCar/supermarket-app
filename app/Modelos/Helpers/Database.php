<?php

/**
 */

declare(strict_types=1);

namespace App\Modelos\Helpers;

/**
 * Singleton de conexión PDO a MySQL.
 *
 * Garantiza una única instancia de PDO por proceso PHP. 
 * La configuración se carga desde app/Config/database.php, que a su vez lee las variables de
 * entorno definidas en .env (cargadas por vlucas/phpdotenv en public/index.php).
 *
 * Opciones PDO configuradas:
 *  - ERRMODE_EXCEPTION: cualquier error lanza PDOException en vez de devolver false.
 *  - FETCH_ASSOC:       fetchAll() devuelve arrays asociativos por defecto.
 *  - EMULATE_PREPARES=false: MySQL ejecuta prepared statements reales (más seguro).
 *  - INIT_COMMAND utf8mb4: fuerza el charset en cada conexión nueva.
 *
 * El constructor y __clone son privados para hacer la clase no instanciable
 * ni clonable desde fuera.
 */
class Database
{
    private static ?\PDO $instancia = null;

    private function __construct() {}
    private function __clone() {}

    /**
     * Devuelve la instancia PDO compartida, creándola si no existe.
     *
     * @return \PDO  Conexión activa a la base de datos.
     *
     * @throws \PDOException Si la conexión a MySQL falla (credenciales, host, etc.).
     */
    public static function obtenerInstancia(): \PDO
    {
        if (self::$instancia === null) {
            $config = require  dirname(__DIR__, 2) . '/Config/database.php';

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['dbname'],
                $config['charset']
            );

            self::$instancia = new \PDO($dsn, $config['user'], $config['password'], [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
            ]);
        }

        return self::$instancia;
    }
}
