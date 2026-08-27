DROP PROCEDURE IF EXISTS sp_guardar_actualizar_producto;

DELIMITER $$
 
-- Inserta un nuevo producto o actualiza uno existente
-- basado en el código de barras (ON DUPLICATE KEY UPDATE)
-- Los parametros son:
-- p_token (VARCHAR): Token de sesión del usuario
-- p_codigo_barras (VARCHAR): Código único del producto
-- p_referencia (VARCHAR): Referencia interna del producto
-- p_nombre (VARCHAR): Nombre del producto
-- p_precio_venta (DECIMAL): Precio de venta normal
-- p_precio_oferta (DECIMAL): Precio en oferta (puede ser NULL)
-- p_precio_tarifa (DECIMAL): Precio por tarifa (puede ser NULL)
-- p_precio_frio (DECIMAL): Precio para productos refrigerados (puede ser NULL)
-- p_precio_unidad (DECIMAL): Precio por unidad (ej: precio por kilo)
-- p_tipo_unidad (VARCHAR): Tipo de unidad (kg, unidad, caja, etc.)
-- p_cant_caja (SMALLINT): Cantidad de unidades por caja
-- Permisos de admin y user... (en el futuro arreglo todo eso )
 
CREATE PROCEDURE sp_guardar_actualizar_producto(
    IN p_token VARCHAR(100),
    IN p_codigo_barras VARCHAR(50),
    IN p_referencia VARCHAR(20),
    IN p_nombre VARCHAR(255),
    IN p_precio_venta DECIMAL(10,2),
    IN p_precio_oferta DECIMAL(10,2),
    IN p_precio_tarifa DECIMAL(10,2),
    IN p_precio_frio DECIMAL(10,2),
    IN p_precio_unidad DECIMAL(10,4),
    IN p_tipo_unidad VARCHAR(20),
    IN p_cant_caja SMALLINT UNSIGNED
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
