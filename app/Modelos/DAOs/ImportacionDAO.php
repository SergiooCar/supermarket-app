<?php

/**
 */

declare(strict_types=1);

namespace App\Modelos\DAOs;

use App\Modelos\Helpers\Database;

/**
 * DAO de importaciones CSV.
 *
 * Gestiona el registro histórico de cada importación de productos. Distingue
 * dos estados independientes: el estado de la BD local ('estado') y el estado
 * de sincronización con el cloud ZKONG ('estado_zkong'), porque ambas
 * operaciones pueden fallar de forma independiente.
 *
 * NOTA: existeSincronizacionExitosa() no tiene llamadores en el código vivo;
 * se conserva para no romper código externo que pudiera usarla.
 */
class ImportacionDAO
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerInstancia();
    }

    /**
     * Registra una nueva importación y devuelve su ID generado.
     *
     * @param string $nombreFichero Nombre original del fichero CSV subido.
     * @param int    $total         Total de productos en el CSV.
     * @param int    $nuevos        Productos que no existían en la BD.
     * @param int    $modificados   Productos que cambiaron respecto a la BD.
     * @param int    $eliminados    Productos eliminados de la BD por no estar en el CSV.
     * @param string $estado        Estado inicial de la importación ('pendiente', 'aplicado', etc.).
     * @return int  ID autoincremental del registro creado.
     *
     * @throws \PDOException Si falla la inserción.
     */
    public function registrar(
        string $nombreFichero,
        int    $total,
        int    $nuevos,
        int    $modificados,
        int    $eliminados,
        string $estado
    ): int {
        $stmt = $this->db->prepare('
            INSERT INTO importaciones
                (nombre_fichero, total_productos, nuevos, modificados, eliminados, estado)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$nombreFichero, $total, $nuevos, $modificados, $eliminados, $estado]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualiza el estado de la importación en la BD local.
     *
     * @param int    $id     ID de la importación.
     * @param string $estado Nuevo estado ('aplicado', 'error', etc.).
     * @return void
     *
     * @throws \PDOException Si falla la actualización.
     */
    public function actualizarEstado(int $id, string $estado): void
    {
        $stmt = $this->db->prepare('UPDATE importaciones SET estado = ? WHERE id = ?');
        $stmt->execute([$estado, $id]);
    }

    /**
     * Actualiza el estado de sincronización ZKONG de una importación.
     *
     * Usa el SP sp_actualizar_estado_zkong en vez de SQL directo porque el SP
     * también actualiza el campo 'sincronizado_en' con la marca de tiempo.
     *
     * @param int    $id     ID de la importación.
     * @param string $estado Nuevo estado ZKONG ('sincronizado', 'error_zkong', etc.).
     * @return void
     *
     * @throws \PDOException Si el SP lanza un error.
     */
    public function actualizarEstadoZkong(int $id, string $estado): void
    {
        $stmt = $this->db->prepare('CALL sp_actualizar_estado_zkong(?, ?)');
        $stmt->execute([$id, $estado]);
        $stmt->closeCursor();
    }

    /**
     * Comprueba si existe al menos una importación con estado_zkong = 'sincronizado'.
     *
     * @return bool  true si alguna importación fue sincronizada con éxito.
     */
    public function existeSincronizacionExitosa(): bool
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM importaciones WHERE estado_zkong = 'sincronizado' LIMIT 1");
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Devuelve todas las importaciones ordenadas de más reciente a más antigua.
     *
     * @return array<int, array<string, mixed>>  Filas con id, fichero, contadores, estados y fecha.
     */
    public function obtenerTodos(): array
    {
        $stmt = $this->db->query('
            SELECT id, nombre_fichero, total_productos, nuevos, modificados, eliminados,
                   estado, estado_zkong, creado_en
            FROM importaciones
            ORDER BY creado_en DESC
        ');
        return $stmt->fetchAll();
    }
}
