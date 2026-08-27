<?php

/**
 */

declare(strict_types=1);

namespace App\Modelos\Entidades;

use App\Modelos\DTOs\ProductoDTO;

/**
 * Entidad inmutable que agrupa el resultado del análisis diferencial de una importación.
 *
 * ImportacionServicio::calcularDiferencias() devuelve esta entidad con tres listas:
 *  - nuevos:      productos del CSV que no existen en la BD.
 *  - modificados: pares {nuevo, anterior} de productos que cambiaron.
 *  - eliminados:  productos de la BD que ya no están en el CSV.
 *
 * NOTA: hayDiferencias() no tiene llamadores en el código vivo (los controladores
 * consultan directamente los totales). Se conserva como utilidad.
 */
class ResultadoDiferencias
{
    /**
     * @param ProductoDTO[]                                     $nuevos      Productos nuevos del CSV.
     * @param array<int, array{nuevo: ProductoDTO, anterior: ProductoDTO}> $modificados Pares de productos modificados.
     * @param ProductoDTO[]                                     $eliminados  Productos a eliminar de la BD.
     */
    public function __construct(
        private readonly array $nuevos,
        private readonly array $modificados,
        private readonly array $eliminados,
    ) {}

    /**
     * @return ProductoDTO[]
     */
    public function getNuevos(): array { return $this->nuevos; }

    /**
     * Devuelve los pares {nuevo, anterior} de productos modificados.
     *
     * @return array<int, array{nuevo: ProductoDTO, anterior: ProductoDTO}>
     */
    public function getModificados(): array { return $this->modificados; }

    /**
     * @return ProductoDTO[]
     */
    public function getEliminados(): array { return $this->eliminados; }

    /**
     * Extrae solo el DTO 'nuevo' de cada par de modificados.
     *
     * ProductoDAO::actualizarVarios() trabaja con ProductoDTOs simples,
     * no con los pares completos, por lo que se necesita esta proyección.
     *
     * @return ProductoDTO[]
     */
    public function getModificadosNuevos(): array
    {
        return array_map(fn(array $par) => $par['nuevo'], $this->modificados);
    }

    /** @return int */
    public function totalNuevos(): int      { return count($this->nuevos); }
    /** @return int */
    public function totalModificados(): int  { return count($this->modificados); }
    /** @return int */
    public function totalEliminados(): int   { return count($this->eliminados); }

    /**
     * Indica si hay alguna diferencia entre el CSV y la BD.
     *
     * @return bool
     */
    public function hayDiferencias(): bool
    {
        return !empty($this->nuevos) || !empty($this->modificados) || !empty($this->eliminados);
    }
}
