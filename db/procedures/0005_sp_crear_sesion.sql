DROP PROCEDURE IF EXISTS sp_crear_sesion;

DELIMITER $$

-- Se llama justo después de que PHP verifica la contraseña con password_verify().
-- No requiere token porque el usuario acaba de autenticarse.
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

DELIMITER ;

