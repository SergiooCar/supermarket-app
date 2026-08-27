<?php
/**
 * zkong_test_completo.php
 * 
 * Script de prueba completo para verificar:
 * 1. Configuración leída correctamente
 * 2. Conexión Basic Auth (/api/status)
 * 3. Obtención de clave pública
 * 4. Obtención de token
 * 5. (Opcional) Sincronizar aula
 * 6. (Opcional) Crear plantilla
 * 
 * USO: php tests/zkong_test_completo.php
 */

// Incluir funciones ZKONG
require_once dirname(__DIR__, 1) . '/utils/ZkongAPI.php';

echo "========================================\n";
echo "ZKONG - TEST COMPLETO\n";
echo "========================================\n\n";

// ============================================
// 1. LEER CONFIGURACIÓN
// ============================================
echo "[1] Leyendo configuración desde BD...\n";
$config = getZkongConfig();

echo "  Usuario: " . $config['usuario'] . "\n";
echo "  StoreId: " . $config['storeId'] . "\n";
echo "  ApiUrl: " . $config['apiUrl'] . "\n";
echo "  CloudUrl: " . $config['cloudUrl'] . "\n";
echo "  ✓ Configuración leída\n\n";

// ============================================
// 2. PRUEBA /api/status CON BASIC AUTH
// ============================================
echo "[2] Probando conexión (Basic Auth) a /api/status...\n";
$statusResult = verificarConexionZkong();
if ($statusResult['connected']) {
    echo "  ✓ Conexión exitosa (HTTP " . $statusResult['httpCode'] . ")\n\n";
} else {
    echo "  ✗ Error de conexión: " . $statusResult['message'] . " (HTTP " . $statusResult['httpCode'] . ")\n";
    echo "  Verifica que las credenciales sean correctas.\n\n";
}

// ============================================
// 3. OBTENER CLAVE PÚBLICA
// ============================================
echo "[3] Obteniendo clave pública...\n";
$publicKey = obtenerPublicKeyZkong();
if ($publicKey) {
    echo "  ✓ Clave pública obtenida\n";
    echo "  Longitud: " . strlen($publicKey) . " caracteres\n";
    echo "  Primeras 50 caracteres: " . substr($publicKey, 0, 50) . "...\n\n";
} else {
    global $zkongLastError;
    echo "  ✗ Error: " . $zkongLastError . "\n";
    echo "  Verifica que /user/getErpPublicKey sea accesible.\n\n";
}

// ============================================
// 4. OBTENER TOKEN
// ============================================
echo "[4] Obteniendo token...\n";
$token = obtenerTokenZkong();
if ($token) {
    echo "  ✓ Token obtenido\n";
    echo "  Longitud: " . strlen($token) . " caracteres\n";
    echo "  Primeros 50 caracteres: " . substr($token, 0, 50) . "...\n\n";
} else {
    global $zkongLastError;
    echo "  ✗ Error: " . $zkongLastError . "\n";
    echo "  Verifica que /user/login sea accesible y devuelva token.\n\n";
}

// ============================================
// 5. PRUEBA SINCRONIZACIÓN (OPCIONAL)
// ============================================
echo "[5] (OPCIONAL) Probando sincronización de aula...\n";
// Crear un objeto aula falso para prueba
$aulaTest = new stdClass();
$aulaTest->id = 999;
$aulaTest->etiqueta_codigo = "TEST_SYNC_001";
$aulaTest->nombre = "Aula de Prueba";

$syncResult = sincronizarAulaAZkong($aulaTest);
if ($syncResult['success']) {
    echo "  ✓ Sincronización exitosa (HTTP " . $syncResult['httpCode'] . ")\n\n";
} else {
    echo "  ✗ Error de sincronización: " . $syncResult['message'] . " (HTTP " . $syncResult['httpCode'] . ")\n";
    echo "  Si es 404, el endpoint /api/sync/classroom no existe en tu servidor.\n\n";
}

// ============================================
// 6. PRUEBA CREAR PLANTILLA (OPCIONAL, REQUIERE TOKEN)
// ============================================
if ($token) {
    echo "[6] (OPCIONAL) Probando creación de plantilla...\n";
    $plantillaResult = crearPlantillaZkong(
        $token,
        $config['storeId'],
        'Plantilla de Prueba ' . date('Y-m-d H:i:s'),
        json_encode(['prueba' => true])
    );
    
    if ($plantillaResult['success']) {
        echo "  ✓ Plantilla creada (HTTP " . $plantillaResult['httpCode'] . ")\n";
        echo "  Respuesta: " . json_encode($plantillaResult['data']) . "\n\n";
    } else {
        echo "  ✗ Error al crear plantilla: " . $plantillaResult['error'] . "\n";
        echo "  Si es 404, el endpoint /zk/api/template no existe.\n";
        echo "  Si es 401, el token es inválido o expirado.\n\n";
    }
} else {
    echo "[6] Saltando prueba de plantilla (no hay token)\n\n";
}

// ============================================
// RESUMEN FINAL
// ============================================
echo "========================================\n";
echo "RESUMEN\n";
echo "========================================\n";
echo "✓ Configuración: OK\n";
echo ($statusResult['connected'] ? "✓" : "✗") . " Conexión Basic Auth: " . ($statusResult['connected'] ? "OK" : "FALLO") . "\n";
echo ($publicKey ? "✓" : "✗") . " Clave Pública: " . ($publicKey ? "OK" : "FALLO") . "\n";
echo ($token ? "✓" : "✗") . " Token: " . ($token ? "OK" : "FALLO") . "\n";
echo ($syncResult['success'] ? "✓" : "✗") . " Sincronización: " . ($syncResult['success'] ? "OK" : "N/A o FALLO") . "\n";

if ($statusResult['connected'] && $token) {
    echo "\n✓ Tu app debería poder conectar con ZKONG.\n";
} else {
    echo "\n✗ Hay problemas. Revisa los errores arriba.\n";
}

echo "\n========================================\n";
echo "FIN DEL TEST\n";
echo "========================================\n";
?>
