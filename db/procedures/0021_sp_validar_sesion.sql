DROP PROCEDURE IF EXISTS sp_validar_sesion;

DELIMITER $$

CREATE PROCEDURE sp_validar_sesion(
    IN p_token VARCHAR(100),
    OUT v_rol ENUM('admin','user'),
    OUT v_usuario_id INT UNSIGNED,
    OUT v_valida BOOLEAN
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

DELIMITER ;
