-- ============================================================
-- PARTE 2: PROCEDURES (primera mitad)
-- Importar segundo este archivo
-- ============================================================

DELIMITER $$

-- sp_login_obtener_usuario
DROP PROCEDURE IF EXISTS sp_login_obtener_usuario$$
CREATE PROCEDURE sp_login_obtener_usuario(
    IN p_email VARCHAR(150)
)
BEGIN
    DECLARE v_id INT DEFAULT NULL;

    SELECT id INTO v_id FROM usuarios WHERE email = p_email LIMIT 1;

    IF v_id IS NULL THEN
        SELECT 0 AS exito, 'Usuario no encontrado' AS mensaje,
               NULL AS id, NULL AS nombre, NULL AS email,
               NULL AS password, NULL AS rol, NULL AS activo;
    ELSE
        SELECT 1 AS exito, 'OK' AS mensaje,
               u.id, u.nombre, u.email, u.password, u.rol, u.activo
        FROM usuarios u
        WHERE u.id = v_id;
    END IF;
END$$


-- sp_crear_sesion
DROP PROCEDURE IF EXISTS sp_crear_sesion$$
CREATE PROCEDURE sp_crear_sesion(
    IN p_usuario_id INT UNSIGNED,
    IN p_token      VARCHAR(100),
    IN p_expira_en  TIMESTAMP
)
BEGIN
    DECLARE v_activo TINYINT DEFAULT 0;

    SELECT activo INTO v_activo FROM usuarios WHERE id = p_usuario_id LIMIT 1;

    IF v_activo IS NULL THEN
        SELECT 0 AS exito, 'Usuario no existe' AS mensaje;
    ELSEIF v_activo = 0 THEN
        SELECT 0 AS exito, 'Usuario inactivo' AS mensaje;
    ELSE
        INSERT INTO sesiones (token, usuario_id, expira_en)
        VALUES (p_token, p_usuario_id, p_expira_en);

        SELECT 1 AS exito, 'Sesion creada' AS mensaje;
    END IF;
END$$


-- sp_cerrar_sesion
DROP PROCEDURE IF EXISTS sp_cerrar_sesion$$
CREATE PROCEDURE sp_cerrar_sesion(
    IN p_token VARCHAR(100)
)
BEGIN
    DECLARE v_sesion_id INT DEFAULT NULL;

    SELECT id INTO v_sesion_id FROM sesiones WHERE token = p_token LIMIT 1;

    IF v_sesion_id IS NULL THEN
        SELECT 0 AS exito, 'Token no encontrado' AS mensaje;
    ELSE
        DELETE FROM sesiones WHERE id = v_sesion_id;
        SELECT 1 AS exito, 'Sesion cerrada' AS mensaje;
    END IF;
END$$


-- sp_validar_sesion
DROP PROCEDURE IF EXISTS sp_validar_sesion$$
CREATE PROCEDURE sp_validar_sesion(
    IN  p_token      VARCHAR(100),
    OUT v_rol        ENUM('admin','user'),
    OUT v_usuario_id INT UNSIGNED,
    OUT v_valida     BOOLEAN
)
BEGIN
    DECLARE v_expirado INT DEFAULT 1;

    SELECT u.rol, u.id, (s.expira_en <= NOW())
    INTO v_rol, v_usuario_id, v_expirado
    FROM sesiones s
    JOIN usuarios u ON s.usuario_id = u.id
    WHERE s.token = p_token AND u.activo = 1
    LIMIT 1;

    IF v_rol IS NULL THEN
        SET v_valida = FALSE;
    ELSEIF v_expirado = 1 THEN
        SET v_valida = FALSE;
    ELSE
        SET v_valida = TRUE;
    END IF;
END$$


