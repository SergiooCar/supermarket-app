<?php

/**
 */

declare(strict_types=1);

namespace App\Servicios;

use App\Modelos\DAOs\LogZkongDAO;
use App\Modelos\DTOs\ProductoDTO;

/**
 * Servicio de integración con el cloud ZKONG (ESL — Electronic Shelf Labels).
 *
 * Encapsula todas las llamadas a la API REST de ZKONG: autenticación RSA,
 * sincronización de productos, emparejamiento de etiquetas, consulta de estado
 * y obtención de vistas previas. Es la única clase del proyecto que habla
 * directamente con el servidor ZKONG.
 *
 * FLUJO DE AUTENTICACIÓN:
 *  1. El servidor ZKONG expone una clave pública RSA en /user/getErpPublicKey.
 *  2. Se cifra la contraseña con esa clave (PKCS#1) y se envía en /user/login.
 *  3. La respuesta contiene un token Bearer que se almacena en $_SESSION.
 *  4. Todas las peticiones posteriores llevan ese token en la cabecera Authorization.
 *  5. Si el servidor devuelve 10041/10042 (token expirado) o 10013 (sesión
 *     desplazada), se hace un único reintento automático tras re-login.
 *
 * CHUNKING:
 *  Las operaciones masivas se dividen en lotes para respetar los límites de
 *  la API: 20 000 productos por lote en importación, 500 en eliminación y
 *  refresco, 2 000 en desemparejamiento. Los lotes se envían de forma
 *  secuencial (no en paralelo) para evitar sobrecargar el servidor y porque
 *  la API no garantiza idempotencia entre peticiones concurrentes.
 *
 * REGISTRO DE LOGS:
 *  Cada llamada HTTP se registra en la tabla logs_zkong via LogZkongDAO.
 *  Los fallos de log nunca interrumpen el flujo principal.
 *
 * @see LogZkongDAO
 * @see app/Config/zkong.php  Para la configuración de URLs y credenciales.
 */
class ZkongServicio
{
    private array      $config;
    private LogZkongDAO $logDAO;

    public function __construct()
    {
        $this->config = require  dirname(__DIR__, 1) . '/Config/zkong.php';
        $this->logDAO = new LogZkongDAO();
    }

    /**
     * Importa o actualiza productos en el catálogo ZKONG.
     *
     * Los productos se envían en lotes de 20 000 para no superar el límite de
     * payload de la API. Los campos del DTO se mapean a los campos de ZKONG:
     *  - idArt       → barCode
     *  - pvp         → price
     *  - precioUnidad → originalPrice
     *  - pvpOferta   → custFeature1
     *  - pvpTarifa   → custFeature2
     *  - pvpFrio     → custFeature3
     *  - cantCaja    → custFeature4
     *
     * @param ProductoDTO[] $productos Lista de productos a importar.
     * @return void
     *
     * @throws \RuntimeException  Si la llamada a la API falla.
     */
    public function importarProductos(array $productos): void
    {
        $this->asegurarToken();

        foreach (array_chunk($productos, 20000) as $chunk) {
            $itemList = array_map(fn(ProductoDTO $p) => [
                'attrCategory'  => 'default',
                'attrName'      => 'default',
                'barCode'       => $p->getCodigoBarras(),
                'itemTitle'     => $p->getNombre(),
                'price'         => (string) $p->getPrecioVenta(),
                'originalPrice' => (string) $p->getPrecioUnidad(),
                'custFeature1'  => (string) ($p->getPrecioOferta() ?? 0),
                'custFeature2'  => (string) ($p->getPrecioTarifa() ?? 0),
                'custFeature3'  => (string) ($p->getPrecioFrio()   ?? 0),
                'custFeature4'  => (string) $p->getCantCaja(),
                'productCode'   => $p->getReferencia(),
                'unit'          => $p->getTipoUnidad(),
            ], $chunk);

            $this->llamar('/zk/item/batchImportItem', 'POST', [
                'agencyId'   => $_SESSION['zkong_agency_id']   ?? '',
                'merchantId' => $_SESSION['zkong_merchant_id'] ?? '',
                'storeId'    => $_SESSION['zkong_store_id']    ?? '',
                'unitName'   => 1,
                'itemList'   => $itemList,
            ], cantidad: count($chunk));
        }
    }

