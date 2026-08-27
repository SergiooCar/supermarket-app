<?php

/**
 */

declare(strict_types=1);

namespace App\Servicios;

use App\Modelos\DAOs\ProductoDAO;
use App\Modelos\DTOs\ProductoDTO;
use App\Modelos\Entidades\ResultadoDiferencias;
use App\Excepciones\CsvMalformadoException;

/**
 * Servicio de importación diferencial de productos desde CSV.
 *
 * Implementa tres operaciones del flujo de importación:
 *  1. parsearCSV()          → lee el fichero y construye un array de ProductoDTOs.
 *  2. calcularDiferencias() → compara el CSV con la BD y devuelve nuevos,
 *                              modificados y eliminados.
 *  3. aplicarDiferencias()  → persiste los cambios en la BD local.
 *
 * La comparación de precios usa number_format() con precisión fija (2 o 4
 * decimales) para evitar falsos positivos por diferencias de punto flotante.
 */
class ImportacionServicio
{
    /** Columnas que deben estar presentes en el CSV para que sea válido. */
    private const COLUMNAS_REQUERIDAS = [
        'ref', 'idArt', 'nombre', 'pvp',
        'cantCaja', 'precioUnidad', 'tipoUnidad',
        'pvpOferta', 'pvpTarifa', 'pvpFrio',
    ];

    private ProductoDAO $productoDAO;

    public function __construct()
    {
        $this->productoDAO = new ProductoDAO();
    }

    /**
     * Lee un fichero CSV y devuelve un array de ProductoDTOs.
     *
     * Elimina el BOM UTF-8 del primer campo si está presente (habitual en
     * exportaciones de Excel) y salta las filas con idArt vacío.
     *
     * @param string $rutaFichero Ruta absoluta al fichero CSV ya guardado en storage.
     * @return ProductoDTO[]
     *
     * @throws CsvMalformadoException  Si el fichero no se puede abrir, está vacío o
     *                                  le falta alguna columna requerida.
     */
    public function parsearCSV(string $rutaFichero): array
    {
        $handle = @fopen($rutaFichero, 'r');
        if ($handle === false) {
            throw new CsvMalformadoException('No se pudo abrir el fichero CSV.');
        }

        $cabeceras = fgetcsv($handle, 0, ';');
        if ($cabeceras === false || empty($cabeceras)) {
            fclose($handle);
            throw new CsvMalformadoException('El fichero CSV está vacío o mal formado.');
        }

        // Eliminar BOM UTF-8 del primer campo si lo hay
        $cabeceras[0] = str_replace("\xEF\xBB\xBF", '', $cabeceras[0]);
        $cabeceras    = array_map('trim', $cabeceras);

        foreach (self::COLUMNAS_REQUERIDAS as $columna) {
            if (!in_array($columna, $cabeceras, true)) {
                fclose($handle);
                throw new CsvMalformadoException("Columna requerida no encontrada: {$columna}");
            }
        }

        $productos = [];
        while (($fila = fgetcsv($handle, 0, ';')) !== false) {
            if (count($fila) < count($cabeceras)) {
                continue;
            }
            $mapeada = array_combine($cabeceras, $fila);
            if (trim($mapeada['idArt'] ?? '') === '') {
                continue;
            }
            $productos[] = ProductoDTO::desdeFilaCSV($mapeada);
        }

        fclose($handle);
        return $productos;
    }

    /**
     * Compara los productos del CSV con los de la BD y calcula las diferencias.
     *
     * Construye un mapa de BD indexado por código de barras para que la
     * comparación sea O(n) en vez de O(n²). El campo 'idArt' del CSV se
     * mapea a 'codigo_barras' en la BD (convención del proyecto).
     *
     * @param ProductoDTO[] $productosCSV Productos leídos del fichero CSV.
     * @return ResultadoDiferencias  Listas de nuevos, modificados y eliminados.
     */
    public function calcularDiferencias(array $productosCSV): ResultadoDiferencias
    {
        $productosBD = $this->productoDAO->obtenerTodos();

        $mapaBD = [];
        foreach ($productosBD as $p) {
            $mapaBD[$p->getCodigoBarras()] = $p;
        }

        $codigosCSV  = [];
        $nuevos      = [];
        $modificados = [];

        foreach ($productosCSV as $csv) {
            $codigo          = $csv->getCodigoBarras();
            $codigosCSV[$codigo] = true;

            if (!isset($mapaBD[$codigo])) {
                $nuevos[] = $csv;
            } elseif ($this->estaModificado($csv, $mapaBD[$codigo])) {
                $modificados[] = ['nuevo' => $csv, 'anterior' => $mapaBD[$codigo]];
            }
        }

        $eliminados = [];
        foreach ($productosBD as $bd) {
            if (!isset($codigosCSV[$bd->getCodigoBarras()])) {
                $eliminados[] = $bd;
            }
        }

        return new ResultadoDiferencias($nuevos, $modificados, $eliminados);
    }

