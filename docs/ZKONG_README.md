# Integración ZKONG - Guía Completa

## ¿Qué se añadió/modificó?

### Nuevos Archivos

1. **`scripts/ZkongAPIExtended.php`** — Funciones auxiliares para ZKONG
   - `getZkongConfig()` — Lee configuración de BD
   - `obtenerPublicKeyZkong()` — Obtiene clave pública RSA
   - `obtenerTokenZkong()` — Obtiene Bearer token via login
   - `crearPlantillaZkong($token, $storeId, $nombre, $json)` — Crea plantilla
   - `actualizarEtiquetaCompletaZkong($token, $storeId, $codigo, $datos)` — Actualiza etiqueta gráfica

2. **`zkong_test_completo.php`** — Script de pruebas
   - Verifica configuración leída
   - Prueba conexión Basic Auth
   - Obtiene clave pública
   - Obtiene token
   - Prueba sincronización (opcional)
   - Prueba creación plantilla (opcional)

### Archivos Modificados

1. **`public/index.php`**
   - Añadido: `require_once __DIR__ . "/../scripts/ZkongAPIExtended.php";`
   - Esto carga automáticamente todas las funciones nuevas

## Cómo Usar

### Opción 1: Probar desde PHP (recomendado para validar)

```bash
php zkong_test_completo.php
```

Esto:
- Lee configuración de la BD
- Prueba `/api/status` con Basic Auth
- Intenta obtener token
- Reporta estado de cada paso

### Opción 2: Usar desde tu App

En cualquier controlador o script:

```php
<?php
// Leer configuración
$cfg = getZkongConfig();
$usuario = $cfg['usuario'];
$storeId = $cfg['storeId'];

// Obtener token
$token = obtenerTokenZkong();
if (!$token) {
    die('Error: No se pudo obtener token');
}

// Crear plantilla
$resultado = crearPlantillaZkong(
    $token,
    $storeId,
    'Mi Plantilla',
    json_encode(['campo' => 'valor'])
);

if ($resultado['success']) {
    echo "Plantilla creada OK";
} else {
    echo "Error: " . $resultado['error'];
}

// O actualizar etiqueta
$resultado = actualizarEtiquetaCompletaZkong(
    $token,
    $storeId,
    'CODIGO_ETIQUETA',
    [
        'nombre' => 'Aula A',
        'profesor' => 'Prof X',
        'horario' => '9:00 Lun'
    ]
);
?>
```

### Opción 3: Sincronizar aula (ya estaba, pero confirmar que funciona)

```php
<?php
$aula = new stdClass();
$aula->id = 42;
$aula->etiqueta_codigo = "CODIGO123";
$aula->nombre = "Aula 1";

$resultado = sincronizarAulaAZkong($aula);
if ($resultado['success']) {
    echo "Sincronizado OK";
}
?>
```

## Endpoints Exactos Usados

| Operación | URL | Método | Autenticación |
|-----------|-----|--------|----------------|
| Verificar conexión | `{apiUrl}/api/status` | GET | Basic Auth |
| Sincronizar aula | `{apiUrl}/api/sync/classroom` | POST | Basic Auth |
| Obtener public key | `{cloudUrl}/user/getErpPublicKey` | GET | Ninguna |
| Login / Token | `{cloudUrl}/user/login` | POST | Basic Auth |
| Crear plantilla | `{cloudUrl}/zk/api/template` | POST | Bearer Token |
| Actualizar etiqueta | `{cloudUrl}/zk/api/label/{codigo}` | PUT | Bearer Token |

Donde:
- `{apiUrl}` = configuración `zkong_api_url` (ej. `https://etiquetas.ausiasmarch.net/zk`)
- `{cloudUrl}` = configuración `zkong_cloud_url` (ej. `https://etiquetas.ausiasmarch.net`)

## Configuración Necesaria (BD tabla `zkong_config`)

Claves que deben estar en la BD:
- `zkong_usuario` — usuario API
- `zkong_password` — contraseña API
- `zkong_storeId` — ID tienda
- `zkong_api_url` — URL base API
- `zkong_cloud_url` — URL base Cloud

