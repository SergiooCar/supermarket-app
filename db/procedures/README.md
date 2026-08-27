# Guía para equipo

## Por qué usamos procedures en lugar de escribir SQL directamente en PHP?

Cuando el código PHP necesita hablar con la base de datos, tiene dos opciones:

**SQL en PHP (lo que ya no hacemos):**
```php
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
```
El problema es que esa query está mezclada con el código de la aplicación. Si de la nada es necesario cambiar la base de datos, hay que buscar ese SQL en decenas de archivos PHP.

**Stored Procedure (lo que SÍ hacemos):**
```php
$stmt = $pdo->prepare("CALL login_obtener_usuario(?)");
```
El SQL no sale de la base de datos. PHP solo dice "ejecuta este procedure con estos datos". Si cambia la logica de base de datos, se cambia el procedure, no el PHP.

**Mwjora:**
- El SQL complejo está en un solo sitio, no repartido por toda la app
- La base de datos valida el token y los permisos antes de hacer cualquier cosa
- PHP no construye queries — solo llama procedures con parámetros seguros


## Regla que siguen TODOS los procedures (excepto login y crear sesión)

Antes de ejecutar cualquier operación, cada procedure hace comprobaciones:

```
1. Existe este token en la tabla sesiones?   → Si no: "Token no encontrado"
2. Ha caducado la sesión?                    → Si sí: "Sesion expirada"
3. El rol del usuario tiene permiso?         → Si no: mensaje y niega el permiso: "No tienes permisos"

Si las tres pasan → ejecuta el select
```

Esto significa que **la base de datos misma rechaza operaciones no autorizadas**, aunque el código PHP tuviese un error.



## Conceptos SQL usados en estos procedures

### DECLARE
```sql
DECLARE v_rol VARCHAR(20) DEFAULT NULL;
```
Crea una variable interna del procedure. Solo existe mientras el procedure se está ejecutando. El prefijo `v_` es una convención para distinguirlas de los parámetros de entrada (`p_`) y las columnas de la tabla.

### SELECT ... INTO
```sql
SELECT u.rol, (s.expira_en < NOW())
INTO v_rol, v_expirado
FROM sesiones s
JOIN usuarios u ON s.usuario_id = u.id
WHERE s.token = p_token
LIMIT 1;
```
En lugar de devolver filas al que llamó el procedure, guarda los valores en las variables declaradas. Es como decir: "busca esto en la base de datos y guárdalo en mis variables para usarlo después".

### JOIN
```sql
FROM sesiones s
JOIN usuarios u ON s.usuario_id = u.id
```
Une dos tablas. La tabla `sesiones` tiene una columna `usuario_id` que apunta al `id` de la tabla `usuarios`. El JOIN las conecta para que podamos leer datos de las dos a la vez. `s` y `u` son alias (apodos) para no escribir el nombre completo cada vez.

### NOW()
```sql
(s.expira_en < NOW())
```
`NOW()` devuelve la fecha y hora exacta en este momento. Comparamos `expira_en` (cuando caduca la sesión) con ahora. Si `expira_en` es menor que ahora, la sesión ya caducó → resultado `1` (verdadero).

### IF / ELSEIF / ELSE

En todas los condicionales son una especie de filtro o de seguridad para que antes dehacer cualquier cosa garantiza que el usuario tiene los permisos, que el token esta abierto y que no está expirado.

### LAST_INSERT_ID() - Explicacion IA
```sql
SELECT 1 AS exito, 'Importacion registrada' AS mensaje, LAST_INSERT_ID() AS id;
```
Después de un `INSERT`, devuelve el `id` auto-generado del registro que se acaba de crear. Así PHP sabe el id de la nueva importación sin tener que hacer otra consulta.

### OUT _ parámetro de salida
```sql
CREATE PROCEDURE sp_validar_sesion(
    IN  p_token      VARCHAR(100),
    OUT v_rol        ENUM('admin','user'),
    OUT v_valida     BOOLEAN
)
```
`IN` = dato que PHP envía al procedure. `OUT` = dato que el procedure devuelve a PHP directamente en una variable, sin usar SELECT. Solo `sp_validar_sesion` usa este patrón. El resto devuelven resultados con SELECT.

---

## Procedures disponibles - TOTAL EXPLICACOIN IA

### `sp_login_obtener_usuario`
**Cuándo se usa:** Cuando el usuario escribe su email y contraseña en el login.

**Excepción importante:** Este es el único procedure (junto con `sp_crear_sesion`) que **no recibe token**. Tiene sentido: el usuario está intentando iniciar sesión, todavía no tiene token.

**Qué hace:**
1. Busca en la tabla `usuarios` por email
2. Si no existe → devuelve error
3. Si existe → devuelve todos sus datos, incluyendo el hash de la contraseña

**Importante:** Devuelve el hash pero NO verifica la contraseña. La verificación la hace PHP con `password_verify()` porque las funciones de hash de PHP son más seguras que las de MySQL.

**Resultado que devuelve:**
| exito | mensaje | id | nombre | email | password | rol | activo |
|---|---|---|---|---|---|---|---|
| 1 | OK | 5 | Juan | juan@... | $2y$10$... | admin | 1 |

---

### `sp_crear_sesion`
**Cuándo se usa:** Justo después de que PHP verifica la contraseña con `password_verify()` y confirma que es correcta.

