-- ============================================================
-- PARTE 3: PROCEDURES (segunda mitad)
-- Importar tercero y último este archivo
-- ============================================================

DELIMITER $$

-- sp_eliminar_producto
DROP PROCEDURE IF EXISTS sp_eliminar_producto$$
CREATE PROCEDURE sp_eliminar_producto(
    IN p_token         VARCHAR(100),
    IN p_codigo_barras VARCHAR(50)
)
BEGIN
    DECLARE v_rol VARCHAR(20) DEFAULT NULL;
    DECLARE v_expirado INT DEFAULT 1;
    DECLARE v_producto_id INT DEFAULT NULL;

    SELECT u.rol, (s.expira_en < NOW())
    INTO v_rol, v_expirado
    FROM sesiones s
    JOIN usuarios u ON s.usuario_id = u.id
    WHERE s.token = p_token AND u.activo = 1
    LIMIT 1;

    IF v_rol IS NULL THEN
        SELECT 0 AS exito, 'Token no encontrado' AS mensaje;
    ELSEIF v_expirado = 1 THEN
        SELECT 0 AS exito, 'Sesion expirada' AS mensaje;
    ELSEIF v_rol != 'admin' THEN
        SELECT 0 AS exito, 'No tienes permisos para eliminar productos' AS mensaje;
    ELSE
        SELECT id INTO v_producto_id FROM productos WHERE codigo_barras = p_codigo_barras LIMIT 1;

        IF v_producto_id IS NULL THEN
            SELECT 0 AS exito, 'Producto no encontrado' AS mensaje;
        ELSE
            DELETE FROM productos WHERE id = v_producto_id;
            SELECT 1 AS exito, 'Producto eliminado' AS mensaje;
        END IF;
    END IF;
END$$


-- sp_obtener_producto_por_codigo
DROP PROCEDURE IF EXISTS sp_obtener_producto_por_codigo$$
CREATE PROCEDURE sp_obtener_producto_por_codigo(
    IN p_token         VARCHAR(100),
    IN p_codigo_barras VARCHAR(50)
)
BEGIN
    DECLARE v_rol VARCHAR(20) DEFAULT NULL;
    DECLARE v_expirado INT DEFAULT 1;
    DECLARE v_producto_id INT DEFAULT NULL;

    SELECT u.rol, (s.expira_en < NOW())
    INTO v_rol, v_expirado
    FROM sesiones s
    JOIN usuarios u ON s.usuario_id = u.id
    WHERE s.token = p_token AND u.activo = 1
    LIMIT 1;

    IF v_rol IS NULL THEN
        SELECT 0 AS exito, 'Token no encontrado' AS mensaje,
               NULL AS codigo_barras, NULL AS referencia, NULL AS nombre,
               NULL AS precio_venta, NULL AS precio_oferta, NULL AS precio_tarifa,
               NULL AS precio_frio, NULL AS precio_unidad, NULL AS tipo_unidad, NULL AS cant_caja;
    ELSEIF v_expirado = 1 THEN
        SELECT 0 AS exito, 'Sesion expirada' AS mensaje,
               NULL AS codigo_barras, NULL AS referencia, NULL AS nombre,
               NULL AS precio_venta, NULL AS precio_oferta, NULL AS precio_tarifa,
               NULL AS precio_frio, NULL AS precio_unidad, NULL AS tipo_unidad, NULL AS cant_caja;
    ELSE
        SELECT id INTO v_producto_id FROM productos WHERE codigo_barras = p_codigo_barras LIMIT 1;

        IF v_producto_id IS NULL THEN
            SELECT 0 AS exito, 'Producto no encontrado' AS mensaje,
                   NULL AS codigo_barras, NULL AS referencia, NULL AS nombre,
                   NULL AS precio_venta, NULL AS precio_oferta, NULL AS precio_tarifa,
                   NULL AS precio_frio, NULL AS precio_unidad, NULL AS tipo_unidad, NULL AS cant_caja;
        ELSE
            SELECT 1 AS exito, 'OK' AS mensaje,
                   p.codigo_barras, p.referencia, p.nombre,
                   p.precio_venta, p.precio_oferta, p.precio_tarifa,
                   p.precio_frio, p.precio_unidad, p.tipo_unidad, p.cant_caja
            FROM productos p
            WHERE p.id = v_producto_id;
        END IF;
    END IF;
END$$


