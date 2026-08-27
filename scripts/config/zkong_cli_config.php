<?php
declare(strict_types=1);

// Uso:
// php zkong_config_cli.php show
// php zkong_config_cli.php set ZKONG_ACCOUNT=valor ZKONG_PASSWORD=valor ZKONG_BASE_URL=valor ZKONG_STORE_ID=valor ZKONG_AP_MAC=valor

function loadEnvFile(string $path): array
{
    if (!is_file($path)) return [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $data = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $data[trim($k)] = trim($v);
    }
    return $data;
}

function writeEnvFile(string $path, array $data): void
{
    $existing = [];
    if (is_file($path)) {
        $existing = file($path, FILE_IGNORE_NEW_LINES);
    }

    $keys = array_keys($data);
    $out = [];
    $seen = [];
    foreach ($existing as $line) {
        if (strpos(trim($line), '#') === 0) {
            $out[] = $line;
            continue;
        }
        if (!str_contains($line, '=')) {
            $out[] = $line;
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        if (in_array($k, $keys, true)) {
            $out[] = $k . '=' . $data[$k];
            $seen[$k] = true;
        } else {
            $out[] = $line;
        }
    }

    foreach ($data as $k => $v) {
        if (!isset($seen[$k])) {
            $out[] = $k . '=' . $v;
        }
    }

    file_put_contents($path, implode("\n", $out) . "\n");
}

$cwd = __DIR__;
$envPath = $cwd . DIRECTORY_SEPARATOR . '.env';

$argv = $_SERVER['argv'] ?? [];
array_shift($argv);

$cmd = $argv[0] ?? 'show';

if ($cmd === 'show') {
    $env = loadEnvFile($envPath);
    $keys = ['ZKONG_BASE_URL','ZKONG_ACCOUNT','ZKONG_PASSWORD','ZKONG_STORE_ID','ZKONG_AP_MAC','ZKONG_SSL_INSECURE'];
    echo "ZKONG config (from .env):\n";
    foreach ($keys as $k) {
        $v = $env[$k] ?? getenv($k) ?: '';
        if ($k === 'ZKONG_PASSWORD' && $v !== '') {
            $v = str_repeat('*', max(4, strlen($v)));
        }
        echo "$k=$v\n";
    }
    exit(0);
}

if ($cmd === 'set') {
    array_shift($argv);
    $toSet = [];
    foreach ($argv as $pair) {
        if (!str_contains($pair, '=')) continue;
        [$k, $v] = explode('=', $pair, 2);
        $toSet[trim($k)] = trim($v);
    }
    if (empty($toSet)) {
        echo "Nothing to set. Usage: php zkong_config_cli.php set ZKONG_ACCOUNT=val ...\n";
        exit(1);
    }
    $current = loadEnvFile($envPath);
    $merged = array_merge($current, $toSet);
    writeEnvFile($envPath, $merged);
    echo "Updated .env with provided keys.\n";
    exit(0);
}

echo "Unknown command. Use 'show' or 'set'.\n";
exit(1);
