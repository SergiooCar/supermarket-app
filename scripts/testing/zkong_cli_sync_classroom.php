<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Servicios\ZkongServicio;

session_start();

// Cargar .env si existe
Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();

$argv = $_SERVER['argv'] ?? [];
array_shift($argv);

$payload = [
    'etiqueta_codigo' => $argv[0] ?? 'TEST123',
    'aula_nombre'     => $argv[1] ?? 'Aula prueba',
    'aula_id'         => isset($argv[2]) ? (int)$argv[2] : 42,
];

echo "Invocando syncClassroom con payload: " . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n";

try {
    $svc = new ZkongServicio();
    // Forzar login y establecer token en session
    $svc->obtenerEstadoAntenas();
    $token = $_SESSION['zkong_token'] ?? '';
    echo "Token obtenido (parcial): " . substr($token, 0, 20) . "...\n";

    $ch = curl_init();
    $svcConfig = require dirname(__DIR__, 1) . '/app/Config/zkong.php';
    $base = $svcConfig['base_url'] ?? '';
    $endpoints = ['/zk/api/sync/classroom', '/api/sync/classroom'];
    $json = json_encode($payload);
    foreach ($endpoints as $ep) {
        $url = rtrim($base, '/') . $ep;
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: ' . $token,
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        echo "\nEndpoint: $url\n";
        echo "HTTP: $http\n";
        if ($err) {
            echo "cURL error: $err\n";
        }
        echo "Body raw:\n" . substr($body ?? '', 0, 4000) . "\n";
    }
    curl_close($ch);
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}


