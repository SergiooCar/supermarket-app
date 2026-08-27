<?php

declare(strict_types=1);

namespace App\Controladores;

use App\Core\Controlador;
use App\Modelos\DAOs\ProductoDAO;
use App\Modelos\DTOs\ProductoDTO;
use App\Servicios\ProductoServicio;
use App\Servicios\ZkongServicio;

class ProductosControlador extends Controlador
{
    private ProductoDAO      $productoDAO;
    private ProductoServicio $productoServicio;

    public function __construct()
    {
        $this->productoDAO      = new ProductoDAO();
        $this->productoServicio = new ProductoServicio();
    }

    public function index(): void
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(
                array_map(
                    fn(array $row) => [
                        'codigo_barras' => $row['codigo_barras'],
                        'referencia'    => $row['referencia'],
                        'nombre'        => $row['nombre'],
                        'precio_venta'  => (float)  $row['precio_venta'],
                        'precio_oferta' => $row['precio_oferta'] !== null ? (float) $row['precio_oferta'] : null,
                        'precio_tarifa' => $row['precio_tarifa'] !== null ? (float) $row['precio_tarifa'] : null,
                        'precio_frio'   => $row['precio_frio']   !== null ? (float) $row['precio_frio']   : null,
                        'precio_unidad' => (float)  $row['precio_unidad'],
                        'tipo_unidad'   => $row['tipo_unidad'],
                        'cant_caja'     => (int)    $row['cant_caja'],
                        'estado_zkong'  => $row['estado_zkong'],
                    ],
                    $this->productoDAO->obtenerTodosConEstado()
                )
            );
            return;
        }

        $this->render('productos/index', [
            'tituloPagina' => 'Productos',
            'paginaActual' => 'productos-sincronizados',
        ]);
    }

    public function editar(): void
    {
        header('Content-Type: application/json');

        $codigo = trim($_POST['codigo_barras'] ?? '');
        if ($codigo === '') {
            http_response_code(422);
            echo json_encode(['error' => 'Código de barras obligatorio.']);
            return;
        }

        $existente = $this->productoDAO->obtenerPorCodigoBarras($codigo);
        if ($existente === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Producto no encontrado.']);
            return;
        }

        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '') {
            http_response_code(422);
            echo json_encode(['error' => 'El nombre no puede estar vacío.']);
            return;
        }

        $precioVenta = $this->parsearPrecio($_POST['precio_venta'] ?? '');
        if ($precioVenta === null || $precioVenta < 0) {
            http_response_code(422);
            echo json_encode(['error' => 'Precio de venta inválido.']);
            return;
        }

        $referencia   = trim($_POST['referencia']  ?? '') ?: $existente->getReferencia();
        $tipoUnidad   = trim($_POST['tipo_unidad'] ?? '') ?: $existente->getTipoUnidad();
        $cantCaja     = (int) ($_POST['cant_caja'] ?? 0);
        $precioUnidad = $this->parsearPrecio($_POST['precio_unidad'] ?? '') ?? $existente->getPrecioUnidad();

        $actualizado = new ProductoDTO(
            codigoBarras:  $codigo,
            referencia:    $referencia,
            nombre:        $nombre,
            precioVenta:   $precioVenta,
            precioUnidad:  $precioUnidad,
            tipoUnidad:    $tipoUnidad,
            cantCaja:      $cantCaja > 0 ? $cantCaja : $existente->getCantCaja(),
            precioOferta:  $this->parsearPrecio($_POST['precio_oferta'] ?? ''),
            precioTarifa:  $this->parsearPrecio($_POST['precio_tarifa'] ?? ''),
            precioFrio:    $this->parsearPrecio($_POST['precio_frio']   ?? ''),
        );

        try {
            $this->productoServicio->actualizarProducto($actualizado);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al guardar: ' . $e->getMessage()]);
            return;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Producto guardado correctamente.',
        ]);
    }

    public function sincronizarUno(): void
    {
        header('Content-Type: application/json');

        $codigo = trim($_POST['codigo_barras'] ?? '');
        if ($codigo === '') {
            http_response_code(422);
            echo json_encode(['error' => 'Código de barras obligatorio.']);
            return;
        }

        if ($this->productoDAO->obtenerPorCodigoBarras($codigo) === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Producto no encontrado.']);
            return;
        }

        try {
            $zkong = new ZkongServicio();
            $this->productoServicio->sincronizarUno($codigo, $zkong);

            echo json_encode([
                'success' => true,
                'message' => 'Producto enviado al cloud correctamente.',
            ]);
        } catch (\RuntimeException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function sincronizarPendientes(): void
    {
        header('Content-Type: application/json');

        try {
            $zkong = new ZkongServicio();
            $total = $this->productoServicio->sincronizarPendientes($zkong);

            if ($total === 0) {
                echo json_encode(['success' => true, 'total' => 0, 'message' => 'No hay productos pendientes de enviar.']);
                return;
            }

            echo json_encode([
                'success' => true,
                'total'   => $total,
                'message' => "Se enviaron {$total} producto" . ($total !== 1 ? 's' : '') . " al cloud.",
            ]);
        } catch (\RuntimeException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function parsearPrecio(string $valor): ?float
    {
        $limpio = trim($valor);
        if ($limpio === '') {
            return null;
        }
        return (float) str_replace(',', '.', $limpio);
    }
}