    /**
     * Elimina productos del catálogo ZKONG por código de barras.
     *
     * Se envían en lotes de 500 para respetar el límite de la API.
     *
     * @param string[] $codigosBarras Códigos de barras de los productos a eliminar.
     * @return void
     *
     * @throws \RuntimeException  Si la llamada a la API falla.
     */
    public function eliminarProductos(array $codigosBarras): void
    {
        $this->asegurarToken();

        foreach (array_chunk($codigosBarras, 500) as $chunk) {
            $this->llamar('/zk/item/batchDeleteItem', 'DELETE', [
                'agencyId'   => (string) ($_SESSION['zkong_agency_id']   ?? ''),
                'merchantId' => (string) ($_SESSION['zkong_merchant_id'] ?? ''),
                'storeId'    => (string) ($_SESSION['zkong_store_id']    ?? ''),
                'list'       => array_map('strval', $chunk),
            ], cantidad: count($chunk));
        }
    }

    /**
     * Fuerza el refresco visual de las etiquetas vinculadas a los barcodes dados.
     *
     * Llama a /zk/bind/updateForceByBarCodes en lotes de 500. El error 13040
     * ('sin emparejamientos activos') no es fatal y se ignora en el llamador.
     *
     * @param string[] $codigosBarras Códigos de barras cuyas etiquetas deben refrescarse.
     * @return void
     *
     * @throws \RuntimeException  Si la API devuelve un error distinto de 13040.
     */
    public function forzarRefresco(array $codigosBarras): void
    {
        $this->asegurarToken();

        foreach (array_chunk($codigosBarras, 500) as $chunk) {
            $this->llamar('/zk/bind/updateForceByBarCodes', 'POST', [
                'storeId'     => (int) ($_SESSION['zkong_store_id'] ?? 0),
                'barCodeList' => $chunk,
            ], cantidad: count($chunk));
        }
    }

    /**
     * Empareja una etiqueta ESL con un artículo del catálogo en ZKONG.
     *
     * @param string $codigoBarras    Código de barras del artículo (idArt del CSV).
     * @param string $codigoEtiqueta  Código físico de la etiqueta ESL.
     * @return void
     *
     * @throws \RuntimeException  Si la API falla (incluye error 15069 = AP offline).
     */
    public function emparejarProductoEtiqueta(string $codigoBarras, string $codigoEtiqueta): void
    {
        $this->asegurarToken();

        $this->llamar('/zk/bind/bindItemPriceTag/1', 'POST', [
            'itemBarCode'  => $codigoBarras,
            'priceTagCode' => $codigoEtiqueta,
            'storeId'      => (string) ($_SESSION['zkong_store_id'] ?? $this->config['store_id']),
            'shelfNum'     => '',
            'apMac'        => $this->config['ap_mac'],
        ]);
    }

