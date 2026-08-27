# Tu App vs Este Proyecto - Comparativa ZKONG

## Lo que YA hace tu app

Tu aplicación actual tiene:
- ✓ Obtención de clave pública (`GET /zk/user/getErpPublicKey`)
- ✓ Login RSA (`POST /zk/user/login` con credenciales cifradas)
- ✓ Guarda token en sesión (`$_SESSION['zkong_token']`)

---

## Lo que ESTE PROYECTO implementa

Este proyecto añade:
- ✅ Funciones helper para obtener configuración
- ✅ Función para obtener token (con Basic Auth alternativo)
- ✅ Función para crear plantillas
- ✅ Función para actualizar etiquetas gráficas
- ✅ Documentación exhaustiva (9 archivos)
- ✅ Script de test automático
- ✅ Ejemplos de cURL listos

---

## Endpoints que YA usas vs. Este Proyecto

| Endpoint | Tu App | Este Proyecto | Status |
|----------|--------|---------------|--------|
| `/zk/user/getErpPublicKey` | ✓ Funciona | ✓ Implementado | ✓ Compatible |
| `/zk/user/login` | ✓ Funciona (RSA) | ✓ Implementado (Basic Auth) | ⚠️ Métodos diferentes |
| `/zk/api/status` | ✗ No | ✓ Implementado | ✓ Nuevo |
| `/zk/api/sync/classroom` | ✗ No | ✓ Implementado | ✓ Nuevo |
| `/zk/api/template` | ✗ No | ✓ Implementado | ✓ Nuevo |
| `/zk/api/label/{code}` | ✗ No | ✓ Implementado | ✓ Nuevo |

---

## Diferencias de Autenticación

### Tu App (RSA Cifrado)
```
1. Obtiene clave pública: GET /zk/user/getErpPublicKey
2. Encripta credenciales con clave RSA
3. Envia credenciales cifradas: POST /zk/user/login
4. Recibe token JWT
5. Usa token en Headers: Authorization: Bearer {token}
```

**Ventaja**: Más seguro (credenciales nunca viajan en texto plano)

### Este Proyecto (Basic Auth)
```
1. Login simple: POST /zk/user/login con Basic Auth
   Headers: Authorization: Basic base64(usuario:password)
2. Recibe token JWT
3. Usa token en Headers: Authorization: Bearer {token}
```

**Ventaja**: Más simple, menos pasos

---

## Cómo Replicar Exactamente en Tu App

### Opción 1: Usar el código tal cual
Si quieres usar este código directamente en tu app:

```php
<?php
// Copia script/ZkongAPIExtended.php a tu proyecto
require_once 'scripts/ZkongAPIExtended.php';

// Usa las funciones directamente
$token = obtenerTokenZkong();  // Usa Basic Auth

// Si prefieres tu método RSA, mantén tu token actual
// La función crearPlantillaZkong() funciona con cualquier token válido
$resultado = crearPlantillaZkong($token, $storeId, $nombre, $json);
?>
```

### Opción 2: Adaptar a tu flujo RSA
Si prefieres mantener tu método RSA:

```php
<?php
// Tu código actual RSA (mantener igual)
$publicKey = obtenerPublicKeyTuMetodo();
$tokenRSA = loginConRSATuMetodo($publicKey, $usuario, $password);

// Usar nuestras funciones de plantilla con tu token
require_once 'scripts/ZkongAPIExtended.php';

$resultado = crearPlantillaZkong(
    $tokenRSA,  // Tu token RSA (compatible)
    $storeId,
    $nombre,
    $json
);
?>
```

**Nota**: Ambos métodos generan tokens JWT válidos, son intercambiables.

---

## URLs: ¿Con `/zk` o sin?

Tu app parece usar:
- `/zk/user/getErpPublicKey` (con `/zk`)
- `/zk/user/login` (con `/zk`)

