DROP PROCEDURE IF EXISTS sp_login_obtener_usuario;

DELIMITER $$

-- este procedure NO recibe token porque es el login
CREATE PROCEDURE sp_login_obtener_usuario(
    IN p_email VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
)
BEGIN
    DECLARE v_id INT DEFAULT NULL;

    SELECT id INTO v_id FROM usuarios WHERE email = p_email COLLATE utf8mb4_unicode_ci LIMIT 1;

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

DELIMITER ;