**No recibe token** porque el usuario acaba de autenticarse.

**Qué hace:**
1. Comprueba que el usuario existe y está activo
2. Inserta un nuevo registro en la tabla `sesiones` con el token generado por PHP

**Resultado que devuelve:**
| exito | mensaje |
|---|---|
| 1 | Sesion creada |

---

### `sp_cerrar_sesion`
**Cuándo se usa:** Cuando el usuario hace clic en "Cerrar sesión".

**Qué hace:**
1. Busca el token en la tabla `sesiones`
2. Si no existe → error (puede que ya estuviese cerrada)
3. Si existe → lo borra con `DELETE`

**Resultado que devuelve:**
| exito | mensaje |
|---|---|
| 1 | Sesion cerrada |

---

### `sp_validar_sesion`
**Cuándo se usa:** En middleware, para comprobar si el usuario que hace una petición tiene una sesión válida antes de mostrar cualquier página protegida.

**Diferente al resto:** Usa parámetros `OUT` en lugar de `SELECT` para devolver el resultado. PHP los lee así:
```php
$pdo->query("CALL sp_validar_sesion('$token', @rol, @usuario_id, @valida)");
$resultado = $pdo->query("SELECT @rol, @usuario_id, @valida")->fetch();
```

**Qué devuelve:** El rol del usuario, su id, y si la sesión es válida (`TRUE`/`FALSE`).

---

### `sp_obtener_todos_productos`
**Cuándo se usa:** Al cargar la página de importación para comparar los productos del CSV con los que ya están en la base de datos.

**Rol permitido:** `admin` y `user`.

**Qué hace:**
1. Valida token y sesión
2. Si todo está bien → devuelve TODAS las filas de la tabla `productos`

**Caso especial:** Si hay error, devuelve 1 fila con `exito=0` y `mensaje`. Si todo va bien, devuelve N filas de productos directamente (sin columna `exito`). PHP distingue los dos casos comprobando si la primera fila tiene la clave `exito`.

---

### `sp_obtener_producto_por_codigo`
**Cuándo se usa:** Para consultar un producto concreto por su código de barras.

**Rol permitido:** `admin` y `user`.

**Qué hace:**
1. Valida token
2. Busca el producto por `codigo_barras`
3. Si no existe → devuelve error con todos los campos a `NULL`
4. Si existe → devuelve sus datos

**Resultado en caso de éxito:**
| exito | mensaje | codigo_barras | referencia | nombre | precio_venta | ... |
|---|---|---|---|---|---|---|
| 1 | OK | 23629 | 000036 | QUINA SANSON 1L | 5.65 | ... |

---

### `sp_guardar_actualizar_producto`
**Cuándo se usa:** Al confirmar una importación CSV — para cada producto nuevo o modificado.

**Rol permitido:** `admin` y `user`.

**Qué hace:** Intenta insertar el producto. Si el `codigo_barras` ya existe (clave única), en lugar de dar error lo actualiza. Esto se llama `ON DUPLICATE KEY UPDATE` — es una instrucción de MySQL que hace INSERT o UPDATE en una sola operación.

---

### `sp_eliminar_producto`
**Cuándo se usa:** Para eliminar un producto individual de la base de datos.

**Rol permitido:** Solo `admin`.

**Qué hace:**
1. Valida token + comprueba que es admin
2. Busca el producto por `codigo_barras`
3. Si no existe → error
4. Si existe → lo borra con `DELETE`

---

### `sp_registrar_importacion`
**Cuándo se usa:** Al finalizar una importación CSV, para guardar un registro histórico de lo que ocurrió.

**Rol permitido:** Solo `admin`.

**Qué hace:** Inserta una fila en la tabla `importaciones` con el nombre del fichero, cuántos productos había en total, cuántos son nuevos, modificados y eliminados, y el estado final.

Devuelve el `id` del registro creado (con `LAST_INSERT_ID()`) para que PHP pueda actualizarlo después si el estado cambia.

---

### `sp_actualizar_estado_importacion`
**Cuándo se usa:** Si una importación empieza como `procesando` y luego termina como `completada` o `fallida`.

**Rol permitido:** Solo `admin`.

**Qué hace:**
1. Valida token + admin
2. Comprueba que existe la importación con ese `id`
3. Cambia el campo `estado` con `UPDATE`

---

## Cómo leer el resultado en PHP

Todos los procedures (excepto `sp_validar_sesion`) devuelven el resultado con un `SELECT`. En PHP se lee así:

```php
$stmt = $pdo->prepare("CALL sp_eliminar_producto(?, ?)");
$stmt->execute([$token, $codigoBarras]);
$fila = $stmt->fetch(PDO::FETCH_ASSOC);

if ($fila['exito'] === 0) {
    // algo falló — ver $fila['mensaje'] para saber qué
} else {
    // todo fue bien
}
```

Para los que devuelven múltiples filas (`sp_obtener_todos_productos`):
```php
$stmt = $pdo->prepare("CALL sp_obtener_todos_productos(?)");
$stmt->execute([$token]);
$primera = $stmt->fetch(PDO::FETCH_ASSOC);

if (isset($primera['exito']) && $primera['exito'] === 0) {
    // error — ver $primera['mensaje']
} else {
    // $primera ya es el primer producto, seguir leyendo con fetchAll o un bucle
}
```