-- sp_obtener_todos_productos
DROP PROCEDURE IF EXISTS sp_obtener_todos_productos$$
CREATE PROCEDURE sp_obtener_todos_productos(
    IN p_token VARCHAR(100)
)
BEGIN
    DECLARE v_rol VARCHAR(20) DEFAULT NULL;
    DECLARE v_expirado INT DEFAULT 1;

    SELECT u.rol, (s.expira_en < NOW())
    INTO v_rol, v_expirado
    FROM sesiones s
    JOIN usuarios u ON s.usuario_id = u.id
    WHERE s.token = p_token AND u.activo = 1
    LIMIT 1;

    IF v_rol IS NULL THEN
        SELECT 0 AS exito, 'Token no encontrado' AS mensaje;
    ELSEIF v_expirado = 1 THEN
        SELECT 0 AS exito, 'Sesion expirada' AS mensaje;
    ELSE
        SELECT codigo_barras, referencia, nombre, precio_venta, precio_oferta,
               precio_tarifa, precio_frio, precio_unidad, tipo_unidad, cant_caja
        FROM productos
        ORDER BY nombre ASC;
    END IF;
END$$


-- sp_registrar_importacion
DROP PROCEDURE IF EXISTS sp_registrar_importacion$$
CREATE PROCEDURE sp_registrar_importacion(
    IN p_token          VARCHAR(100),
    IN p_nombre_fichero VARCHAR(255),
    IN p_total          INT UNSIGNED,
    IN p_nuevos         INT UNSIGNED,
    IN p_modificados    INT UNSIGNED,
    IN p_eliminados     INT UNSIGNED,
    IN p_estado         ENUM('procesando','completada','fallida')
)
BEGIN
    DECLARE v_rol VARCHAR(20) DEFAULT NULL;
    DECLARE v_expirado INT DEFAULT 1;

    SELECT u.rol, (s.expira_en < NOW())
    INTO v_rol, v_expirado
    FROM sesiones s
    JOIN usuarios u ON s.usuario_id = u.id
    WHERE s.token = p_token AND u.activo = 1
    LIMIT 1;

    IF v_rol IS NULL THEN
        SELECT 0 AS exito, 'Token no encontrado' AS mensaje, NULL AS id;
    ELSEIF v_expirado = 1 THEN
        SELECT 0 AS exito, 'Sesion expirada' AS mensaje, NULL AS id;
    ELSEIF v_rol != 'admin' THEN
        SELECT 0 AS exito, 'No tienes permisos para registrar importaciones' AS mensaje, NULL AS id;
    ELSE
        INSERT INTO importaciones (nombre_fichero, total_productos, nuevos, modificados, eliminados, estado)
        VALUES (p_nombre_fichero, p_total, p_nuevos, p_modificados, p_eliminados, p_estado);

        SELECT 1 AS exito, 'Importacion registrada' AS mensaje, LAST_INSERT_ID() AS id;
    END IF;
END$$


-- sp_actualizar_estado_importacion
DROP PROCEDURE IF EXISTS sp_actualizar_estado_importacion$$
CREATE PROCEDURE sp_actualizar_estado_importacion(
    IN p_token          VARCHAR(100),
    IN p_importacion_id INT UNSIGNED,
    IN p_estado         ENUM('procesando','completada','fallida')
)
BEGIN
    DECLARE v_rol VARCHAR(20) DEFAULT NULL;
    DECLARE v_expirado INT DEFAULT 1;
    DECLARE v_importacion_id INT DEFAULT NULL;

    SELECT u.rol, (s.expira_en < NOW())
    INTO v_rol, v_expirado
    FROM sesiones s
    JOIN usuarios u ON s.usuario_id = u.id
    WHERE s.token = p_token AND u.activo = 1
    LIMIT 1;

    IF v_rol IS NULL THEN
        SELECT 0 AS exito, 'Token no encontrado' AS mensaje;
    ELSEIF v_expirado = 1 THEN
        SELECT 0 AS exito, 'Sesion expirada' AS mensaje;
    ELSEIF v_rol != 'admin' THEN
        SELECT 0 AS exito, 'No tienes permisos para modificar importaciones' AS mensaje;
    ELSE
        SELECT id INTO v_importacion_id FROM importaciones WHERE id = p_importacion_id LIMIT 1;

        IF v_importacion_id IS NULL THEN
            SELECT 0 AS exito, 'Importacion no encontrada' AS mensaje;
        ELSE
            UPDATE importaciones SET estado = p_estado WHERE id = v_importacion_id;
            SELECT 1 AS exito, 'Estado actualizado' AS mensaje;
        END IF;
    END IF;
END$$


-- sp_actualizar_estado_zkong
DROP PROCEDURE IF EXISTS sp_actualizar_estado_zkong$$
CREATE PROCEDURE sp_actualizar_estado_zkong(
    IN p_id     INT UNSIGNED,
    IN p_estado ENUM('pendiente','sincronizado','fallido')
)
BEGIN
    UPDATE importaciones SET estado_zkong = p_estado WHERE id = p_id;
END$$


