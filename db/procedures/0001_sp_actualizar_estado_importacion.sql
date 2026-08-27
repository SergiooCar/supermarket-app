DROP PROCEDURE IF EXISTS sp_actualizar_estado_importacion;

DELIMITER $$

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

DELIMITER ;
