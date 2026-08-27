<?php

/**
 */

declare(strict_types=1);

namespace App\Modelos\DAOs;

use App\Modelos\Helpers\Database;

/**
 * DAO de logs de llamadas a la API ZKONG.
 *
 * Registra y consulta cada petición HTTP que ZkongServicio envía al cloud.
 * Los logs permiten diagnosticar problemas de conectividad, tasas de error y
 * latencias sin necesidad de habilitar xdebug ni revisar logs de servidor.
 *
 * NOTA: obtenerTodos() no tiene llamadores en el código vivo; se conserva
 * como herramienta de diagnóstico para scripts o tests manuales.
 */
class LogZkongDAO
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerInstancia();
    }

    /**
     * Registra una llamada a la API ZKONG en la tabla logs_zkong.
     *
     * @param string   $endpoint  Ruta de la API (p. ej. '/zk/item/batchImportItem').
     * @param string   $metodo    Método HTTP ('GET', 'POST', 'DELETE').
     * @param int      $cantidad  Número de items del lote (0 si no aplica).
     * @param int|null $codigo    Código de respuesta ZKONG (null en error de red).
     * @param string|null $mensaje Mensaje de respuesta o descripción del error.
     * @param int      $duracion  Duración de la petición en milisegundos.
     * @return void
     *
     * @throws \PDOException Si el SP lanza un error.
     */
    public function insertar(
        string  $endpoint,
        string  $metodo,
        int     $cantidad,
        ?int    $codigo,
        ?string $mensaje,
        int     $duracion
    ): void {
        $stmt = $this->db->prepare('CALL sp_insertar_log_zkong(?, ?, ?, ?, ?, ?)');
        $stmt->execute([$endpoint, $metodo, $cantidad, $codigo, $mensaje, $duracion]);
        $stmt->closeCursor();
    }

    /**
     * Devuelve los últimos 500 logs de ZKONG sin paginación.
     *
     * @return array<int, array<string, mixed>>  Filas con endpoint, código, mensaje y duración.
     */
    public function obtenerTodos(): array
    {
        $stmt = $this->db->query('
            SELECT id, endpoint, metodo, cantidad_productos, codigo_respuesta,
                   mensaje_respuesta, duracion_ms, creado_en
            FROM   logs_zkong
            ORDER  BY creado_en DESC
            LIMIT  500
        ');
        return $stmt->fetchAll();
    }

    /**
     * Devuelve logs paginados junto con el total de registros.
     *
     * @param int $pagina Índice de página (base 0).
     * @param int $tamano Registros por página.
     * @return array{total: int, items: array<int, array<string, mixed>>}
     */
    public function obtenerPaginado(int $pagina, int $tamano): array
    {
        $offset = $pagina * $tamano;
        $total  = (int) $this->db->query('SELECT COUNT(*) FROM logs_zkong')->fetchColumn();

        $stmt = $this->db->prepare('
            SELECT id, endpoint, metodo, cantidad_productos, codigo_respuesta,
                   mensaje_respuesta, duracion_ms, creado_en
            FROM   logs_zkong
            ORDER  BY creado_en DESC
            LIMIT  ? OFFSET ?
        ');
        $stmt->execute([$tamano, $offset]);

        return ['total' => $total, 'items' => $stmt->fetchAll()];
    }

    /**
     * Devuelve el número total de registros en logs_zkong.
     *
     * @return int
     */
    public function contar(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM logs_zkong')->fetchColumn();
    }

    /**
     * Devuelve los últimos N errores (respuestas con código distinto de 10000).
     *
     * El código 10000 es el código de éxito de ZKONG; cualquier otro valor se
     * considera un error potencialmente relevante para el diagnóstico.
     *
     * @param int $limite Número máximo de errores a devolver.
     * @return array<int, array<string, mixed>>  Filas con endpoint, código y mensaje.
     */
    public function ultimosErrores(int $limite = 5): array
    {
        $stmt = $this->db->prepare('
            SELECT id, endpoint, metodo, codigo_respuesta, mensaje_respuesta,
                   duracion_ms, creado_en
            FROM   logs_zkong
            WHERE  codigo_respuesta IS NOT NULL AND codigo_respuesta != 10000
            ORDER  BY creado_en DESC
            LIMIT  ?
        ');
        $stmt->execute([$limite]);
        return $stmt->fetchAll();
    }
}