-- sp_insertar_log_zkong
DROP PROCEDURE IF EXISTS sp_insertar_log_zkong$$
CREATE PROCEDURE sp_insertar_log_zkong(
    IN p_endpoint  VARCHAR(200),
    IN p_metodo    VARCHAR(10),
    IN p_cantidad  INT,
    IN p_codigo    INT,
    IN p_mensaje   TEXT,
    IN p_duracion  INT
)
BEGIN
    INSERT INTO logs_zkong (endpoint, metodo, cantidad_productos, codigo_respuesta, mensaje_respuesta, duracion_ms)
    VALUES (p_endpoint, p_metodo, p_cantidad, p_codigo, p_mensaje, p_duracion);
END$$


-- sp_insertar_emparejamiento
DROP PROCEDURE IF EXISTS sp_insertar_emparejamiento$$
CREATE PROCEDURE sp_insertar_emparejamiento(
    IN p_codigo_etiqueta  VARCHAR(60),
    IN p_barcode_articulo VARCHAR(100),
    IN p_store_id         VARCHAR(20)
)
BEGIN
    UPDATE emparejamientos
       SET desemparejado_en = NOW()
     WHERE codigo_etiqueta  = p_codigo_etiqueta
       AND desemparejado_en IS NULL;

    INSERT INTO emparejamientos (codigo_etiqueta, barcode_articulo, store_id)
    VALUES (p_codigo_etiqueta, p_barcode_articulo, p_store_id);
END$$


-- sp_desemparejar_etiqueta (corregido: DROP dentro del DELIMITER)
DROP PROCEDURE IF EXISTS sp_desemparejar_etiqueta$$
CREATE PROCEDURE sp_desemparejar_etiqueta(
    IN p_codigo_etiqueta VARCHAR(60)
)
BEGIN
    UPDATE emparejamientos
       SET desemparejado_en = NOW()
     WHERE codigo_etiqueta  = p_codigo_etiqueta
       AND desemparejado_en IS NULL;
END$$


-- sp_obtener_emparejamientos
DROP PROCEDURE IF EXISTS sp_obtener_emparejamientos$$
CREATE PROCEDURE sp_obtener_emparejamientos()
BEGIN
    SELECT e.id,
           e.codigo_etiqueta,
           e.barcode_articulo,
           p.nombre         AS nombre_articulo,
           e.store_id,
           e.emparejado_en
      FROM emparejamientos e
      LEFT JOIN productos p ON p.codigo_barras = e.barcode_articulo
     WHERE e.desemparejado_en IS NULL
     ORDER BY e.emparejado_en DESC;
END$$


-- sp_listar_usuarios
DROP PROCEDURE IF EXISTS sp_listar_usuarios$$
CREATE PROCEDURE sp_listar_usuarios()
BEGIN
    SELECT id, nombre, email, rol, activo, creado_en
    FROM   usuarios
    ORDER  BY creado_en DESC;
END$$


-- sp_crear_usuario
DROP PROCEDURE IF EXISTS sp_crear_usuario$$
CREATE PROCEDURE sp_crear_usuario(
    IN p_nombre   VARCHAR(100),
    IN p_email    VARCHAR(150),
    IN p_password VARCHAR(255),
    IN p_rol      ENUM('admin','user')
)
BEGIN
    INSERT INTO usuarios (nombre, email, password, rol, activo)
    VALUES (p_nombre, p_email, p_password, p_rol, 1);
    SELECT LAST_INSERT_ID() AS id;
END$$


-- sp_editar_usuario
DROP PROCEDURE IF EXISTS sp_editar_usuario$$
CREATE PROCEDURE sp_editar_usuario(
    IN p_id     INT UNSIGNED,
    IN p_nombre VARCHAR(100),
    IN p_email  VARCHAR(150),
    IN p_rol    ENUM('admin','user')
)
BEGIN
    UPDATE usuarios SET nombre = p_nombre, email = p_email, rol = p_rol WHERE id = p_id;
END$$


-- sp_eliminar_usuario
DROP PROCEDURE IF EXISTS sp_eliminar_usuario$$
CREATE PROCEDURE sp_eliminar_usuario(IN p_id INT UNSIGNED, IN p_admin_id INT UNSIGNED)
BEGIN
    IF p_id = p_admin_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No puedes eliminarte a ti mismo.';
    ELSE
        DELETE FROM usuarios WHERE id = p_id;
    END IF;
END$$


-- sp_toggle_activo_usuario
DROP PROCEDURE IF EXISTS sp_toggle_activo_usuario$$
CREATE PROCEDURE sp_toggle_activo_usuario(IN p_id INT UNSIGNED)
BEGIN
    UPDATE usuarios SET activo = IF(activo = 1, 0, 1) WHERE id = p_id;
END$$


DELIMITER ;
