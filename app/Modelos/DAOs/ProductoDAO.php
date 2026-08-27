<?php

/**
 */

declare(strict_types=1);

namespace App\Modelos\DAOs;

use App\Modelos\DTOs\ProductoDTO;
use App\Modelos\Helpers\Database;

/**
 * DAO de productos del catálogo local.
 *
 * Gestiona la tabla 'productos' que actúa como caché local del catálogo ZKONG.
 * Las operaciones masivas (insertarVarios, actualizarVarios) usan SQL dinámico
 * con múltiples filas para reducir el número de roundtrips a la BD.
 *
 * El campo 'estado_zkong' rastrea si el producto ya fue enviado al cloud ZKONG
 * ('sincronizado') o está pendiente ('pendiente').
 *
 * NOTA: guardarOActualizar() no tiene llamadores en el código vivo; se conserva
 * por si se necesita en scripts de mantenimiento puntuales.
 */
class ProductoDAO
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerInstancia();
    }

    /**
     * Inserta o actualiza un único producto usando ON DUPLICATE KEY UPDATE.
     *
     * @param ProductoDTO $producto Producto a insertar o actualizar.
     * @return void
     *
     * @throws \PDOException Si falla la operación.
     */
    public function guardarOActualizar(ProductoDTO $producto): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO productos
                (codigo_barras, referencia, nombre, precio_venta, precio_oferta,
                 precio_tarifa, precio_frio, precio_unidad, tipo_unidad, cant_caja)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                referencia    = VALUES(referencia),
                nombre        = VALUES(nombre),
                precio_venta  = VALUES(precio_venta),
                precio_oferta = VALUES(precio_oferta),
                precio_tarifa = VALUES(precio_tarifa),
                precio_frio   = VALUES(precio_frio),
                precio_unidad = VALUES(precio_unidad),
                tipo_unidad   = VALUES(tipo_unidad),
                cant_caja     = VALUES(cant_caja)
        ');

        $stmt->execute([
            $producto->getCodigoBarras(),
            $producto->getReferencia(),
            $producto->getNombre(),
            $producto->getPrecioVenta(),
            $producto->getPrecioOferta(),
            $producto->getPrecioTarifa(),
            $producto->getPrecioFrio(),
            $producto->getPrecioUnidad(),
            $producto->getTipoUnidad(),
            $producto->getCantCaja(),
        ]);
    }

    /**
     * Devuelve los productos cuyo estado_zkong es 'sincronizado'.
     *
     * @return ProductoDTO[]  Productos ordenados por nombre ascendente.
     */
    public function obtenerSincronizados(): array
    {
        $stmt = $this->db->query('
            SELECT codigo_barras, referencia, nombre, precio_venta, precio_oferta,
                   precio_tarifa, precio_frio, precio_unidad, tipo_unidad, cant_caja,
                   estado_zkong, actualizado_en
            FROM productos
            WHERE estado_zkong = \'sincronizado\'
            ORDER BY nombre ASC
        ');

        return array_map(
            fn(array $row) => ProductoDTO::desdeArray($row),
            $stmt->fetchAll()
        );
    }

    /**
     * Marca un conjunto de productos como sincronizados con ZKONG.
     *
     * Construye la cláusula IN dinámicamente con placeholders para evitar
     * inyección SQL. Si la lista está vacía, no hace nada.
     *
     * @param string[] $codigosBarras Códigos de barras a marcar.
     * @return void
     *
     * @throws \PDOException Si falla la actualización.
     */
    public function marcarSincronizados(array $codigosBarras): void
    {
        if (empty($codigosBarras)) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($codigosBarras), '?'));
        $stmt = $this->db->prepare(
            "UPDATE productos SET estado_zkong = 'sincronizado' WHERE codigo_barras IN ($placeholders)"
        );
        $stmt->execute($codigosBarras);
    }

    /**
     * Devuelve todos los productos del catálogo local.
     *
     * @return ProductoDTO[]  Ordenados por nombre ascendente.
     */
    public function obtenerTodos(): array
    {
        $stmt = $this->db->query('
            SELECT codigo_barras, referencia, nombre, precio_venta, precio_oferta,
                   precio_tarifa, precio_frio, precio_unidad, tipo_unidad, cant_caja
            FROM productos
            ORDER BY nombre ASC
        ');

        return array_map(
            fn(array $row) => ProductoDTO::desdeArray($row),
            $stmt->fetchAll()
        );
    }

    /**
     * Busca un producto por su código de barras exacto.
     *
     * @param string $codigo Código de barras a buscar.
     * @return ProductoDTO|null  null si no existe.
     */
    public function obtenerPorCodigoBarras(string $codigo): ?ProductoDTO
    {
        $stmt = $this->db->prepare('
            SELECT codigo_barras, referencia, nombre, precio_venta, precio_oferta,
                   precio_tarifa, precio_frio, precio_unidad, tipo_unidad, cant_caja
            FROM productos
            WHERE codigo_barras = ?
            LIMIT 1
        ');
        $stmt->execute([$codigo]);
        $row = $stmt->fetch();

        return $row ? ProductoDTO::desdeArray($row) : null;
    }

    /**
     * Actualiza los campos de un único producto y lo marca como pendiente de sincronizar.
     *
     * @param ProductoDTO $producto Producto con los nuevos valores.
     * @return void
     *
     * @throws \PDOException Si falla la actualización.
     */
    public function actualizarUno(ProductoDTO $producto): void
    {
        $stmt = $this->db->prepare("
            UPDATE productos SET
                referencia    = ?, nombre        = ?, precio_venta  = ?,
                precio_oferta = ?, precio_tarifa  = ?, precio_frio   = ?,
                precio_unidad = ?, tipo_unidad    = ?, cant_caja     = ?,
                estado_zkong  = 'pendiente'
            WHERE codigo_barras = ?
        ");

        $stmt->execute([
            $producto->getReferencia(),   $producto->getNombre(),       $producto->getPrecioVenta(),
            $producto->getPrecioOferta(), $producto->getPrecioTarifa(), $producto->getPrecioFrio(),
            $producto->getPrecioUnidad(), $producto->getTipoUnidad(),   $producto->getCantCaja(),
            $producto->getCodigoBarras(),
        ]);
    }

    /**
     * Devuelve los productos cuyo estado_zkong no es 'sincronizado'.
     *
     * @return ProductoDTO[]  Ordenados por nombre ascendente.
     */
    public function obtenerPendientes(): array
    {
        $stmt = $this->db->query("
            SELECT codigo_barras, referencia, nombre, precio_venta, precio_oferta,
                   precio_tarifa, precio_frio, precio_unidad, tipo_unidad, cant_caja
            FROM productos
            WHERE estado_zkong != 'sincronizado'
            ORDER BY nombre ASC
        ");

        return array_map(fn(array $row) => ProductoDTO::desdeArray($row), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Devuelve el número de productos que no están sincronizados con ZKONG.
     *
     * @return int
     */
    public function contarPendientes(): int
    {
        return (int) $this->db->query(
            "SELECT COUNT(*) FROM productos WHERE estado_zkong != 'sincronizado'"
        )->fetchColumn();
    }

    /**
     * Devuelve todos los productos incluyendo su estado de sincronización.
     *
     * @return array<int, array<string, mixed>>  Filas crudas con campo estado_zkong incluido.
     */
    public function obtenerTodosConEstado(): array
    {
        $stmt = $this->db->query('
            SELECT codigo_barras, referencia, nombre, precio_venta, precio_oferta,
                   precio_tarifa, precio_frio, precio_unidad, tipo_unidad, cant_caja, estado_zkong
            FROM productos
            ORDER BY nombre ASC
        ');

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Inserta múltiples productos nuevos en un único statement SQL.
     *
     * Usa INSERT IGNORE para saltar duplicados silenciosamente. Los parámetros
     * se construyen dinámicamente para reducir el número de roundtrips a la BD
     * cuando el CSV tiene miles de productos nuevos.
     *
     * @param ProductoDTO[] $productos Lista de productos a insertar.
     * @return void
     *
     * @throws \PDOException Si falla la inserción.
     */
    public function insertarVarios(array $productos): void
    {
        if (empty($productos)) {
            return;
        }

        $filas  = [];
        $params = [];
        foreach ($productos as $p) {
            $filas[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            array_push(
                $params,
                $p->getCodigoBarras(), $p->getReferencia(), $p->getNombre(),
                $p->getPrecioVenta(),  $p->getPrecioOferta(), $p->getPrecioTarifa(),
                $p->getPrecioFrio(),   $p->getPrecioUnidad(), $p->getTipoUnidad(),
                $p->getCantCaja()
            );
        }

        $sql  = 'INSERT IGNORE INTO productos
                    (codigo_barras, referencia, nombre, precio_venta, precio_oferta,
                     precio_tarifa, precio_frio, precio_unidad, tipo_unidad, cant_caja)
                 VALUES ' . implode(',', $filas);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Actualiza los campos de múltiples productos existentes.
     *
     * Reutiliza el mismo statement preparado para cada producto, lo que es más
     * eficiente que preparar N statements distintos.
     *
     * @param ProductoDTO[] $productos Lista de productos con los nuevos valores.
     * @return void
     *
     * @throws \PDOException Si falla alguna actualización.
     */
    public function actualizarVarios(array $productos): void
    {
        if (empty($productos)) {
            return;
        }

        $stmt = $this->db->prepare('
            UPDATE productos SET
                referencia    = ?, nombre       = ?, precio_venta  = ?,
                precio_oferta = ?, precio_tarifa = ?, precio_frio   = ?,
                precio_unidad = ?, tipo_unidad   = ?, cant_caja     = ?
            WHERE codigo_barras = ?
        ');

        foreach ($productos as $p) {
            $stmt->execute([
                $p->getReferencia(),  $p->getNombre(),      $p->getPrecioVenta(),
                $p->getPrecioOferta(), $p->getPrecioTarifa(), $p->getPrecioFrio(),
                $p->getPrecioUnidad(), $p->getTipoUnidad(),  $p->getCantCaja(),
                $p->getCodigoBarras(),
            ]);
        }
    }

    /**
     * Elimina productos por sus códigos de barras.
     *
     * Construye la cláusula IN dinámicamente con placeholders. Si la lista
     * está vacía, no hace nada.
     *
     * @param string[] $codigos Códigos de barras de los productos a eliminar.
     * @return void
     *
     * @throws \PDOException Si falla la eliminación.
     */
    public function eliminarPorCodigosBarras(array $codigos): void
    {
        if (empty($codigos)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($codigos), '?'));
        $stmt = $this->db->prepare("DELETE FROM productos WHERE codigo_barras IN ({$placeholders})");
        $stmt->execute(array_values($codigos));
    }
}
