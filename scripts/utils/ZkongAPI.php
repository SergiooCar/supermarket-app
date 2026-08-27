<?php

/**
 *
 * Funciones procedurales de soporte para la integración ZKONG.
 *
 * Este fichero es cargado por public/index.php en cada petición y proporciona
 * funciones de bajo nivel para scripts de diagnóstico y casos de uso que no
 * pasan por ZkongServicio (tests manuales, herramientas CLI, plantillas de
 * etiquetas). No depende de ningún modelo ni de la BD.
 *
 * FLUJO RSA (idéntico al de ZkongServicio, aquí en versión procedimental):
 *  1. obtenerPublicKeyZkong()  → GET /user/getErpPublicKey → clave base64.
 *  2. Decodificar base64 → envolver en PEM → openssl_public_encrypt (PKCS#1).
 *  3. obtenerTokenZkong()      → POST /user/login con password cifrado → token JWT.
 */

declare(strict_types=1);

$zkongLastError = '';

/**
 * Devuelve la configuración ZKONG leída del entorno.
 *
 * Centraliza el acceso a $_ENV para que el resto de funciones no repitan
 * la lógica de fallback. apiUrl y cloudUrl usan la misma base porque el
 * servidor ZKONG expone ambas API bajo /zk del mismo host.
 *
 * @return array{usuario: string, password: string, storeId: string, apiUrl: string, cloudUrl: string}
 */
function getZkongConfig(): array
{
    $base = rtrim($_ENV['ZKONG_BASE_URL'] ?? 'https://etiquetas.ausiasmarch.net', '/');

    return [
        'usuario'  => $_ENV['ZKONG_ACCOUNT']  ?? '',
        'password' => $_ENV['ZKONG_PASSWORD'] ?? '',
        'storeId'  => $_ENV['ZKONG_STORE_ID'] ?? '',
        'apiUrl'   => $base . '/zk',
        'cloudUrl' => $base . '/zk',
    ];
}

/**
 * URL base de la API ZKONG sin barra final.
 *
 * @return string
 */
function getZkongBaseUrl(): string
{
    return rtrim(getZkongConfig()['apiUrl'], '/');
}

/**
 * Verifica que el servidor ZKONG sea alcanzable probando el endpoint público de clave RSA.
 *
 * Se usa /user/getErpPublicKey porque es el único endpoint que no requiere
 * autenticación y responde rápido. Un HTTP 200 con success=true confirma
 * que el servidor está operativo y que TLS funciona.
 *
 * @return array{connected: bool, httpCode: int, message: string}
 */
function verificarConexionZkong(): array
{
    global $zkongLastError;

    $config = getZkongConfig();
    $url    = rtrim($config['cloudUrl'], '/') . '/user/getErpPublicKey';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json;charset=utf-8', 'Language: es'],
    ]);

    $response  = curl_exec($ch);
    $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        $zkongLastError = 'cURL: ' . $curlError;
        return ['connected' => false, 'httpCode' => 0, 'message' => $zkongLastError];
    }

    $json = json_decode((string) $response, true);

    if ($httpCode === 200 && is_array($json) && ($json['success'] ?? false)) {
        return ['connected' => true, 'httpCode' => $httpCode, 'message' => 'Servidor ZKONG accesible'];
    }

    $zkongLastError = 'HTTP ' . $httpCode;
    return ['connected' => false, 'httpCode' => $httpCode, 'message' => $zkongLastError];
}

/**
 * Obtiene la clave pública RSA del servidor ZKONG (en base64 puro, sin cabeceras PEM).
 *
 * La clave pública se renueva periódicamente en el servidor ZKONG, por lo que
 * no se cachea: cada llamada a obtenerTokenZkong() la obtiene de nuevo.
 *
 * @return string|false Clave pública en base64 o false si la petición falla.
 */
function obtenerPublicKeyZkong(): string|false
{
    global $zkongLastError;

    $config = getZkongConfig();
    $url    = rtrim($config['cloudUrl'], '/') . '/user/getErpPublicKey';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json;charset=utf-8', 'Language: es'],
    ]);

    $response  = curl_exec($ch);
    $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        $zkongLastError = 'cURL: ' . $curlError;
        return false;
    }

    $json = json_decode((string) $response, true);

    if ($httpCode === 200 && is_array($json) && ($json['success'] ?? false)) {
        $data = $json['data'] ?? null;
        if (is_string($data) && $data !== '') {
            return $data;
        }
    }

    $zkongLastError = 'Respuesta inesperada (HTTP ' . $httpCode . '): ' . mb_substr((string) $response, 0, 200);
    return false;
}

