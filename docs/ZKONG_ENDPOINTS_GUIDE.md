# ZKONG - Guía Completa de Endpoints

## Resumen Ejecutivo

Este proyecto conecta con ZKONG Cloud usando **dos métodos de autenticación**:
1. **Basic Auth** para operaciones API simples (`/api/status`, `/api/sync/classroom`)
2. **Bearer Token** (JWT) para operaciones de plantillas y edición gráfica

---

## 1. Endpoints Exactos Usados

### 1.1 Verificar Conexión
- **URL**: `{apiUrl}/api/status`
- **Método**: `GET`
- **Autenticación**: HTTP Basic Auth (`usuario:password`)
- **Descripción**: Verifica que las credenciales son válidas
- **Respuesta esperada**: 2xx = OK, 4xx/5xx = Error

**Ejemplo en cURL**:
```bash
curl -i -u "iesbarajas-equipo4:TU_PASSWORD" \
  "https://etiquetas.ausiasmarch.net/zk/api/status"
```

---

### 1.2 Sincronizar Aula
- **URL**: `{apiUrl}/api/sync/classroom`
- **Método**: `POST`
- **Autenticación**: HTTP Basic Auth (`usuario:password`)
- **Headers**: `Content-Type: application/json`
- **Body JSON**:
  ```json
  {
    "etiqueta_codigo": "CODIGO123",
    "aula_nombre": "Aula 1",
    "aula_id": 42
  }
  ```
- **Respuesta esperada**: 2xx = OK, 4xx/5xx = Error

**Ejemplo en cURL**:
```bash
curl -i -u "iesbarajas-equipo4:TU_PASSWORD" \
  -H "Content-Type: application/json" \
  -X POST "https://etiquetas.ausiasmarch.net/zk/api/sync/classroom" \
  -d '{"etiqueta_codigo":"CODIGO123","aula_nombre":"Aula 1","aula_id":42}'
```

---

### 1.3 Obtener Clave Pública (para RSA login)
- **URL**: `{cloudUrl}/user/getErpPublicKey` o `{cloudUrl}/zk/user/getErpPublicKey`
- **Método**: `GET`
- **Autenticación**: NINGUNA (público)
- **Respuesta**: PEM pública o JSON con campo `publicKey`

**Ejemplo en cURL**:
```bash
curl -i "https://etiquetas.ausiasmarch.net/user/getErpPublicKey"
```

---

### 1.4 Login / Obtener Token
- **URL**: `{cloudUrl}/user/login` o `{cloudUrl}/zk/user/login`
- **Método**: `POST`
- **Autenticación**: HTTP Basic Auth (con credenciales) **O** JSON con RSA cifrado
- **Headers**: `Content-Type: application/json`
- **Body JSON** (depende de tu implementación; en este proyecto):
  ```json
  {}
  ```
- **Respuesta esperada**: 
  ```json
  {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "accessToken": "...",
    "access_token": "..."
  }
  ```

**Ejemplo en cURL** (con Basic Auth):
```bash
curl -i -u "iesbarajas-equipo4:TU_PASSWORD" \
  -H "Content-Type: application/json" \
  -X POST "https://etiquetas.ausiasmarch.net/zk/user/login" \
  -d '{}'
```

---

### 1.5 Crear Plantilla
- **URL**: `{cloudUrl}/zk/api/template`
- **Método**: `POST`
- **Autenticación**: `Authorization: Bearer <token>`
- **Headers**: `Content-Type: application/json`
- **Body JSON**:
  ```json
  {
    "name": "Plantilla aula A",
    "storeId": "1773057109137",
    "type": "label",
    "design": { /* JSON complejo */ },
    "active": true
  }
  ```
- **Respuesta esperada**: 2xx = Plantilla creada exitosamente

---

### 1.6 Actualizar Etiqueta (Diseño Gráfico)
- **URL**: `{cloudUrl}/zk/api/label/{etiqueta_codigo}`
- **Método**: `PUT`
- **Autenticación**: `Authorization: Bearer <token>`
- **Headers**: `Content-Type: application/json`
- **Body JSON**:
  ```json
  {
    "storeId": "1773057109137",
    "code": "CODIGO123",
    "data": {
      "nombre": "Aula A",
      "profesor": "Prof X",
      "horario": "9:00 Lun"
    },
    "updated": "2026-06-01 14:30:00"
  }
  ```
- **Respuesta esperada**: 2xx = Etiqueta actualizada

---

## 2. Variables de Configuración (BD: tabla `zkong_config`)

Claves almacenadas en la BD:
- `zkong_usuario` — usuario API (ej. `iesbarajas-equipo4`)
- `zkong_password` — contraseña API
- `zkong_storeId` — ID de tienda (ej. `1773057109137`)
- `zkong_api_url` — base URL para API (ej. `https://etiquetas.ausiasmarch.net/zk`)
- `zkong_cloud_url` — base URL para Cloud/UI (ej. `https://etiquetas.ausiasmarch.net`)

Se leen mediante `getZkongConfig()`:
```php
$cfg = getZkongConfig();
// Devuelve:
// ['usuario' => '...', 'password' => '...', 'storeId' => '...', 'apiUrl' => '...', 'cloudUrl' => '...']
```

---

## 3. Implementación en el Proyecto

### Archivos principales:
- `scripts/ZkongAPIExtended.php` — Nuevas funciones
- `public/index.php` — Incluye `ZkongAPIExtended.php`

### Flujo de uso desde el controlador:
```php
// Obtiene token
$token = obtenerTokenZkong();
$storeId = getZkongConfig()['storeId'];

// Crea plantilla
$resultado = crearPlantillaZkong($token, $storeId, $nombre, $diseno);

// O actualiza etiqueta
$resultado = actualizarEtiquetaCompletaZkong($token, $storeId, $codigo, $datos);
```

---

## 4. Debugging

### Script de debug:
```bash
php zkong_test_completo.php
```
Intenta:
- Leer configuración
- cURL a /api/status
- Obtener public key
- Obtener token
- Sincronizar aula
- Crear plantilla

### Prueba manual:
```bash
# Verificar conexión
curl -i -u "usuario:password" "https://etiquetas.ausiasmarch.net/zk/api/status"

# Obtener token
curl -i -u "usuario:password" -H "Content-Type: application/json" \
  -X POST "https://etiquetas.ausiasmarch.net/zk/user/login" -d '{}'
```

---

**Última actualización**: 01 Jun 2026  
**Archivos referenciados**: `scripts/ZkongAPI.php`, `scripts/ZkongAPIExtended.php`
