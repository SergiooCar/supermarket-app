<?php

/**
 */

declare(strict_types=1);

namespace App\Modelos\DTOs;

/**
 * DTO inmutable que representa un producto del catálogo.
 *
 * Actúa como contenedor tipado entre las capas CSV/BD y la lógica de negocio.
 * Proporciona dos fábricas estáticas:
 *  - desdeFilaCSV() para construirlo desde el mapa de cabeceras del CSV.
 *  - desdeArray()   para construirlo desde una fila de BD (fetchAll FETCH_ASSOC).
 *
 * Los precios opcionales (oferta, tarifa, frío) son nullable porque el CSV
 * puede dejarlos en blanco cuando el artículo no tiene ese tipo de precio.
 */
class ProductoDTO
{
    /**
     * @param string     $codigoBarras  Código de barras EAN/PLU (idArt en el CSV).
     * @param string     $referencia    Referencia interna del artículo (campo 'ref').
     * @param string     $nombre        Nombre descriptivo del artículo.
     * @param float      $precioVenta   PVP estándar (pvp en el CSV).
     * @param float      $precioUnidad  Precio por unidad de medida (precioUnidad en el CSV).
     * @param string     $tipoUnidad    Tipo de unidad ('KG', 'UD', etc.).
     * @param int        $cantCaja      Unidades por caja.
     * @param float|null $precioOferta  PVP de oferta o null si no aplica.
     * @param float|null $precioTarifa  PVP de tarifa o null si no aplica.
     * @param float|null $precioFrio    PVP de productos de frío o null si no aplica.
     */
    public function __construct(
        private readonly string  $codigoBarras,
        private readonly string  $referencia,
        private readonly string  $nombre,
        private readonly float   $precioVenta,
        private readonly float   $precioUnidad,
        private readonly string  $tipoUnidad,
        private readonly int     $cantCaja,
        private readonly ?float  $precioOferta,
        private readonly ?float  $precioTarifa,
        private readonly ?float  $precioFrio,
    ) {}

    /** @return string */
    public function getCodigoBarras(): string  { return $this->codigoBarras; }
    /** @return string */
    public function getReferencia(): string    { return $this->referencia; }
    /** @return string */
    public function getNombre(): string        { return $this->nombre; }
    /** @return float */
    public function getPrecioVenta(): float    { return $this->precioVenta; }
    /** @return float */
    public function getPrecioUnidad(): float   { return $this->precioUnidad; }
    /** @return string */
    public function getTipoUnidad(): string    { return $this->tipoUnidad; }
    /** @return int */
    public function getCantCaja(): int         { return $this->cantCaja; }
    /** @return float|null */
    public function getPrecioOferta(): ?float  { return $this->precioOferta; }
    /** @return float|null */
    public function getPrecioTarifa(): ?float  { return $this->precioTarifa; }
    /** @return float|null */
    public function getPrecioFrio(): ?float    { return $this->precioFrio; }

    /**
     * Construye un ProductoDTO desde una fila mapeada del CSV.
     *
     * El campo 'idArt' del CSV se convierte en 'codigoBarras' porque es el
     * código único que identifica al producto tanto en la BD local como en ZKONG.
     *
     * @param array<string, string> $fila Mapa cabecera→valor de una fila del CSV.
     * @return self
     */
    public static function desdeFilaCSV(array $fila): self
    {
        return new self(
            codigoBarras: trim((string) ($fila['idArt'] ?? '')),
            referencia:   trim((string) ($fila['ref']   ?? '')),
            nombre:       trim((string) ($fila['nombre'] ?? '')),
            precioVenta:  self::parsearPrecio($fila['pvp']          ?? '') ?? 0.0,
            precioUnidad: self::parsearPrecio($fila['precioUnidad']  ?? '') ?? 0.0,
            tipoUnidad:   trim((string) ($fila['tipoUnidad'] ?? '')),
            cantCaja:     (int) ($fila['cantCaja'] ?? 0),
            precioOferta: self::parsearPrecio($fila['pvpOferta'] ?? ''),
            precioTarifa: self::parsearPrecio($fila['pvpTarifa'] ?? ''),
            precioFrio:   self::parsearPrecio($fila['pvpFrio']   ?? ''),
        );
    }

    /**
     * Construye un ProductoDTO desde una fila de BD (fetchAll con FETCH_ASSOC).
     *
     * @param array<string, mixed> $row Fila con los nombres de columna de la tabla 'productos'.
     * @return self
     */
    public static function desdeArray(array $row): self
    {
        return new self(
            codigoBarras: $row['codigo_barras'],
            referencia:   $row['referencia'],
            nombre:       $row['nombre'],
            precioVenta:  (float) $row['precio_venta'],
            precioUnidad: (float) $row['precio_unidad'],
            tipoUnidad:   $row['tipo_unidad'],
            cantCaja:     (int)   $row['cant_caja'],
            precioOferta: $row['precio_oferta'] !== null ? (float) $row['precio_oferta'] : null,
            precioTarifa: $row['precio_tarifa'] !== null ? (float) $row['precio_tarifa'] : null,
            precioFrio:   $row['precio_frio']   !== null ? (float) $row['precio_frio']   : null,
        );
    }

    /**
     * Convierte un string de precio del CSV a float.
     *
     * El CSV usa coma como separador decimal (convención española). Se
     * sustituye la coma por punto antes del cast para que (float) funcione
     * correctamente. Devuelve null si el campo está vacío (precio no aplicable).
     *
     * @param string $valor Precio en formato string (p. ej. '1,99' o '1.99').
     * @return float|null  null si el valor es cadena vacía.
     */
    private static function parsearPrecio(string $valor): ?float
    {
        $limpio = trim($valor);
        if ($limpio === '') {
            return null;
        }
        return (float) str_replace(',', '.', $limpio);
    }
}