/**
 * Obtiene un token de acceso ZKONG usando el flujo RSA completo.
 *
 * PASOS:
 *  1. Obtiene la clave pública RSA de /user/getErpPublicKey.
 *  2. Decodifica el base64 → envuelve en formato PEM → cifra la contraseña
 *     con openssl_public_encrypt (PKCS#1), necesario para que OpenSSL acepte
 *     la clave en crudo sin cabeceras PEM.
 *  3. Envía el password cifrado (base64) a /user/login con loginType=3 (ERP).
 *
 * Si la sesión PHP está activa, almacena el token y los IDs en $_SESSION para
 * que ZkongServicio los use en llamadas posteriores sin re-autenticar.
 *
 * @return string|false Token JWT de sesión o false si cualquier paso falla.
 */
function obtenerTokenZkong(): string|false
{
    global $zkongLastError;

    $config = getZkongConfig();

    if ($config['usuario'] === '' || $config['password'] === '') {
        $zkongLastError = 'Credenciales no configuradas en .env';
        return false;
    }

    // 1. Clave pública (base64)
    $clavePublicaB64 = obtenerPublicKeyZkong();
    if ($clavePublicaB64 === false) {
        return false;
    }

    // 2. Cifrar password con RSA PKCS1
    $binario = base64_decode($clavePublicaB64, true);
    if ($binario === false) {
        $zkongLastError = 'Clave pública con base64 inválido';
        return false;
    }

    // La clave llega sin cabeceras PEM; hay que envolverla antes de pasarla a OpenSSL
    $pem = "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($binario), 64, "\n")
        . "-----END PUBLIC KEY-----\n";

    $encrypted = '';
    if (!openssl_public_encrypt($config['password'], $encrypted, $pem, OPENSSL_PKCS1_PADDING)) {
        $zkongLastError = 'Error RSA: ' . openssl_error_string();
        return false;
    }

    $passwordCifrado = base64_encode($encrypted);

    // 3. Login
    $loginUrl = rtrim($config['cloudUrl'], '/') . '/user/login';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $loginUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'account'   => $config['usuario'],
            'loginType' => 3,
            'password'  => $passwordCifrado,
        ]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json;charset=utf-8', 'Language: es'],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        $zkongLastError = 'cURL login: ' . $curlError;
        return false;
    }

    $json = json_decode((string) $response, true);

    if (is_array($json) && ($json['success'] ?? false)) {
        $data    = $json['data'] ?? [];
        $usuario = is_array($data) && is_array($data['currentUser'] ?? null) ? $data['currentUser'] : [];
        $token   = is_array($data) ? ($data['token'] ?? '') : '';

        if (is_string($token) && $token !== '') {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['zkong_token']       = $token;
                $_SESSION['zkong_store_id']    = (string) (is_array($data) ? ($data['storeId']                                    ?? $config['storeId']) : $config['storeId']);
                $_SESSION['zkong_merchant_id'] = (string) ($usuario['merchantId'] ?? (is_array($data) ? ($data['merchantId'] ?? '') : ''));
                $_SESSION['zkong_agency_id']   = (string) ($usuario['agencyId']   ?? (is_array($data) ? ($data['agencyId']   ?? '') : ''));
            }
            return $token;
        }
    }

    $codigo  = is_array($json) ? ($json['code']    ?? 0) : 0;
    $mensaje = is_array($json) ? ($json['message'] ?? 'sin mensaje') : 'respuesta no JSON';
    $zkongLastError = "Login fallido (HTTP {$httpCode}, code {$codigo}): {$mensaje}";
    return false;
}

/**
 * Sincroniza un aula en ZKONG usando ZkongServicio.
 *
 * Wrapper procedimental sobre ZkongServicio::syncClassroom() para que
 * scripts y herramientas CLI puedan sincronizar aulas sin instanciar
 * el servicio directamente.
 *
 * @param object $aula Objeto con propiedades: id, etiqueta_codigo, nombre.
 * @return array{success: bool, message: string, httpCode: int, data?: mixed}
 */