Se pueden configurar mediante:
1. Directamente en BD:
   ```sql
   INSERT INTO zkong_config (clave, valor) VALUES ('zkong_usuario', 'tu_usuario');
   INSERT INTO zkong_config (clave, valor) VALUES ('zkong_password', 'tu_password');
   // ...
   ```

2. O desde la app (si tienes UI):
   ```
   POST ?action=guardarConfigZkong&usuario=...&password=...&storeId=...
   ```

## Debugging

### Si algo falla:

1. **Ejecuta el test completo**:
   ```bash
   php zkong_test_completo.php
   ```

2. **Revisa errores globales**:
   ```php
   global $zkongLastError;
   echo $zkongLastError; // Mensaje del último error
   ```

3. **Prueba cURL manual**:
   ```bash
   # Verificar conexión
   curl -i -u "usuario:password" "https://etiquetas.ausiasmarch.net/zk/api/status"
   
   # Sincronizar aula
   curl -i -u "usuario:password" -H "Content-Type: application/json" \
     -X POST "https://etiquetas.ausiasmarch.net/zk/api/sync/classroom" \
     -d '{"etiqueta_codigo":"TEST","aula_nombre":"Test","aula_id":1}'
   ```

4. **Revisa logs del servidor**:
   - Apache/nginx: `/var/log/apache2/error.log` o equivalente
   - PHP: si tienes error_log configurado

## Notas Importantes

- **Token**: Obtenido de `/user/login`, válido para operaciones token-based (plantillas, etiquetas).
- **Basic Auth**: Usuario:contraseña, válido para `/api/status` y `/api/sync/classroom`.
- **Múltiples intentos**: Las funciones nuevas prueban múltiples URLs (ej. `/zk/user/login` y `/user/login`) para mayor compatibilidad.
- **SSL**: En producción asegúrate de usar certificados válidos. El proyecto desactiva verificación SSL solo para debug.
- **Timeouts**: Configurados a 5-10 segundos; ajusta si tu servidor es lento.

## Secuencia Recomendada para Implementar

1. **Paso 1**: Copia `scripts/ZkongAPIExtended.php` a tu proyecto
2. **Paso 2**: Actualiza `public/index.php` para incluir el nuevo archivo
3. **Paso 3**: Ejecuta `php zkong_test_completo.php` para validar
4. **Paso 4**: Si test pasa, usa las nuevas funciones en tu controlador
5. **Paso 5**: Si algo falla, consulta `ZKONG_QUICK_REFERENCE.md` para detalles exactos

## Archivos Referenciados

- `scripts/ZkongAPI.php` — Funciones originales (status, sync)
- `scripts/ZkongAPIExtended.php` — Nuevas funciones (token, plantilla, etiqueta)
- `controllers/ZkongController.php` — Controlador que usa todas las funciones
- `models/ZkongConfig.php` — DAO para persister configuración en BD
- `ZKONG_QUICK_REFERENCE.md` — Referencia rápida de endpoints

## Preguntas Frecuentes

**P: ¿Qué pasa si `/api/status` falla con 404?**
A: El servidor ZKONG no tiene ese endpoint. Prueba `/zk/api/status` o consulta la documentación exacta de tu proveedor.

**P: ¿Qué pasa si `/user/login` devuelve 401?**
A: Credenciales incorrectas o usuario/contraseña no configurados en BD.

**P: ¿Cómo sé qué formato usa el payload de plantilla?**
A: Ejecuta `php zkong_test_completo.php` en el paso 6; verás la respuesta exacta del servidor.

**P: ¿Puedo usar Basic Auth para plantillas?**
A: No. El proyecto asume que plantillas requieren token Bearer. Si tu servidor acepta Basic Auth, ajusta la función.

**P: ¿Dónde se guarda el token?**
A: En `$_SESSION['zkong_token']` si se usa desde controlador; en variables locales si se llama directo desde script.

---

**Última actualización**: 01 Jun 2026  
**Versión**: 1.0