Este proyecto usa:
- Intenta con `/zk` primero
- Luego intenta sin `/zk`
- Esto da mayor compatibilidad

---

## Payloads Comparados

### Sincronizar Aula

**Este Proyecto**:
```json
{
  "etiqueta_codigo": "CODIGO123",
  "aula_nombre": "Aula 1",
  "aula_id": 42
}
```

**Tu App** (si tuviera):
```
Probablemente similar, podría variar formato
```

---

### Crear Plantilla

**Este Proyecto** (asumido, puede variar):
```json
{
  "name": "Plantilla X",
  "storeId": "1773057109137",
  "type": "label",
  "design": { /* JSON */ },
  "active": true
}
```

**Tu App**:
```
Sin implementación aún
```

---

## Checklist de Integración en Tu App

Para replicar exactamente en tu app existente:

- [ ] Paso 1: Obtener este código
  ```bash
  cp scripts/ZkongAPIExtended.php /ruta/tu-app/scripts/
  ```

- [ ] Paso 2: Incluir en tu router o bootstrap
  ```php
  require_once __DIR__ . '/scripts/ZkongAPIExtended.php';
  ```

- [ ] Paso 3: Configurar variables de entorno (si necesita BD)
  - Si tu app tiene tabla `zkong_config`, usar `getZkongConfig()`
  - Si no, ajusta la función para leer desde `.env`

- [ ] Paso 4: Ajustar URLs si son diferentes
  - Edita `scripts/ZkongAPIExtended.php` líneas donde hace cURL
  - Cambia URLs si tu servidor usa rutas diferentes

- [ ] Paso 5: Validar
  ```bash
  php zkong_test_completo.php
  ```

---

## Problemas Comunes y Soluciones

### "404 en `/api/status`"
**Causa**: Endpoint no existe en tu servidor.  
**Solución**: Intenta sin `/zk`: `/api/status` o consulta doc ZKONG.

### "401 en sincronización"
**Causa**: Credenciales Basic Auth incorrectas.  
**Solución**: Verifica usuario/password en BD o `.env`.

### "Plantilla devuelve 404"
**Causa**: URL incorrecta (`/zk/api/template` puede ser otro path).  
**Solución**: Ejecuta `php zkong_test_completo.php` paso 6; verá error exacto.

### "Token inválido para Bearer"
**Causa**: Token no es formato JWT válido o expirado.  
**Solución**: Confirma que `/user/login` devuelve `token` válido.

---

## Tabla Comparativa Final

| Aspecto | Tu App | Este Proyecto |
|--------|--------|---------------|
| **Clave Pública** | ✓ GET `/zk/user/getErpPublicKey` | ✓ `obtenerPublicKeyZkong()` |
| **Login** | ✓ POST RSA cifrado | ✓ POST Basic Auth (o tu RSA) |
| **Status/Ping** | ✗ | ✓ `verificarConexionZkong()` |
| **Sincronizar** | ✗ | ✓ `sincronizarAulaAZkong()` |
| **Crear Plantilla** | ✗ | ✓ `crearPlantillaZkong()` |
| **Actualizar Etiqueta** | ✗ | ✓ `actualizarEtiquetaCompletaZkong()` |
| **Documentación** | Parcial | ✓ Exhaustiva (9 archivos) |
| **Test Automático** | ✗ | ✓ `zkong_test_completo.php` |
| **Ejemplos cURL** | ✗ | ✓ 12+ ejemplos |

---

## Conclusión

**Tu app**: Tiene lo básico (autenticación).

**Este proyecto**: Tiene TODO (autenticación + operaciones + documentación + test).

**Integración**: En 5 pasos podrías replicar exactamente en tu app. Los métodos de autenticación son compatibles.

---

**Última actualización**: 01 Jun 2026  
**Relacionado con**: [`ZKONG_README.md`](ZKONG_README.md), [`PROXIMOS_PASOS.md`](PROXIMOS_PASOS.md)
