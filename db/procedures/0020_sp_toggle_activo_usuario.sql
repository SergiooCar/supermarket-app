DROP PROCEDURE IF EXISTS sp_toggle_activo_usuario;
DELIMITER $$
CREATE PROCEDURE sp_toggle_activo_usuario(IN p_id INT UNSIGNED)
BEGIN
    UPDATE usuarios SET activo = IF(activo = 1, 0, 1) WHERE id = p_id;
END$$
DELIMITER ;
