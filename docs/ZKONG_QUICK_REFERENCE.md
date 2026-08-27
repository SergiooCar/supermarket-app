# ZKONG - Referencia Rápida de Endpoints

## Tabla de Endpoints

| # | Operación | URL | Método | Auth | Payload | Respuesta |
|----|-----------|-----|--------|------|---------|-----------|
| 1 | Status | `{api}/api/status` | GET | Basic | — | `200 OK` o `401/404` |
| 2 | Sync Aula | `{api}/api/sync/classroom` | POST | Basic | `{"etiqueta_codigo":"...", "aula_nombre":"...", "aula_id":...}` | `200 OK` |
| 3 | Public Key | `{cloud}/user/getErpPublicKey` | GET | — | — | PEM o `{"publicKey":"..."}` |
| 4 | Login | `{cloud}/user/login` | POST | Basic | `{}` | `{"token":"..."}` |
| 5 | Template | `{cloud}/zk/api/template` | POST | Bearer | `{"name":"...", "storeId":"...", "type":"label", "design":{...}, "active":true}` | `200 OK` |
| 6 | Label Update | `{cloud}/zk/api/label/{code}` | PUT | Bearer | `{"storeId":"...", "code":"...", "data":{...}, "updated":"..."}` | `200 OK` |

### Leyenda
- `{api}` = `https://etiquetas.ausiasmarch.net/zk`
- `{cloud}` = `https://etiquetas.ausiasmarch.net`
- **Basic** = `curl -u usuario:password`
- **Bearer** = `curl -H "Authorization: Bearer token"`

---

## Payloads Exactos

### Sincronizar Aula (POST `/api/sync/classroom`)
```json
{
  "etiqueta_codigo": "CODIGO123",
  "aula_nombre": "Aula 1",
  "aula_id": 42
}
```

### Crear Plantilla (POST `/zk/api/template`)
```json
{
  "name": "Plantilla aula A",
  "storeId": "1773057109137",
  "type": "label",
  "design": {
    /* JSON complejo con elementos gráficos */
  },
  "active": true
}
```

### Actualizar Etiqueta (PUT `/zk/api/label/{codigo}`)
```json
{
  "storeId": "1773057109137",
  "code": "CODIGO123",
  "data": {
    "nombre": "Aula A",
    "profesor": "Prof X",
    "horario": "9:00 Lun",
    "textoSuperior": "Aula A",
    "textoInferior": "9:00 Lun"
  },
  "updated": "2026-06-01 14:30:00"
}
```

---

## Ejemplos cURL

### 1. Verificar conexión
```bash
curl -i -u "iesbarajas-equipo4:PASSWORD" \
  "https://etiquetas.ausiasmarch.net/zk/api/status"
```

### 2. Sincronizar aula
```bash
curl -i -u "iesbarajas-equipo4:PASSWORD" \
  -H "Content-Type: application/json" \
  -X POST "https://etiquetas.ausiasmarch.net/zk/api/sync/classroom" \
  -d '{"etiqueta_codigo":"TEST","aula_nombre":"Test","aula_id":1}'
```

### 3. Obtener public key
```bash
curl -i "https://etiquetas.ausiasmarch.net/user/getErpPublicKey"
```

### 4. Login (obtener token)
```bash
curl -i -u "iesbarajas-equipo4:PASSWORD" \
  -H "Content-Type: application/json" \
  -X POST "https://etiquetas.ausiasmarch.net/zk/user/login" \
  -d '{}'
```
Respuesta:
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "expiresIn": 3600,
  ...
}
```

### 5. Crear plantilla (con Bearer token)
```bash
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGc..."

curl -i -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -X POST "https://etiquetas.ausiasmarch.net/zk/api/template" \
  -d '{"name":"Template","storeId":"1773057109137","type":"label","design":{},"active":true}'
```

### 6. Actualizar etiqueta (con Bearer token)
```bash
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGc..."

curl -i -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -X PUT "https://etiquetas.ausiasmarch.net/zk/api/label/CODIGO123" \
  -d '{
    "storeId":"1773057109137",
    "code":"CODIGO123",
    "data":{"nombre":"Aula A","profesor":"Prof X"},
    "updated":"2026-06-01 14:30:00"
  }'
```

---

## Funciones PHP Disponibles

```php
// Obtener configuración
$cfg = getZkongConfig();

// Verificar conexión (Basic Auth)
$result = verificarConexionZkong();

// Sincronizar aula (Basic Auth)
$result = sincronizarAulaAZkong($aulaObj);

// Obtener token
$token = obtenerTokenZkong();

// Obtener public key
$pubKey = obtenerPublicKeyZkong();

// Crear plantilla (Bearer token)
$result = crearPlantillaZkong($token, $storeId, $nombre, $json);

// Actualizar etiqueta (Bearer token)
$result = actualizarEtiquetaCompletaZkong($token, $storeId, $codigo, $datos);
```

---

## Códigos HTTP Esperados

| Código | Significado | Acción |
|--------|-------------|--------|
| 200 | OK | Operación exitosa |
| 201 | Created | Recurso creado (plantilla) |
| 400 | Bad Request | Payload inválido |
| 401 | Unauthorized | Credenciales o token inválido |
| 404 | Not Found | Endpoint no existe o recurso no existe |
| 500 | Server Error | Error en servidor ZKONG |

---

## Configuración en BD

```sql
-- Verificar que existen estas claves
SELECT clave, valor FROM zkong_config 
WHERE clave IN ('zkong_usuario', 'zkong_password', 'zkong_storeId', 'zkong_api_url', 'zkong_cloud_url');

-- Si faltan, insertar:
INSERT INTO zkong_config (clave, valor) VALUES ('zkong_usuario', 'iesbarajas-equipo4');
INSERT INTO zkong_config (clave, valor) VALUES ('zkong_password', 'TU_PASSWORD');
INSERT INTO zkong_config (clave, valor) VALUES ('zkong_storeId', '1773057109137');
INSERT INTO zkong_config (clave, valor) VALUES ('zkong_api_url', 'https://etiquetas.ausiasmarch.net/zk');
INSERT INTO zkong_config (clave, valor) VALUES ('zkong_cloud_url', 'https://etiquetas.ausiasmarch.net');
```

---

## Test Rápido

```bash
php zkong_test_completo.php
```

Valida:
- ✓ Configuración
- ✓ Conexión Basic Auth
- ✓ Clave pública
- ✓ Token
- ✓ Sincronización (opcional)
- ✓ Plantilla (opcional)

---

**Hecho**: 01 Jun 2026  
**Proyecto**: Supermarket App - ZKONG Integration
