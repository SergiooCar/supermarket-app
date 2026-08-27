DROP PROCEDURE IF EXISTS sp_registrar_importacion;

DELIMITER $$

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

DELIMITER ;