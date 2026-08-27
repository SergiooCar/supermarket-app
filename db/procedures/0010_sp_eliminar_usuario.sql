DROP PROCEDURE IF EXISTS sp_eliminar_usuario;
DELIMITER $$
CREATE PROCEDURE sp_eliminar_usuario(IN p_id INT UNSIGNED, IN p_admin_id INT UNSIGNED)
BEGIN
    -- Un admin no puede eliminarse a sí mismo
    IF p_id = p_admin_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No puedes eliminarte a ti mismo.';
    ELSE
        DELETE FROM usuarios WHERE id = p_id;
    END IF;
END$$
DELIMITER ;