    /**
     * Obtiene la imagen de vista previa de una etiqueta por código de barras del artículo.
     *
     * El endpoint devuelve el campo 'images' como string base64 de un BMP o como
     * array de strings. Se toma el primero disponible. Si GD está disponible,
     * se convierte el BMP a PNG server-side para maximizar la compatibilidad
     * de los navegadores (algunos no renderizan BMP nativamente).
     *
     * @param string $barcodeArticulo Código de barras del artículo vinculado a la etiqueta.
     * @return array{imagen: string|null, bindInfos: array}
     *
     * @throws \RuntimeException  Si la API falla o devuelve una respuesta inesperada.
     */
    public function obtenerVistaPrevia(string $barcodeArticulo): array
    {
        $this->asegurarToken();

        $data = $this->llamar(
            '/zk/bind/viewBindPicByBarcode?barcode=' . urlencode($barcodeArticulo),
            'GET',
            []
        );

        if (!is_array($data)) {
            throw new \RuntimeException('Respuesta inesperada de viewBindPicByBarcode.');
        }

        // images puede ser string o array<string>; tomamos el primero disponible
        $raw = $data['images'] ?? null;
        if (is_array($raw)) {
            $raw = $raw[0] ?? null;
        }

        $bindInfos = $data['itemBindInfos'] ?? [];

        if (empty($raw)) {
            return ['imagen' => null, 'bindInfos' => $bindInfos];
        }

        // Limpiar posibles \r\n embebidos en el base64
        $base64Limpio = preg_replace('/\s+/', '', (string) $raw);

        // Intentar convertir BMP→PNG server-side con GD para máxima compatibilidad
        $imagenUri = $this->bmpBase64APng($base64Limpio)
            ?? 'data:image/bmp;base64,' . $base64Limpio;

        return ['imagen' => $imagenUri, 'bindInfos' => $bindInfos];
    }

