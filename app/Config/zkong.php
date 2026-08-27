<?php

/**
 */

declare(strict_types=1);

/**
 * Configuración de la integración con el cloud ZKONG (ESL).
 *
 * Cargado por ZkongServicio::__construct() y por scripts/ZkongAPIExtended.php.
 * Todos los valores son obligatorios en producción; el fallo se detecta en el
 * arranque cuando $dotenv->required([...]) valida las variables en public/index.php.
 *
 * CAMPOS:
 *  - base_url:   URL base de la API ZKONG (incluye /zk en la ruta).
 *  - api_url:    URL específica de la API REST de operaciones (fallback a base_url/zk).
 *  - cloud_url:  URL del cloud de login y clave pública (fallback a base_url/zk).
 *  - account:    Cuenta ERP (email o usuario) para el login ZKONG.
 *  - password:   Contraseña en texto plano (se cifra con RSA antes de enviar).
 *  - store_id:   ID de la tienda en la plataforma ZKONG.
 *  - ap_mac:     MAC del Access Point por defecto para emparejamiento de etiquetas.
 *  - token_type: Tipo de autenticación ('token' por defecto).
 */
return [
    'base_url'   => $_ENV['ZKONG_BASE_URL']   ?? '',
    'api_url'    => $_ENV['ZKONG_API_URL']    ?? rtrim($_ENV['ZKONG_BASE_URL'] ?? '', '/') . '/zk',
    'cloud_url'  => $_ENV['ZKONG_CLOUD_URL']  ?? rtrim($_ENV['ZKONG_BASE_URL'] ?? '', '/') . '/zk',
    'account'    => $_ENV['ZKONG_ACCOUNT']    ?? '',
    'password'   => $_ENV['ZKONG_PASSWORD']   ?? '',
    'store_id'   => $_ENV['ZKONG_STORE_ID']   ?? '',
    'ap_mac'     => $_ENV['ZKONG_AP_MAC']     ?? '',
    'token_type' => $_ENV['ZKONG_TOKEN_TYPE'] ?? 'token',
];
