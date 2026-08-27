# ZKONG - Respuestas de Ejemplo

## Respuestas HTTP Esperadas

### 1. GET `/api/status` (Basic Auth)

**Solicitud:**
```bash
curl -i -u "usuario:password" "https://etiquetas.ausiasmarch.net/zk/api/status"
```

**Respuesta exitosa (HTTP 200):**
```
HTTP/1.1 200 OK
Content-Type: application/json
Content-Length: 42

{"status":"ok","message":"Service is running"}
```

**Respuesta fallida (HTTP 401):**
```
HTTP/1.1 401 Unauthorized
Content-Type: application/json

{"error":"Invalid credentials"}
```

**Respuesta fallida (HTTP 404):**
```
HTTP/1.1 404 Not Found
Content-Type: application/json

{"error":"Endpoint not found"}
```

---

### 2. POST `/api/sync/classroom` (Basic Auth)

**Solicitud:**
```bash
curl -i -u "usuario:password" \
  -H "Content-Type: application/json" \
  -X POST "https://etiquetas.ausiasmarch.net/zk/api/sync/classroom" \
  -d '{"etiqueta_codigo":"AULA001","aula_nombre":"Aula 1","aula_id":42}'
```

**Respuesta exitosa (HTTP 200):**
```
HTTP/1.1 200 OK
Content-Type: application/json

{
  "success": true,
  "message": "Classroom synchronized successfully",
  "data": {
    "aulaId": 42,
    "etiquetaCodigo": "AULA001",
    "timestamp": "2026-06-01T14:30:00Z"
  }
}
```

**Respuesta fallida (HTTP 401):**
```
HTTP/1.1 401 Unauthorized
Content-Type: application/json

{"error":"Unauthorized","message":"Invalid credentials"}
```

**Respuesta fallida (HTTP 400):**
```
HTTP/1.1 400 Bad Request
Content-Type: application/json

{"error":"Bad Request","message":"Missing required field: etiqueta_codigo"}
```

---

### 3. GET `/user/getErpPublicKey` (Sin auth)

**Solicitud:**
```bash
curl -i "https://etiquetas.ausiasmarch.net/user/getErpPublicKey"
```

**Respuesta exitosa - Formato PEM (HTTP 200):**
```
HTTP/1.1 200 OK
Content-Type: text/plain

-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA2Z3qX2BTLS7ZQXQ5V8f4
...base64 encoded key...
-----END PUBLIC KEY-----
```

**Respuesta exitosa - Formato JSON (HTTP 200):**
```
HTTP/1.1 200 OK
Content-Type: application/json

{
  "publicKey": "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgk...\n-----END PUBLIC KEY-----"
}
```

**Respuesta fallida (HTTP 404):**
```
HTTP/1.1 404 Not Found
Content-Type: application/json

{"error":"Endpoint not found"}
```

---

### 4. POST `/user/login` (Basic Auth)

**Solicitud:**
```bash
curl -i -u "usuario:password" \
  -H "Content-Type: application/json" \
  -X POST "https://etiquetas.ausiasmarch.net/zk/user/login" \
  -d '{}'
```

**Respuesta exitosa (HTTP 200):**
```
HTTP/1.1 200 OK
Content-Type: application/json

{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOiJpZXNiYXJhamFzLWVxdWlwbzQiLCJpYXQiOjE2ODk4NDMyMDAsImV4cCI6MTY4OTg0NjgwMH0.kgQhNqVuZMpKxXqU6L0VYZvqVzxLl8ZrV2FQhKqE9Ac",
  "expiresIn": 3600,
  "tokenType": "Bearer",
  "user": {
    "id": "user123",
    "username": "iesbarajas-equipo4",
    "role": "admin"
  }
}
```

**Respuesta fallida (HTTP 401):**
```
HTTP/1.1 401 Unauthorized
Content-Type: application/json

{"error":"Invalid credentials","message":"Username or password is incorrect"}
```

---

### 5. POST `/zk/api/template` (Bearer Token)

**Solicitud:**
```bash
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGc..."

curl -i -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -X POST "https://etiquetas.ausiasmarch.net/zk/api/template" \
  -d '{
    "name": "Plantilla Aula A",
    "storeId": "1773057109137",
    "type": "label",
    "design": {},
    "active": true
  }'
```

**Respuesta exitosa (HTTP 200/201):**
```
HTTP/1.1 201 Created
Content-Type: application/json
Location: /zk/api/template/template_id_123

{
  "success": true,
  "message": "Template created successfully",
  "data": {
    "id": "template_id_123",
    "name": "Plantilla Aula A",
    "storeId": "1773057109137",
    "type": "label",
    "createdAt": "2026-06-01T14:30:00Z"
  }
}
```

**Respuesta fallida (HTTP 401):**
```
HTTP/1.1 401 Unauthorized
Content-Type: application/json

{"error":"Unauthorized","message":"Token is expired or invalid"}
```

**Respuesta fallida (HTTP 404):**
```
HTTP/1.1 404 Not Found
Content-Type: application/json

{"error":"Not Found","message":"Endpoint /zk/api/template does not exist"}
```

---

### 6. PUT `/zk/api/label/{codigo}` (Bearer Token)

**Solicitud:**
```bash
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGc..."

curl -i -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -X PUT "https://etiquetas.ausiasmarch.net/zk/api/label/AULA001" \
  -d '{
    "storeId": "1773057109137",
    "code": "AULA001",
    "data": {
      "nombre": "Aula 1",
      "profesor": "Dr. García",
      "horario": "09:00 Lun"
    },
    "updated": "2026-06-01T14:30:00Z"
  }'
```

**Respuesta exitosa (HTTP 200):**
```
HTTP/1.1 200 OK
Content-Type: application/json

{
  "success": true,
  "message": "Label updated successfully",
  "data": {
    "code": "AULA001",
    "storeId": "1773057109137",
    "updatedAt": "2026-06-01T14:30:00Z"
  }
}
```

**Respuesta fallida (HTTP 401):**
```
HTTP/1.1 401 Unauthorized
Content-Type: application/json

{"error":"Unauthorized","message":"Token is expired"}
```

**Respuesta fallida (HTTP 404):**
```
HTTP/1.1 404 Not Found
Content-Type: application/json

{"error":"Not Found","message":"Label with code AULA001 not found"}
```

---

## Interpretación de Códigos HTTP

| Código | Situación | Acción |
|--------|-----------|--------|
| **200** | OK | Operación completada exitosamente |
| **201** | Created | Recurso creado (plantilla/etiqueta) |
| **400** | Bad Request | Payload/formato inválido — revisa JSON |
| **401** | Unauthorized | Credenciales/token inválido o expirado |
| **403** | Forbidden | Permiso insuficiente |
| **404** | Not Found | Endpoint no existe o recurso no encontrado |
| **409** | Conflict | Recurso ya existe |
| **500** | Server Error | Error interno del servidor ZKONG |
| **503** | Service Unavailable | Servidor ZKONG no disponible |

---

**Última actualización**: 01 Jun 2026  
**Versión**: 1.0
