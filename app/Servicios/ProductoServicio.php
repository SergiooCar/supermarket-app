<?php

declare(strict_types=1);

namespace App\Servicios;

use App\Modelos\DAOs\ProductoDAO;
use App\Modelos\DTOs\ProductoDTO;

class ProductoServicio
{
    private const CSV_CATALOGO = __DIR__ . '/../../storage/catalogo.csv';

    private ProductoDAO $productoDAO;

    public function __construct()
    {
        $this->productoDAO = new ProductoDAO();
    }

    public function actualizarProducto(ProductoDTO $producto): void
    {
        $this->productoDAO->actualizarUno($producto);
        $this->regenerarCSV();
    }

    public function sincronizarUno(string $codigoBarras, ZkongServicio $zkong): void
    {
        $producto = $this->productoDAO->obtenerPorCodigoBarras($codigoBarras);
        if ($producto === null) {
            throw new \RuntimeException('Producto no encontrado.');
        }

        $zkong->importarProductos([$producto]);

        try {
            $zkong->forzarRefresco([$codigoBarras]);
        } catch (\RuntimeException) {
            // código 13040: sin emparejamientos activos, no es un error crítico
        }

        $this->productoDAO->marcarSincronizados([$codigoBarras]);
    }

    public function sincronizarPendientes(ZkongServicio $zkong): int
    {
        $pendientes = $this->productoDAO->obtenerPendientes();
        if (empty($pendientes)) {
            return 0;
        }

        $zkong->importarProductos($pendientes);

        $codigos = array_map(fn(ProductoDTO $p) => $p->getCodigoBarras(), $pendientes);
        try {
            $zkong->forzarRefresco($codigos);
        } catch (\RuntimeException) {
            // código 13040: sin emparejamientos activos, no es un error crítico
        }

        $this->productoDAO->marcarSincronizados($codigos);

        return count($pendientes);
    }

    public function regenerarCSV(): void
    {
        $productos = $this->productoDAO->obtenerTodos();
        $handle    = @fopen(self::CSV_CATALOGO, 'w');
        if ($handle === false) {
            return;
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['ref', 'idArt', 'nombre', 'pvp', 'cantCaja', 'precioUnidad', 'tipoUnidad', 'pvpOferta', 'pvpTarifa', 'pvpFrio'], ';');

        foreach ($productos as $p) {
            fputcsv($handle, [
                $p->getReferencia(),
                $p->getCodigoBarras(),
                $p->getNombre(),
                number_format($p->getPrecioVenta(),  2, ',', ''),
                $p->getCantCaja(),
                number_format($p->getPrecioUnidad(), 4, ',', ''),
                $p->getTipoUnidad(),
                $p->getPrecioOferta() !== null ? number_format($p->getPrecioOferta(), 2, ',', '') : '',
                $p->getPrecioTarifa() !== null ? number_format($p->getPrecioTarifa(), 2, ',', '') : '',
                $p->getPrecioFrio()   !== null ? number_format($p->getPrecioFrio(),   2, ',', '') : '',
            ], ';');
        }

        fclose($handle);
    }
}
