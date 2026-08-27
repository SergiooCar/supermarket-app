DROP PROCEDURE IF EXISTS sp_obtener_producto_por_codigo;

DELIMITER $$

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

DELIMITER ;