function sincronizarAulaAZkong(object $aula): array
{
    try {
        $servicio  = new \App\Servicios\ZkongServicio();
        $resultado = $servicio->syncClassroom([
            'etiqueta_codigo' => isset($aula->etiqueta_codigo) ? (string) $aula->etiqueta_codigo : '',
            'aula_nombre'     => isset($aula->nombre)          ? (string) $aula->nombre          : '',
            'aula_id'         => isset($aula->id)              ? (int)    $aula->id               : 0,
        ]);
        return ['success' => true, 'message' => 'OK', 'httpCode' => 200, 'data' => $resultado];
    } catch (\Throwable $e) {
        return ['success' => false, 'message' => $e->getMessage(), 'httpCode' => 0];
    }
}

/**
 * Crea una plantilla de etiqueta en ZKONG Cloud.
 *
 * Intenta varias rutas de endpoint en orden porque la documentación de ZKONG
 * no es definitiva sobre cuál está activa en la versión de producción.
 * El primer HTTP 2xx se considera éxito.
 *
 * @param string $token             Token JWT obtenido con obtenerTokenZkong().
 * @param string $storeId           ID de tienda.
 * @param string $nombrePlantilla   Nombre de la plantilla.
 * @param string $datosPlantillaJson JSON con el diseño de la plantilla.
 * @return array{success: bool, message?: string, error?: string, data?: mixed, httpCode?: int}
 */
function crearPlantillaZkong(string $token, string $storeId, string $nombrePlantilla, string $datosPlantillaJson): array
{
    global $zkongLastError;

    if ($token === '' || $storeId === '' || $nombrePlantilla === '') {
        return ['success' => false, 'error' => 'token, storeId y nombre son requeridos'];
    }

    $config  = getZkongConfig();
    $baseUrl = rtrim($config['cloudUrl'], '/');
    $diseno  = json_decode($datosPlantillaJson, true);

    if (!is_array($diseno)) {
        $diseno = [];
    }

    $payload = [
        'name'    => $nombrePlantilla,
        'storeId' => $storeId,
        'type'    => 'label',
        'design'  => $diseno,
        'active'  => true,
    ];

    $headers = [
        'Content-Type: application/json;charset=utf-8',
        'Authorization: ' . $token,
        'Language: es',
    ];

    // Probar múltiples rutas porque la API ZKONG Cloud no documenta claramente cuál está activa
    foreach (['/zk/api/template', '/zk/api/templates', '/api/template'] as $path) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $baseUrl . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success'  => true,
                'message'  => 'Plantilla creada',
                'data'     => json_decode((string) $response, true),
                'httpCode' => $httpCode,
            ];
        }
    }

    $zkongLastError = 'No se pudo crear plantilla (múltiples rutas fallidas)';
    return ['success' => false, 'error' => $zkongLastError];
}

/**
 * Actualiza una etiqueta completa con datos gráficos en ZKONG Cloud.
 *
 * Intenta primero con PUT sobre el recurso específico y, si falla, con POST
 * genérico. El orden importa: PUT es más semánticamente correcto para una
 * actualización, pero no todos los entornos ZKONG lo tienen habilitado.
 *
 * @param string               $token          Token JWT.
 * @param string               $storeId        ID de tienda.
 * @param string               $etiquetaCodigo Código de la etiqueta ESL.
 * @param array<string, mixed> $datosEtiqueta  Datos del producto a mostrar.
 * @return array{success: bool, message?: string, error?: string, data?: mixed, httpCode?: int}
 */
function actualizarEtiquetaCompletaZkong(string $token, string $storeId, string $etiquetaCodigo, array $datosEtiqueta): array
{
    global $zkongLastError;

    if ($token === '' || $storeId === '' || $etiquetaCodigo === '') {
        return ['success' => false, 'error' => 'token, storeId y código son requeridos'];
    }

    $config  = getZkongConfig();
    $baseUrl = rtrim($config['cloudUrl'], '/');

    $payload = [
        'storeId' => $storeId,
        'code'    => $etiquetaCodigo,
        'data'    => $datosEtiqueta,
        'updated' => date('Y-m-d H:i:s'),
    ];

    $headers = [
        'Content-Type: application/json;charset=utf-8',
        'Authorization: ' . $token,
        'Language: es',
    ];

    $intentos = [
        [$baseUrl . '/zk/api/label/' . urlencode($etiquetaCodigo), 'PUT'],
        [$baseUrl . '/zk/api/label', 'POST'],
    ];

    foreach ($intentos as [$url, $method]) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success'  => true,
                'message'  => 'Etiqueta actualizada',
                'data'     => json_decode((string) $response, true),
                'httpCode' => $httpCode,
            ];
        }
    }

    $zkongLastError = 'No se pudo actualizar etiqueta (múltiples rutas fallidas)';
    return ['success' => false, 'error' => $zkongLastError];
}
