DROP PROCEDURE IF EXISTS sp_buscar_usuario_por_email;

DELIMITER $$

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

DELIMITER ;
