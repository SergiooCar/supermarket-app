<?php

/**
 */

declare(strict_types=1);

namespace App\Modelos\DAOs;

use App\Modelos\Helpers\Database;

/**
 * DAO de emparejamientos ESL.
 *
 * Gestiona la tabla de emparejamientos entre etiquetas físicas (ESL) y
 * artículos del catálogo. Todas las operaciones delegan en stored procedures
 * para mantener la lógica de negocio centralizada en la BD.
 *
 * @see sp_insertar_emparejamiento
 * @see sp_desemparejar_etiqueta
 * @see sp_obtener_emparejamientos
 */
class EmparejamientoDAO
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerInstancia();
    }

    /**
     * Registra un nuevo emparejamiento entre una etiqueta ESL y un artículo.
     *
     * @param string $codigoEtiqueta  Código físico de la etiqueta ESL.
     * @param string $barcodeArticulo Código de barras del artículo (idArt del CSV).
     * @param string $storeId         Identificador de tienda en ZKONG.
     * @return void
     *
     * @throws \PDOException Si el SP lanza un error (p. ej. duplicado).
     */
    public function insertar(string $codigoEtiqueta, string $barcodeArticulo, string $storeId): void
    {
        $stmt = $this->db->prepare('CALL sp_insertar_emparejamiento(?, ?, ?)');
        $stmt->execute([$codigoEtiqueta, $barcodeArticulo, $storeId]);
        $stmt->closeCursor();
    }

    /**
     * Marca como inactivo el emparejamiento vigente de una etiqueta.
     *
     * @param string $codigoEtiqueta Código físico de la etiqueta a desemparejar.
     * @return void
     *
     * @throws \PDOException Si el SP lanza un error.
     */
    public function desemparejar(string $codigoEtiqueta): void
    {
        $stmt = $this->db->prepare('CALL sp_desemparejar_etiqueta(?)');
        $stmt->execute([$codigoEtiqueta]);
        $stmt->closeCursor();
    }

    /**
     * Devuelve todos los emparejamientos activos registrados en la BD.
     *
     * @return array<int, array<string, mixed>>  Filas con etiqueta, artículo, tienda y fecha.
     *
     * @throws \PDOException Si el SP lanza un error.
     */
    public function obtenerVigentes(): array
    {
        $stmt = $this->db->prepare('CALL sp_obtener_emparejamientos()');
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $stmt->closeCursor();
        return $rows;
    }
}
