DROP PROCEDURE IF EXISTS sp_obtener_todos_productos;

DELIMITER $$

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

DELIMITER ;