    /**
     * Persiste las diferencias calculadas en la base de datos local.
     *
     * Opera en tres pasos secuenciales: insertar nuevos, actualizar modificados
     * y eliminar los que ya no están en el CSV. Cada paso solo se ejecuta si
     * hay elementos en la lista correspondiente.
     *
     * @param ResultadoDiferencias $diferencias Resultado de calcularDiferencias().
     * @return void
     */
    public function aplicarDiferencias(ResultadoDiferencias $diferencias): void
    {
        if (!empty($diferencias->getNuevos())) {
            $this->productoDAO->insertarVarios($diferencias->getNuevos());
        }

        if (!empty($diferencias->getModificadosNuevos())) {
            $this->productoDAO->actualizarVarios($diferencias->getModificadosNuevos());
        }

        $codigosEliminados = array_map(
            fn(ProductoDTO $p) => $p->getCodigoBarras(),
            $diferencias->getEliminados()
        );
        if (!empty($codigosEliminados)) {
            $this->productoDAO->eliminarPorCodigosBarras($codigosEliminados);
        }
    }

    /**
     * Determina si un producto del CSV difiere del que está en BD.
     *
     * Las comparaciones de texto son exactas (===). Las comparaciones de precio
     * usan mismoPrecio() con number_format para evitar falsos positivos por
     * la imprecisión inherente de los tipos float en PHP.
     *
     * @param ProductoDTO $csv Datos procedentes del CSV.
     * @param ProductoDTO $bd  Datos actuales en la base de datos.
     * @return bool  true si hay al menos un campo diferente.
     */
    private function estaModificado(ProductoDTO $csv, ProductoDTO $bd): bool
    {
        if ($csv->getNombre()    !== $bd->getNombre())    return true;
        if ($csv->getReferencia() !== $bd->getReferencia()) return true;
        if ($csv->getTipoUnidad() !== $bd->getTipoUnidad()) return true;
        if ($csv->getCantCaja()   !== $bd->getCantCaja())   return true;
        if (!$this->mismoPrecio($csv->getPrecioVenta(),   $bd->getPrecioVenta()))   return true;
        if (!$this->mismoPrecio($csv->getPrecioOferta(),  $bd->getPrecioOferta()))  return true;
        if (!$this->mismoPrecio($csv->getPrecioTarifa(),  $bd->getPrecioTarifa()))  return true;
        if (!$this->mismoPrecio($csv->getPrecioFrio(),    $bd->getPrecioFrio()))    return true;
        if (!$this->mismoPrecio($csv->getPrecioUnidad(),  $bd->getPrecioUnidad(), 4)) return true;
        return false;
    }

    /**
     * Compara dos precios con precisión fija para evitar falsos positivos por float.
     *
     * Se usa number_format en lugar de comparación directa de floats porque
     * 1.1 + 2.2 puede no ser estrictamente igual a 3.3 en punto flotante.
     * precioUnidad usa 4 decimales porque puede expresar fracciones de euro
     * por unidad (p. ej. precio por gramo) que requieren mayor precisión.
     *
     * @param float|null $a         Precio del CSV.
     * @param float|null $b         Precio de la BD.
     * @param int        $decimales Número de decimales para la comparación (2 por defecto).
     * @return bool  true si ambos precios son equivalentes a la precisión dada.
     */
    private function mismoPrecio(?float $a, ?float $b, int $decimales = 2): bool
    {
        if ($a === null && $b === null) return true;
        if ($a === null || $b === null) return false;
        return number_format($a, $decimales, '.', '') === number_format($b, $decimales, '.', '');
    }
}
