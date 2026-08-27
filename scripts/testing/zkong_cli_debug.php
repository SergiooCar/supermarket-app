<?php
declare(strict_types=1);

// Script de depuración para ZKONG - ejecuta desde el servidor:
// php zkong_debug.php

require dirname(__DIR__, 1) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Servicios\ZkongServicio;

session_start();

// Cargar .env si existe
Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();

$base    = $_ENV['ZKONG_BASE_URL'] ?? '';
$account = $_ENV['ZKONG_ACCOUNT']  ?? '';
$pass    = $_ENV['ZKONG_PASSWORD'] ?? '';

echo "ZKONG debug script\n";
echo "Base URL: {$base}\n";
echo "Account: {$account}\n";
echo "Password: (hidden, length=" . strlen($pass) . ")\n\n";

function curlGetWithBasic(string $url, string $user, string $pass): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERPWD        => $user . ':' . $pass,
        CURLOPT_HTTPGET        => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $body, 'error' => $err];
}

if ($base !== '') {
    $statusUrl = rtrim($base, '/') . '/api/status';
    echo "Probando endpoint (Basic Auth): {$statusUrl}\n";
    $res = curlGetWithBasic($statusUrl, $account, $pass);
    echo "HTTP: {$res['code']}\n";
    if ($res['error']) {
        echo "cURL error: {$res['error']}\n";
    }
    echo "Body:\n" . substr($res['body'] ?? '', 0, 2000) . "\n\n";
} else {
    echo "ZKONG_BASE_URL no configurada. Revisa .env o app/Config/zkong.php\n\n";
}

echo "Intentando login vía `ZkongServicio` (usa flujo RSA/login del SDK interno)\n";
try {
    $svc = new ZkongServicio();
    // Llamada que fuerza login y devuelve información (si falla se lanza excepción)
    $antenas = $svc->obtenerEstadoAntenas();
    echo "Llamada a obtenerEstadoAntenas() OK. Tipo de respuesta: " . gettype($antenas) . "\n";
    if (is_array($antenas)) {
        echo "Items devueltos (muestra parcial):\n" . json_encode(array_slice($antenas, 0, 5), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        var_export($antenas);
        echo "\n";
    }
} catch (Throwable $e) {
    echo "Excepción durante login/llamada Zkong: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\nFin debug.\n";
