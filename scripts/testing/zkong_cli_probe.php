<?php
declare(strict_types=1);

require dirname(__DIR__, 1) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Servicios\ZkongServicio;

session_start();

// Cargar .env si existe
Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();

function curlRequest(string $url, string $method = 'GET', array $headers = [], ?string $body = null): array
{
    $ch = curl_init();
    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ];

    if (!empty($headers)) {
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = $body;
    }

    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    return [
        'http'  => $info['http_code'] ?? 0,
        'body'  => $body,
        'error' => $err,
    ];
}

$service = new ZkongServicio();

echo "=== ZKONG Probe ===\n";

try {
    $antenna = $service->obtenerEstadoAntenas();
    echo "Login/token flow OK. Token present: " . (!empty($_SESSION['zkong_token']) ? 'yes' : 'no') . "\n";
    echo "Store ID: " . ($_SESSION['zkong_store_id'] ?? 'none') . "\n";
    echo "Merchant ID: " . ($_SESSION['zkong_merchant_id'] ?? 'none') . "\n";
    echo "Agency ID: " . ($_SESSION['zkong_agency_id'] ?? 'none') . "\n";
} catch (Throwable $e) {
    echo "Login/token flow failed: " . $e->getMessage() . "\n";
    exit(1);
}

$payload = json_encode([
    'etiqueta_codigo' => 'PROBE123',
    'aula_nombre' => 'Probe Aula',
    'aula_id' => 999,
]);

$base = rtrim((require dirname(__DIR__, 1) . '/app/Config/zkong.php')['api_url'] ?? '', '/');
$cloud = rtrim((require dirname(__DIR__, 1) . '/app/Config/zkong.php')['cloud_url'] ?? '', '/');
$token = $_SESSION['zkong_token'] ?? '';

$tests = [
    [
        'name' => 'API status with token',
        'url' => $base . '/api/status',
        'method' => 'GET',
        'headers' => ["Authorization: {$token}", 'Accept: application/json'],
    ],
    [
        'name' => 'API sync classroom with token',
        'url' => $base . '/api/sync/classroom',
        'method' => 'POST',
        'headers' => ["Authorization: {$token}", 'Content-Type: application/json'],
        'body' => $payload,
    ],
    [
        'name' => 'API sync classroom with Basic Auth',
        'url' => $base . '/api/sync/classroom',
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => $payload,
        'basic' => true,
    ],
    [
        'name' => 'Cloud public key',
        'url' => $cloud . '/user/getErpPublicKey',
        'method' => 'GET',
    ],
];

// Candidate template endpoints to help detect the correct route.
$candidateTemplateEndpoints = [
    '/zk/api/template',
    '/zk/api/template/create',
    '/zk/api/template/update',
    '/zk/api/label/create',
    '/zk/api/label/update',
    '/zk/api/label/complete/update',
    '/zk/api/resource/template',
    '/zk/api/graphics/template',
];

foreach ($candidateTemplateEndpoints as $endpoint) {
    $tests[] = [
        'name' => "Template candidate: {$endpoint}",
        'url' => $base . $endpoint,
        'method' => 'POST',
        'headers' => ["Authorization: {$token}", 'Content-Type: application/json'],
        'body' => json_encode(['storeId' => $_SESSION['zkong_store_id'] ?? '', 'name' => 'Probe', 'design' => ['probe' => true]]),
    ];
}

foreach ($tests as $test) {
    echo "\n---\n";
    echo "Test: {$test['name']}\n";
    echo "URL: {$test['url']}\n";
    if (!empty($test['method'])) {
        echo "Method: {$test['method']}\n";
    }
    if (!empty($test['basic']) && $test['basic']) {
        $credentials = sprintf('%s:%s', getenv('ZKONG_ACCOUNT') ?: '', getenv('ZKONG_PASSWORD') ?: '');
        $test['headers'][] = 'Authorization: Basic ' . base64_encode($credentials);
    }
    $result = curlRequest($test['url'], $test['method'] ?? 'GET', $test['headers'] ?? [], $test['body'] ?? null);
    echo "HTTP: {$result['http']}\n";
    if ($result['error']) {
        echo "Error: {$result['error']}\n";
    }
    echo "Body: " . substr((string)$result['body'], 0, 1200) . "\n";
}

echo "\nProbe completo. Revisa las rutas que respondan 200/201.\n";
