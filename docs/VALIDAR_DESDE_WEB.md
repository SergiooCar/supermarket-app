# Validar ZKONG desde la Web - Paso a Paso

## 🎯 **PASO 1: Abre tu Navegador**

Escribe esta URL:
```
http://localhost/supermarket-app/public/zkong_web_test.php
```

O si tu proyecto está en otro puerto/nombre, adapta:
```
http://TUHOST/supermarket-app/public/zkong_web_test.php
```

---

## ✅ **PASO 2: Verás una página con 6 validaciones**

### **Pantalla que verás:**

```
╔════════════════════════════════════════════════╗
║         🚀 ZKONG - Test Web                   ║
║   Validación de integración desde el navegador ║
╚════════════════════════════════════════════════╝

[1] ✓ Configuración
    Configuración leída correctamente
    - Usuario: iesbarajas-equipo4
    - StoreId: 1773057109137
    - ApiUrl: https://etiquetas.ausiasmarch.net/zk
    - CloudUrl: https://etiquetas.ausiasmarch.net

[2] ✓ Conexión (Basic Auth)
    Conexión exitosa a /api/status
    - HTTP Code: 200
    - Mensaje: OK

[3] ✓ Clave Pública RSA
    Clave pública obtenida
    - Longitud: 1234 caracteres
    - Tipo: PEM

[4] ✓ Token Bearer
    Token obtenido correctamente
    - Longitud: 256 caracteres
    - Primeros 50 chars: eyJ0eXAiOiJKV1QiLCJhbGc...

[5] ✓ Sincronizar Aula
    Aula sincronizada correctamente
    - HTTP Code: 200
    - Código etiqueta: TEST_WEB_1717225400

[6] ✓ Crear Plantilla
    Plantilla creada correctamente
    - HTTP Code: 200
    - Respuesta: {"success":true,"id":"..."}

📊 Resumen
═════════════════════════════════════════════════
✓ Exitosos: 6
✗ Fallos: 0
✓ Estado General: OK

✓ Tu app está correctamente conectada a ZKONG

Puedes usar estas funciones en tus controladores:
────────────────────────────────────────────────
$token = obtenerTokenZkong();
$resultado = crearPlantillaZkong($token, $storeId, $nombre, $json);
$resultado = actualizarEtiquetaCompletaZkong($token, $storeId, $codigo, $datos);
$resultado = sincronizarAulaAZkong($aula);
```

---

## 🎯 **PASO 3: Interpretar los Resultados**

### **Si ves muchos ✓ (verde):**
```
✓ Configuración
✓ Conexión
✓ Clave Pública
✓ Token
✓ Sincronizar Aula
✓ Crear Plantilla

→ ¡PERFECTO! Todo funciona. Ya puedes usar las funciones.
```

### **Si ves algún ✗ (rojo):**
```
✗ Conexión (Basic Auth)
  Error de conexión
  - HTTP Code: 401
  - Mensaje: Invalid credentials
  
→ Las credenciales son incorrectas. Verifica en BD.
```

---

## 🚀 **PASO 4: Usar en tu Controlador**

Una vez que TODO sea ✓, en cualquier controlador puedes escribir:

### **Ejemplo 1: Obtener Token**
```php
<?php
// En tu controlador, ya está cargado automáticamente
$token = obtenerTokenZkong();

if ($token) {
    echo "Token obtenido: " . substr($token, 0, 30) . "...";
} else {
    echo "Error obteniendo token";
}
?>
```

**Lo que verás en la web:**
```
Token obtenido: eyJ0eXAiOiJKV1QiLCJhbGc...
```

---

### **Ejemplo 2: Crear Plantilla**
```php
<?php
$config = getZkongConfig();
$token = obtenerTokenZkong();

$resultado = crearPlantillaZkong(
    $token,
    $config['storeId'],
    'Mi Plantilla',
    json_encode(['campo' => 'valor'])
);

if ($resultado['success']) {
    echo "✓ Plantilla creada!";
    echo "ID: " . $resultado['data']['id'];
} else {
    echo "✗ Error: " . $resultado['error'];
}
?>
```

**Lo que verás en la web:**
```
✓ Plantilla creada!
ID: template_12345
```

---

### **Ejemplo 3: Actualizar Etiqueta**
```php
<?php
$config = getZkongConfig();
$token = obtenerTokenZkong();

$resultado = actualizarEtiquetaCompletaZkong(
    $token,
    $config['storeId'],
    'AULA001',  // código de etiqueta existente
    [
        'nombre' => 'Aula A',
        'profesor' => 'Prof. García',
        'horario' => '09:00 Lun',
        'textoSuperior' => 'Aula A',
        'textoInferior' => '09:00 Lun'
    ]
);

if ($resultado['success']) {
    echo "✓ Etiqueta actualizada!";
} else {
    echo "✗ Error: " . $resultado['error'];
}
?>
```

**Lo que verás en la web:**
```
✓ Etiqueta actualizada!
```

---

### **Ejemplo 4: Sincronizar Aula**
```php
<?php
$aula = new stdClass();
$aula->id = 42;
$aula->etiqueta_codigo = "AULA001";
$aula->nombre = "Aula 1";

$resultado = sincronizarAulaAZkong($aula);

if ($resultado['success']) {
    echo "✓ Aula sincronizada!";
} else {
    echo "✗ Error: " . $resultado['message'];
}
?>
```

**Lo que verás en la web:**
```
✓ Aula sincronizada!
```

---

## 📞 **Si Algo Falla**

### **Paso [1] - Configuración ✗**
```
✗ Configuración
  Error: Error al obtener configuración
```

**Solución:**
1. Abre tu BD
2. Verifica que existe tabla `zkong_config`
3. Verifica que contiene estas claves:
   - `zkong_usuario`
   - `zkong_password`
   - `zkong_storeId`
   - `zkong_api_url`
   - `zkong_cloud_url`

Ejecuta en tu BD:
```sql
SELECT * FROM zkong_config WHERE clave LIKE 'zkong%';
```

---

### **Paso [2] - Conexión ✗**
```
✗ Conexión (Basic Auth)
  Error de conexión
  - HTTP Code: 401
```

**Solución:**
- Las credenciales son incorrectas
- Verifica usuario y password en BD tabla `zkong_config`

---

### **Paso [3] - Clave Pública ✗**
```
✗ Clave Pública RSA
  No se pudo obtener clave pública
```

**Solución:**
- El servidor ZKONG no está respondiendo
- O la URL está incorrecta
- Intenta en terminal:
```bash
curl -i "https://etiquetas.ausiasmarch.net/user/getErpPublicKey"
```

---

### **Paso [4] - Token ✗**
```
✗ Token Bearer
  No se pudo obtener token
```

**Solución:**
- El endpoint `/user/login` no funciona
- O el token no está en el JSON que devuelve
- Intenta en terminal:
```bash
curl -i -u "usuario:password" \
  -H "Content-Type: application/json" \
  -X POST "https://etiquetas.ausiasmarch.net/zk/user/login" \
  -d '{}'
```

---

## 🎯 **Resumen: Qué hacer en 2 minutos**

1. **Abre navegador:**
   ```
   http://localhost/supermarket-app/public/zkong_web_test.php
   ```

2. **Mira los resultados:**
   - Si todo ✓ → Ya funciona
   - Si algo ✗ → Sigue la solución arriba

3. **Listo:**
   - En tu app, usa `obtenerTokenZkong()` y las otras funciones

---

**¡Adelante!** 🚀

Abre el navegador y visita:
```
http://localhost/supermarket-app/public/zkong_web_test.php
```
