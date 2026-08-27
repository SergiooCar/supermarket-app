DROP PROCEDURE IF EXISTS sp_eliminar_producto;

DELIMITER $$

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

DELIMITER ;