-- sp_buscar_usuario_por_email
DROP PROCEDURE IF EXISTS sp_buscar_usuario_por_email$$
CREATE PROCEDURE sp_buscar_usuario_por_email(
    IN p_token VARCHAR(100),
    IN p_email VARCHAR(100)
)
BEGIN
    DECLARE v_rol VARCHAR(20) DEFAULT NULL;
    DECLARE v_expirado INT DEFAULT 1;
    DECLARE v_usuario_id INT DEFAULT NULL;

    SELECT u.rol, (s.expira_en < NOW())
    INTO v_rol, v_expirado
    FROM sesiones s
    JOIN usuarios u ON s.usuario_id = u.id
    WHERE s.token = p_token AND u.activo = 1
    LIMIT 1;

    IF v_rol IS NULL THEN
        SELECT 0 AS exito, 'Token no encontrado' AS mensaje,
               NULL AS id, NULL AS nombre, NULL AS email, NULL AS rol, NULL AS activo, NULL AS creado_en;
    ELSEIF v_expirado = 1 THEN
        SELECT 0 AS exito, 'Sesion expirada' AS mensaje,
               NULL AS id, NULL AS nombre, NULL AS email, NULL AS rol, NULL AS activo, NULL AS creado_en;
    ELSEIF v_rol != 'admin' THEN
        SELECT 0 AS exito, 'No tienes permisos' AS mensaje,
               NULL AS id, NULL AS nombre, NULL AS email, NULL AS rol, NULL AS activo, NULL AS creado_en;
    ELSE
        SELECT id INTO v_usuario_id FROM usuarios WHERE email = p_email LIMIT 1;

        IF v_usuario_id IS NULL THEN
            SELECT 0 AS exito, 'Usuario no encontrado' AS mensaje,
                   NULL AS id, NULL AS nombre, NULL AS email, NULL AS rol, NULL AS activo, NULL AS creado_en;
        ELSE
            SELECT 1 AS exito, 'OK' AS mensaje,
                   u.id, u.nombre, u.email, u.rol, u.activo, u.creado_en
            FROM usuarios u
            WHERE u.id = v_usuario_id;
        END IF;
    END IF;
END$$


-- sp_guardar_actualizar_producto (compatible con MySQL < 8.0.19)
DROP PROCEDURE IF EXISTS sp_guardar_actualizar_producto$$
CREATE PROCEDURE sp_guardar_actualizar_producto(
    IN p_token         VARCHAR(100),
    IN p_codigo_barras VARCHAR(50),
    IN p_referencia    VARCHAR(20),
    IN p_nombre        VARCHAR(255),
    IN p_precio_venta  DECIMAL(10,2),
    IN p_precio_oferta DECIMAL(10,2),
    IN p_precio_tarifa DECIMAL(10,2),
    IN p_precio_frio   DECIMAL(10,2),
    IN p_precio_unidad DECIMAL(10,4),
    IN p_tipo_unidad   VARCHAR(20),
    IN p_cant_caja     SMALLINT UNSIGNED
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
    ELSEIF v_rol NOT IN ('admin', 'user') THEN
        SELECT 0 AS exito, 'No tienes permisos para modificar productos' AS mensaje;
    ELSE
        INSERT INTO productos (
            codigo_barras, referencia, nombre, precio_venta, precio_oferta,
            precio_tarifa, precio_frio, precio_unidad, tipo_unidad, cant_caja
        ) VALUES (
            p_codigo_barras, p_referencia, p_nombre, p_precio_venta, p_precio_oferta,
            p_precio_tarifa, p_precio_frio, p_precio_unidad, p_tipo_unidad, p_cant_caja
        ) ON DUPLICATE KEY UPDATE
            referencia    = VALUES(referencia),
            nombre        = VALUES(nombre),
            precio_venta  = VALUES(precio_venta),
            precio_oferta = VALUES(precio_oferta),
            precio_tarifa = VALUES(precio_tarifa),
            precio_frio   = VALUES(precio_frio),
            precio_unidad = VALUES(precio_unidad),
            tipo_unidad   = VALUES(tipo_unidad),
            cant_caja     = VALUES(cant_caja);

        SELECT 1 AS exito, 'Producto guardado correctamente' AS mensaje;
    END IF;
END$$


DELIMITER ;