    /**
     * Convierte un BMP en base64 a un data URI PNG usando la extensión GD.
     *
     * GD puede decodificar BMP vía imagecreatefromstring. Si GD no está
     * disponible o la imagen no es válida, devuelve null para que el llamador
     * use el BMP en crudo como fallback.
     *
     * @param string $base64 BMP codificado en base64 (sin encabezado data URI).
     * @return string|null  Data URI PNG o null si la conversión no es posible.
     */
    private function bmpBase64APng(string $base64): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }
        $binario = base64_decode($base64, true);
        if ($binario === false) {
            return null;
        }
        $img = @imagecreatefromstring($binario);
        if ($img === false) {
            return null;
        }
        ob_start();
        imagepng($img);
        imagedestroy($img);
        $png = ob_get_clean();
        return 'data:image/png;base64,' . base64_encode($png);
    }

    /**
     * Desempareja un conjunto de etiquetas ESL en ZKONG.
     *
     * Se envían en lotes de 2 000 para respetar el límite de la API.
     *
     * @param string[] $codigosEtiqueta Códigos de las etiquetas a desvincular.
     * @return void
     *
     * @throws \RuntimeException  Si la llamada a la API falla.
     */
    public function desemparejarEtiquetas(array $codigosEtiqueta): void
    {
        $this->asegurarToken();

        foreach (array_chunk($codigosEtiqueta, 2000) as $chunk) {
            $this->llamar('/zk/bind/batchUnbind', 'POST', [
                'storeId'      => (int) ($_SESSION['zkong_store_id'] ?? 0),
                'tagItemBinds' => array_map(fn($c) => ['eslBarcode' => $c], $chunk),
            ]);
        }
    }

    /**
     * Obtiene el estado y datos de las etiquetas ESL registradas en la tienda.
     *
     * @param int $pagina Índice de página (base 0).
     * @param int $tamano Número de items por página (máx. 200).
     * @return mixed  Array paginado con clave 'content' o array plano de items.
     *
     * @throws \RuntimeException  Si la llamada a la API falla.
     */
    public function obtenerEstadoEtiquetas(int $pagina = 0, int $tamano = 50): mixed
    {
        $this->asegurarToken();

        return $this->llamar(
            '/zk/erp/esl/list?page=' . $pagina . '&size=' . $tamano,
            'POST',
            [
                'storeId'      => (int) ($_SESSION['zkong_store_id'] ?? 0),
                'itemBarCode'  => '',
                'itemTitle'    => '',
                'priceTagCode' => '',
                'oemModel'     => '',
                'shelfNo'      => '',
            ]
        );
    }

    /**
     * Obtiene todas las etiquetas ESL vinculadas a productos desde el cloud ZKONG.
     *
     * Itera las páginas hasta obtener todas las etiquetas que tienen
     * itemBarCode no vacío. Devuelve un array plano con los campos
     * normalizados: codigo_etiqueta, barcode_articulo, nombre_articulo.
     *
     * @return array<int, array{codigo_etiqueta: string, barcode_articulo: string, nombre_articulo: string}>
     *
     * @throws \RuntimeException  Si la llamada a la API falla.
     */
    public function obtenerEtiquetasVinculadas(): array
    {
        $this->asegurarToken();
        $vinculadas = [];
        $pagina = 0;
        $tamano = 200;
        $limitePaginas = 10;

        while ($pagina < $limitePaginas) {
            $data = $this->llamar(
                '/zk/erp/esl/list?page=' . $pagina . '&size=' . $tamano,
                'POST',
                [
                    'storeId'      => (int) ($_SESSION['zkong_store_id'] ?? 0),
                    'itemBarCode'  => '',
                    'itemTitle'    => '',
                    'priceTagCode' => '',
                    'oemModel'     => '',
                    'shelfNo'      => '',
                ]
            );

            $lista = is_array($data) ? ($data['list'] ?? []) : [];

            foreach ($lista as $item) {
                $barcode = trim((string) ($item['itemBarCode'] ?? ''));
                if ($barcode === '') {
                    continue;
                }
                $vinculadas[] = [
                    'codigo_etiqueta'  => trim((string) ($item['priceTagCode'] ?? '')),
                    'barcode_articulo' => $barcode,
                    'nombre_articulo'  => trim((string) ($item['itemTitle'] ?? '')),
                ];
            }

            $total = (int) ($data['totalElements'] ?? 0);
            $obtenidas = ($pagina + 1) * $tamano;
            if ($obtenidas >= $total) {
                break;
            }
            $pagina++;
        }

        return $vinculadas;
    }

    /**
     * Obtiene el estado de las antenas (Access Points) de la tienda.
     *
     * @return mixed  Lista de APs con su estado online/offline y firmware.
     *
     * @throws \RuntimeException  Si la llamada a la API falla.
     */
    public function obtenerEstadoAntenas(): mixed
    {
        $this->asegurarToken();

        return $this->llamar(
            '/zk/erp/ap/list?storeId=' . ($_SESSION['zkong_store_id'] ?? ''),
            'POST',
            []
        );
    }

    /**
     * Sincroniza un aula en ZKONG (API token-based).
     *
     * Parámetros esperados: ['etiqueta_codigo'=>..., 'aula_nombre'=>..., 'aula_id'=>...]
     *
     * @param array<string, mixed> $payload Datos del aula a sincronizar.
     * @return mixed  Respuesta de la API.
     *
     * @throws \RuntimeException  Si la llamada a la API falla.
     */
    public function syncClassroom(array $payload): mixed
    {
        $this->asegurarToken();
        return $this->llamar('/zk/api/sync/classroom', 'POST', $payload);
    }

    /**
     * Garantiza que existe un token ZKONG válido en la sesión.
     *
     * Si el token falta o está vacío, ejecuta el flujo RSA de login.
     * Limita los reintentos a 3 para evitar bucles infinitos si el servidor
     * rechaza sistemáticamente las credenciales (p. ej. contraseña expirada).
     *
     * @return void
     *
     * @throws \RuntimeException  Si se superan 3 intentos de login consecutivos.
     */
    private function asegurarToken(): void
    {
        if (empty($_SESSION['zkong_token']) || empty($_SESSION['zkong_agency_id'])) {
            $intentos = (int) ($_SESSION['zkong_login_intentos'] ?? 0);
            if ($intentos >= 3) {
                throw new \RuntimeException('ZKONG: máximo de reintentos de login alcanzado (3). Cierra sesión e inténtalo de nuevo.');
            }
            $_SESSION['zkong_login_intentos'] = $intentos + 1;
            $this->login();
            $_SESSION['zkong_login_intentos'] = 0;
        }
    }

    /**
     * Ejecuta el flujo completo de autenticación RSA contra el cloud ZKONG.
     *
     * PASOS:
     *  1. GET /user/getErpPublicKey  → clave pública RSA en base64.
     *  2. Decodificar el base64, envolver en PEM y cifrar la contraseña con
     *     openssl_public_encrypt (PKCS#1).
     *  3. POST /user/login con account, loginType=3 y el password cifrado en base64.
     *  4. Guardar token, merchantId, storeId y agencyId en $_SESSION.
     *
     * Los IDs se guardan en sesión porque se necesitan en el payload de casi
     * todas las llamadas posteriores.
     *
     * @return void
     *
     * @throws \RuntimeException  Si no se puede obtener la clave pública o si el login falla.
     */
    private function login(): void
    {
        $clavePublica    = $this->obtenerClavePublica();
        $passwordCifrado = $this->cifrarPassword($clavePublica, $this->config['password']);

        $datos = $this->llamarCloud('/user/login', 'POST', [
            'account'   => $this->config['account'],
            'loginType' => 3,
            'password'  => $passwordCifrado,
        ], requiereToken: false);

        $usuario = is_array($datos['currentUser'] ?? null) ? $datos['currentUser'] : [];

        $_SESSION['zkong_token']       = (string) ($datos['token']                                           ?? '');
        $_SESSION['zkong_merchant_id'] = (string) ($usuario['merchantId'] ?? ($datos['merchantId']           ?? ''));
        $_SESSION['zkong_store_id']    = (string) ($datos['storeId']                                         ?? $this->config['store_id']);
        $_SESSION['zkong_agency_id']   = (string) ($usuario['agencyId']  ?? ($datos['agencyId']              ?? ''));
    }

    /**
     * Obtiene la clave pública RSA del servidor ZKONG.
     *
     * La clave viene como string base64 puro (sin cabeceras PEM). La conversión
     * a formato PEM se hace en cifrarPassword() antes de pasarla a OpenSSL.
     *
     * @return string  Clave pública RSA codificada en base64.
     *
     * @throws \RuntimeException  Si la respuesta no es un string válido.
     */
    private function obtenerClavePublica(): string
    {
        $data = $this->llamarCloud('/user/getErpPublicKey', 'GET', [], requiereToken: false);

        if (!is_string($data)) {
            throw new \RuntimeException('Respuesta inesperada al obtener clave pública ZKONG.');
        }

        return $data;
    }

    /**
     * Punto de acceso público al endpoint de la API ZKONG (base_url).
     *
     * Permite que código externo (scripts, tests) haga llamadas autenticadas
     * sin acceder directamente al método privado llamar().
     *
     * @param string $endpoint      Ruta de la API (p. ej. '/zk/item/list').
     * @param string $metodo        Método HTTP ('GET', 'POST', 'DELETE').
     * @param array  $datos         Cuerpo de la petición.
     * @param bool   $requiereToken Si true, añade Authorization a las cabeceras.
     * @return mixed  Contenido del campo 'data' de la respuesta.
     *
     * @throws \RuntimeException  Si la llamada falla.
     */
    public function callApiEndpoint(string $endpoint, string $metodo, array $datos = [], bool $requiereToken = true): mixed
    {
        return $this->llamar($endpoint, $metodo, $datos, $requiereToken);
    }

    /**
     * Punto de acceso público al endpoint del cloud ZKONG (cloud_url).
     *
     * @param string $endpoint      Ruta del cloud (p. ej. '/user/login').
     * @param string $metodo        Método HTTP.
     * @param array  $datos         Cuerpo de la petición.
     * @param bool   $requiereToken Si true, añade Authorization a las cabeceras.
     * @return mixed  Contenido del campo 'data' de la respuesta.
     *
     * @throws \RuntimeException  Si la llamada falla.
     */
    public function callCloudEndpoint(string $endpoint, string $metodo, array $datos = [], bool $requiereToken = true): mixed
    {
        return $this->llamarCloud($endpoint, $metodo, $datos, $requiereToken);
    }

    /**
     * Devuelve la URL base de la API sin barra final.
     *
     * @return string
     */
    private function getApiBaseUrl(): string
    {
        return rtrim($this->config['api_url'] ?? '', '/');
    }

    /**
     * Devuelve la URL base del cloud sin barra final.
     *
     * @return string
     */
    private function getCloudBaseUrl(): string
    {
        return rtrim($this->config['cloud_url'] ?? '', '/');
    }

    /**
     * Ejecuta una petición HTTP al cloud ZKONG (cloud_url) y parsea la respuesta.
     *
     * Idéntico en lógica a llamar() pero apunta a cloud_url en vez de base_url.
     * Se separan en dos métodos porque algunos endpoints (login, clave pública)
     * viven en el cloud mientras que los de operación (productos, etiquetas)
     * viven en base_url.
     *
     * En caso de token expirado (10041, 10042) o sesión desplazada (10013),
     * invalida la sesión ZKONG, hace re-login y reintenta la petición UNA vez
     * (esReintento=true) para evitar bucles.
     *
     * @param string $endpoint      Ruta del cloud.
     * @param string $metodo        Método HTTP.
     * @param array  $datos         Cuerpo de la petición.
     * @param bool   $requiereToken Si true, añade Authorization.
     * @param bool   $esReintento   true si es el segundo intento tras re-login.
     * @param int    $cantidad      Nº de items del lote (para el log).
     * @return mixed  Campo 'data' de la respuesta ZKONG.
     *
     * @throws \RuntimeException  En error de red o código de error no recuperable.
     */
    private function llamarCloud(
        string $endpoint,
        string $metodo,
        array  $datos,
        bool   $requiereToken = true,
        bool   $esReintento   = false,
        int    $cantidad      = 0
    ): mixed {
        $inicio = microtime(true);
        $url    = $this->getCloudBaseUrl() . $endpoint;

        $headers = ['Content-Type: application/json;charset=utf-8', 'Language: es'];
        if ($requiereToken) {
            $headers[] = 'Authorization: ' . ($_SESSION['zkong_token'] ?? '');
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL             => $url,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HTTPHEADER      => $headers,
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_MAXREDIRS       => 3,
            CURLOPT_POSTREDIR       => CURL_REDIR_POST_ALL,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST  => 0,
        ]);

        if ($metodo === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datos));
        } elseif ($metodo === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datos));
        }

        $respuesta   = curl_exec($ch);
        $codigoHttp  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errorCurl   = curl_error($ch);
        curl_close($ch);

        $duracionMs = (int) round((microtime(true) - $inicio) * 1000);

        if ($errorCurl) {
            $this->registrarLog($endpoint, $metodo, $cantidad, null, 'cURL: ' . $errorCurl, $duracionMs);
            throw new \RuntimeException('Error de conexión con ZKONG: ' . $errorCurl);
        }

        $json = $this->parsearRespuesta($respuesta ?? '');

        if ($json === null) {
            $resumen = 'HTTP ' . $codigoHttp . ' — sin respuesta parseable: ' . mb_substr($respuesta ?? '', 0, 300);
            $this->registrarLog($endpoint, $metodo, $cantidad, $codigoHttp, $resumen, $duracionMs);
            throw new \RuntimeException('ZKONG devolvió respuesta no válida. ' . $resumen);
        }

        $codigo  = (int)    ($json['code']    ?? 0);
        $mensaje = (string) ($json['message'] ?? '');

        $payloadDebug = !empty($datos) ? ' | payload: ' . json_encode($datos) : '';
        $this->registrarLog($endpoint, $metodo, $cantidad, $codigo, $mensaje . $payloadDebug, $duracionMs);

        if (!($json['success'] ?? false)) {
            // 10006 = sesión caducada; 10041/10042 = token expirado; 10013 = sesión desplazada por otro login
            if (!$esReintento && in_array($codigo, [10006, 10041, 10042, 10013], true)) {
                unset(
                    $_SESSION['zkong_token'],
                    $_SESSION['zkong_merchant_id'],
                    $_SESSION['zkong_store_id'],
                    $_SESSION['zkong_agency_id']
                );
                $this->login();
                return $this->llamarCloud($endpoint, $metodo, $datos, $requiereToken, true, $cantidad);
            }
            throw new \RuntimeException("ZKONG error {$codigo}: {$mensaje}");
        }

        return $json['data'] ?? null;
    }

    /**
     * Cifra la contraseña con la clave pública RSA del servidor ZKONG (PKCS#1).
     *
     * La clave llega en base64 puro (sin cabeceras PEM). Se convierte al formato
     * PEM estándar antes de pasarla a openssl_public_encrypt, que requiere ese
     * formato. El resultado se codifica en base64 para enviarlo en el payload JSON.
     *
     * @param string $clavePublicaBase64 Clave pública RSA en base64 (desde la API).
     * @param string $password           Contraseña en texto plano a cifrar.
     * @return string  Contraseña cifrada y codificada en base64.
     *
     * @throws \RuntimeException  Si el cifrado RSA falla.
     */
    private function cifrarPassword(string $clavePublicaBase64, string $password): string
    {
        $claveDecodificada = base64_decode($clavePublicaBase64);
        $clavePem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($claveDecodificada), 64, "\n")
            . "-----END PUBLIC KEY-----\n";

        $resultado = '';
        if (!openssl_public_encrypt($password, $resultado, $clavePem, OPENSSL_PKCS1_PADDING)) {
            throw new \RuntimeException('Error al cifrar password RSA: ' . openssl_error_string());
        }

        return base64_encode($resultado);
    }

    /**
     * Ejecuta una petición HTTP a la API ZKONG (base_url) y parsea la respuesta.
     *
     * Maneja automáticamente la renovación del token en caso de expiración
     * (códigos 10041, 10042) o desplazamiento de sesión (10013): invalida
     * los datos de sesión, re-autentica y reintenta la petición original UNA
     * vez (esReintento=true) para evitar bucles de login infinitos.
     *
     * @param string $endpoint      Ruta de la API (p. ej. '/zk/item/batchImportItem').
     * @param string $metodo        Método HTTP ('GET', 'POST', 'DELETE').
     * @param array  $datos         Cuerpo de la petición (se codifica como JSON).
     * @param bool   $requiereToken Si true, añade la cabecera Authorization con el token de sesión.
     * @param bool   $esReintento   true en el segundo intento tras re-login; evita bucles.
     * @param int    $cantidad      Número de items del lote, para el registro de log.
     * @return mixed  Campo 'data' de la respuesta ZKONG, o null si data no existe.
     *
     * @throws \RuntimeException  En error de red (cURL) o si ZKONG devuelve success=false
     *                             con un código no recuperable.
     */
    private function llamar(
        string $endpoint,
        string $metodo,
        array  $datos,
        bool   $requiereToken = true,
        bool   $esReintento   = false,
        int    $cantidad      = 0
    ): mixed {
        $inicio = microtime(true);
        $url    = rtrim($this->config['base_url'], '/') . $endpoint;

        $headers = ['Content-Type: application/json;charset=utf-8', 'Language: es'];
        if ($requiereToken) {
            $headers[] = 'Authorization: ' . ($_SESSION['zkong_token'] ?? '');
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL             => $url,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HTTPHEADER      => $headers,
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_MAXREDIRS       => 3,
            CURLOPT_POSTREDIR       => CURL_REDIR_POST_ALL,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST  => 0,
        ]);

        if ($metodo === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datos));
        } elseif ($metodo === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datos));
        }

        $respuesta   = curl_exec($ch);
        $codigoHttp  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errorCurl   = curl_error($ch);
        curl_close($ch);

        $duracionMs = (int) round((microtime(true) - $inicio) * 1000);

        if ($errorCurl) {
            $this->registrarLog($endpoint, $metodo, $cantidad, null, 'cURL: ' . $errorCurl, $duracionMs);
            throw new \RuntimeException('Error de conexión con ZKONG: ' . $errorCurl);
        }

        $json = $this->parsearRespuesta($respuesta ?? '');

        if ($json === null) {
            $resumen = 'HTTP ' . $codigoHttp . ' — sin respuesta parseable: ' . mb_substr($respuesta ?? '', 0, 300);
            $this->registrarLog($endpoint, $metodo, $cantidad, $codigoHttp, $resumen, $duracionMs);
            throw new \RuntimeException('ZKONG devolvió respuesta no válida. ' . $resumen);
        }

        $codigo  = (int)    ($json['code']    ?? 0);
        $mensaje = (string) ($json['message'] ?? '');

        $payloadDebug = !empty($datos) ? ' | payload: ' . json_encode($datos) : '';
        $this->registrarLog($endpoint, $metodo, $cantidad, $codigo, $mensaje . $payloadDebug, $duracionMs);

        if (!($json['success'] ?? false)) {
            // 10006 = sesión caducada; 10041/10042 = token expirado; 10013 = sesión desplazada por otro login
            if (!$esReintento && in_array($codigo, [10006, 10041, 10042, 10013], true)) {
                unset(
                    $_SESSION['zkong_token'],
                    $_SESSION['zkong_merchant_id'],
                    $_SESSION['zkong_store_id'],
                    $_SESSION['zkong_agency_id']
                );
                $this->login();
                return $this->llamar($endpoint, $metodo, $datos, $requiereToken, true, $cantidad);
            }
            throw new \RuntimeException("ZKONG error {$codigo}: {$mensaje}");
        }

        return $json['data'] ?? null;
    }

    /**
     * Parsea el cuerpo de la respuesta HTTP como JSON o como XML fallback.
     *
     * ZKONG devuelve siempre JSON en producción, pero algunas respuestas de
     * error del proxy/balanceador llegan como XML. Se intenta JSON primero y,
     * si falla, se convierte el XML a array normalizando los campos 'success'
     * (bool) y 'code' (int) para que el código de verificación sea uniforme.
     *
     * @param string $cuerpo Cuerpo de la respuesta HTTP.
     * @return array<string, mixed>|null  Array normalizado o null si no es parseable.
     */
    private function parsearRespuesta(string $cuerpo): ?array
    {
        if ($cuerpo === '') {
            return null;
        }

        $json = json_decode($cuerpo, true);
        if ($json !== null) {
            return $json;
        }

        $xml = @simplexml_load_string($cuerpo);
        if ($xml === false) {
            return null;
        }

        $array = json_decode(json_encode($xml), true);
        if (!is_array($array)) {
            return null;
        }

        if (isset($array['success'])) {
            $array['success'] = filter_var($array['success'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($array['code'])) {
            $array['code'] = (int) $array['code'];
        }

        return $array;
    }

    /**
     * Registra una llamada a la API ZKONG en la tabla logs_zkong.
     *
     * El fallo de log nunca interrumpe el flujo principal: si la BD no está
     * disponible en ese momento, el error se suprime para que la operación
     * ZKONG no falle por una causa secundaria.
     *
     * @param string  $endpoint  Ruta de la API llamada.
     * @param string  $metodo    Método HTTP utilizado.
     * @param int     $cantidad  Número de items del lote (0 si no aplica).
     * @param int|null    $codigo    Código de respuesta ZKONG (null en error de red).
     * @param string|null $mensaje   Mensaje de respuesta o descripción del error.
     * @param int     $duracion  Duración de la petición en milisegundos.
     * @return void
     */
    private function registrarLog(
        string  $endpoint,
        string  $metodo,
        int     $cantidad,
        ?int    $codigo,
        ?string $mensaje,
        int     $duracion
    ): void {
        try {
            $this->logDAO->insertar($endpoint, $metodo, $cantidad, $codigo, $mensaje, $duracion);
        } catch (\Throwable) {
            // El fallo de log no debe interrumpir el flujo principal
        }
    }
}
