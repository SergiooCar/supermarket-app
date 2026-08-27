<?php
/**
 * zkong_web_test.php
 * 
 * Endpoint web para probar la integración ZKONG desde el navegador
 * Acceso: php tests/zkong_web_test.php (CLI) o http://localhost/supermarket-app/scripts/testing/zkong_web_test.php (web)
 * 
 * Valida:
 * 1. Configuración leída de BD
 * 2. Conexión Basic Auth (/api/status)
 * 3. Obtención de clave pública
 * 4. Obtención de token
 * 5. Sincronización de aula
 * 6. Creación de plantilla
 */

// Cargar autoload, entorno y sesión
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
// Incluir funciones ZKONG extendidas
require_once dirname(__DIR__, 1) . '/utils/ZkongAPI.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->safeLoad();

session_start();


// HTML Header
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZKONG - Test Web</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: #333;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .content {
            padding: 30px;
        }
        
        .step {
            margin-bottom: 30px;
            border-left: 4px solid #667eea;
            padding-left: 20px;
            padding-top: 15px;
            padding-bottom: 15px;
        }
        
        .step.success {
            border-left-color: #4caf50;
            background: #f1f8f4;
        }
        
        .step.error {
            border-left-color: #f44336;
            background: #fef5f5;
        }
        
        .step.info {
            border-left-color: #2196f3;
            background: #f3f9ff;
        }
        
        .step-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .step-title .icon {
            font-size: 20px;
            min-width: 20px;
        }
        
        .step-content {
            font-size: 13px;
            line-height: 1.6;
            color: #666;
        }
        
        .step-content code {
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: #c7254e;
        }
        
        .step-content pre {
            background: #f5f5f5;
            padding: 12px;
            border-radius: 5px;
            overflow-x: auto;
            margin: 10px 0;
            font-size: 12px;
            border: 1px solid #ddd;
        }
        
        .summary {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .summary-item {
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            font-size: 13px;
        }
        
        .summary-item.ok {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #4caf50;
        }
        
        .summary-item.fail {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #f44336;
        }
        
        .summary-item strong {
            display: block;
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .footer {
            background: #f5f5f5;
            padding: 20px 30px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #999;
            text-align: center;
        }
        
        .button-group {
            text-align: center;
            margin-top: 30px;
        }
        
        .button-group a, .button-group button {
            display: inline-block;
            padding: 10px 20px;
            margin: 0 5px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            border: none;
            cursor: pointer;
        }
        
        .btn-reload {
            background: #667eea;
            color: white;
        }
        
        .btn-reload:hover {
            background: #5568d3;
        }
        
        .btn-home {
            background: #f0f0f0;
            color: #333;
        }
        
        .btn-home:hover {
            background: #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 ZKONG - Test Web</h1>
            <p>Validación de integración desde el navegador</p>
        </div>
        
        <div class="content">
            <?php
            
            $steps = [];
            $success_count = 0;
            $fail_count = 0;
            
            // ========================================
            // [1] LEER CONFIGURACIÓN
            // ========================================
            try {
                $config = getZkongConfig();
                
                if (!empty($config['usuario']) && !empty($config['storeId'])) {
                    $steps[] = [
                        'number' => 1,
                        'title' => 'Configuración',
                        'status' => 'success',
                        'message' => 'Configuración leída correctamente',
                        'details' => [
                            'Usuario: ' . $config['usuario'],
                            'StoreId: ' . $config['storeId'],
                            'ApiUrl: ' . $config['apiUrl'],
                            'CloudUrl: ' . $config['cloudUrl']
                        ]
                    ];
                    $success_count++;
                } else {
                    $steps[] = [
                        'number' => 1,
                        'title' => 'Configuración',
                        'status' => 'error',
                        'message' => 'Configuración incompleta en BD',
                        'details' => ['Verifica tabla zkong_config']
                    ];
                    $fail_count++;
                }
            } catch (Exception $e) {
                $steps[] = [
                    'number' => 1,
                    'title' => 'Configuración',
                    'status' => 'error',
                    'message' => 'Error: ' . $e->getMessage(),
                    'details' => []
                ];
                $fail_count++;
            }
            
            // ========================================
            // [2] PRUEBA CONEXIÓN /api/status
            // ========================================
            try {
                $result = verificarConexionZkong();
                
                if ($result['connected']) {
                    $steps[] = [
                        'number' => 2,
                        'title' => 'Conexión (Basic Auth)',
                        'status' => 'success',
                        'message' => 'Conexión exitosa a /api/status',
                        'details' => [
                            'HTTP Code: ' . $result['httpCode'],
                            'Mensaje: ' . $result['message']
                        ]
                    ];
                    $success_count++;
                } else {
                    $steps[] = [
                        'number' => 2,
                        'title' => 'Conexión (Basic Auth)',
                        'status' => 'error',
                        'message' => 'Error de conexión',
                        'details' => [
                            'HTTP Code: ' . $result['httpCode'],
                            'Mensaje: ' . $result['message'],
                            'Acción: Verifica credenciales en BD'
                        ]
                    ];
                    $fail_count++;
                }
            } catch (Exception $e) {
                $steps[] = [
                    'number' => 2,
                    'title' => 'Conexión (Basic Auth)',
                    'status' => 'error',
                    'message' => 'Excepción: ' . $e->getMessage(),
                    'details' => []
                ];
                $fail_count++;
            }
            
            // ========================================
            // [3] OBTENER CLAVE PÚBLICA
            // ========================================
            try {
                $publicKey = obtenerPublicKeyZkong();
                
                if ($publicKey) {
                    $steps[] = [
                        'number' => 3,
                        'title' => 'Clave Pública RSA',
                        'status' => 'success',
                        'message' => 'Clave pública obtenida',
                        'details' => [
                            'Longitud: ' . strlen($publicKey) . ' caracteres',
                            'Tipo: ' . (strpos($publicKey, 'BEGIN PUBLIC KEY') !== false ? 'PEM' : 'JSON')
                        ]
                    ];
                    $success_count++;
                } else {
                    global $zkongLastError;
                    $steps[] = [
                        'number' => 3,
                        'title' => 'Clave Pública RSA',
                        'status' => 'error',
                        'message' => 'No se pudo obtener clave pública',
                        'details' => [
                            'Error: ' . $zkongLastError,
                            'Acción: Verifica que /user/getErpPublicKey sea accesible'
                        ]
                    ];
                    $fail_count++;
                }
            } catch (Exception $e) {
                $steps[] = [
                    'number' => 3,
                    'title' => 'Clave Pública RSA',
                    'status' => 'error',
                    'message' => 'Excepción: ' . $e->getMessage(),
                    'details' => []
                ];
                $fail_count++;
            }
            
            // ========================================
            // [4] OBTENER TOKEN
            // ========================================
            $token = false;
            try {
                $token = obtenerTokenZkong();
                
                if ($token) {
                    $steps[] = [
                        'number' => 4,
                        'title' => 'Token Bearer',
                        'status' => 'success',
                        'message' => 'Token obtenido correctamente',
                        'details' => [
                            'Longitud: ' . strlen($token) . ' caracteres',
                            'Primeros 50 chars: ' . substr($token, 0, 50) . '...'
                        ]
                    ];
                    $success_count++;
                } else {
                    global $zkongLastError;
                    $steps[] = [
                        'number' => 4,
                        'title' => 'Token Bearer',
                        'status' => 'error',
                        'message' => 'No se pudo obtener token',
                        'details' => [
                            'Error: ' . $zkongLastError,
                            'Acción: Verifica que /user/login sea accesible'
                        ]
                    ];
                    $fail_count++;
                }
            } catch (Exception $e) {
                $steps[] = [
                    'number' => 4,
                    'title' => 'Token Bearer',
                    'status' => 'error',
                    'message' => 'Excepción: ' . $e->getMessage(),
                    'details' => []
                ];
                $fail_count++;
            }
            
            // ========================================
            // [5] SINCRONIZAR AULA (opcional)
            // ========================================
            try {
                $aulaTest = new stdClass();
                $aulaTest->id = 999;
                $aulaTest->etiqueta_codigo = "TEST_WEB_" . time();
                $aulaTest->nombre = "Aula de Prueba Web";
                
                $result = sincronizarAulaAZkong($aulaTest);
                
                if ($result['success']) {
                    $steps[] = [
                        'number' => 5,
                        'title' => 'Sincronizar Aula',
                        'status' => 'success',
                        'message' => 'Aula sincronizada correctamente',
                        'details' => [
                            'HTTP Code: ' . $result['httpCode'],
                            'Código etiqueta: ' . $aulaTest->etiqueta_codigo
                        ]
                    ];
                    $success_count++;
                } else {
                    $steps[] = [
                        'number' => 5,
                        'title' => 'Sincronizar Aula',
                        'status' => 'error',
                        'message' => 'Error de sincronización',
                        'details' => [
                            'Mensaje: ' . $result['message'],
                            'HTTP Code: ' . $result['httpCode']
                        ]
                    ];
                    $fail_count++;
                }
            } catch (Exception $e) {
                $steps[] = [
                    'number' => 5,
                    'title' => 'Sincronizar Aula',
                    'status' => 'error',
                    'message' => 'Excepción: ' . $e->getMessage(),
                    'details' => []
                ];
                $fail_count++;
            }
            
            // ========================================
            // [6] CREAR PLANTILLA (opcional, requiere token)
            // ========================================
            if ($token && $config) {
                try {
                    $result = crearPlantillaZkong(
                        $token,
                        $config['storeId'],
                        'Plantilla Test Web ' . date('Y-m-d H:i:s'),
                        json_encode(['test' => true, 'timestamp' => time()])
                    );
                    
                    if ($result['success']) {
                        $steps[] = [
                            'number' => 6,
                            'title' => 'Crear Plantilla',
                            'status' => 'success',
                            'message' => 'Plantilla creada correctamente',
                            'details' => [
                                'HTTP Code: ' . $result['httpCode'],
                                'Respuesta: ' . json_encode($result['data'])
                            ]
                        ];
                        $success_count++;
                    } else {
                        $steps[] = [
                            'number' => 6,
                            'title' => 'Crear Plantilla',
                            'status' => 'error',
                            'message' => 'Error creando plantilla',
                            'details' => [
                                'Error: ' . $result['error'],
                                'Acción: Verifica que /zk/api/template exista'
                            ]
                        ];
                        $fail_count++;
                    }
                } catch (Exception $e) {
                    $steps[] = [
                        'number' => 6,
                        'title' => 'Crear Plantilla',
                        'status' => 'error',
                        'message' => 'Excepción: ' . $e->getMessage(),
                        'details' => []
                    ];
                    $fail_count++;
                }
            } else {
                $steps[] = [
                    'number' => 6,
                    'title' => 'Crear Plantilla',
                    'status' => 'info',
                    'message' => 'Saltado (requiere token válido)',
                    'details' => ['Los pasos anteriores tuvieron problemas']
                ];
            }
            
            // ========================================
            // RENDER STEPS
            // ========================================
            foreach ($steps as $step) {
                $icon = match($step['status']) {
                    'success' => '✓',
                    'error' => '✗',
                    'info' => 'ℹ'
                };
                
                $step_number = str_pad($step['number'], 1, '0', STR_PAD_LEFT);
                ?>
                <div class="step <?php echo $step['status']; ?>">
                    <div class="step-title">
                        <span class="icon"><?php echo $icon; ?></span>
                        <span>[<?php echo $step_number; ?>] <?php echo $step['title']; ?></span>
                    </div>
                    <div class="step-content">
                        <strong><?php echo $step['message']; ?></strong>
                        <?php if (!empty($step['details'])): ?>
                            <pre><?php echo implode("\n", $step['details']); ?></pre>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            }
            
            // ========================================
            // SUMMARY
            // ========================================
            ?>
            
            <div class="summary">
                <strong>📊 Resumen</strong>
                <div class="summary-grid">
                    <div class="summary-item <?php echo $success_count > 0 ? 'ok' : 'fail'; ?>">
                        <strong><?php echo $success_count; ?></strong>
                        Exitosos
                    </div>
                    <div class="summary-item <?php echo $fail_count > 0 ? 'fail' : 'ok'; ?>">
                        <strong><?php echo $fail_count; ?></strong>
                        Fallos
                    </div>
                    <div class="summary-item <?php echo $success_count >= 4 ? 'ok' : 'fail'; ?>">
                        <strong><?php echo $success_count >= 4 ? '✓' : '✗'; ?></strong>
                        Estado General
                    </div>
                </div>
                
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 13px; color: #666;">
                    <?php if ($success_count >= 4): ?>
                        <strong style="color: #2e7d32;">✓ Tu app está correctamente conectada a ZKONG</strong>
                        <p style="margin-top: 10px;">Puedes usar las siguientes funciones en tus controladores:</p>
                        <pre>$token = obtenerTokenZkong();
$resultado = crearPlantillaZkong($token, $storeId, $nombre, $json);
$resultado = actualizarEtiquetaCompletaZkong($token, $storeId, $codigo, $datos);
$resultado = sincronizarAulaAZkong($aula);</pre>
                    <?php else: ?>
                        <strong style="color: #c62828;">✗ Hay problemas con la integración</strong>
                        <p style="margin-top: 10px;">Revisa los errores arriba y verifica:</p>
                        <ul style="margin-left: 20px;">
                            <li>Configuración en tabla <code>zkong_config</code></li>
                            <li>Credenciales correctas</li>
                            <li>Endpoints ZKONG accesibles</li>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="button-group">
                <button class="btn-reload" onclick="location.reload()">🔄 Recargar Test</button>
                <a href="/" class="btn-home">🏠 Volver al Inicio</a>
            </div>
        </div>
        
        <div class="footer">
            Test realizado: <?php echo date('Y-m-d H:i:s'); ?> | 
            Duración: ~<?php echo number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3); ?>s
        </div>
    </div>
</body>
</html>
