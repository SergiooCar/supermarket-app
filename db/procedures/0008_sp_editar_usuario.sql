DROP PROCEDURE IF EXISTS sp_editar_usuario;
DELIMITER $$
CREATE PROCEDURE sp_editar_usuario(
    IN p_id     INT UNSIGNED,
    IN p_nombre VARCHAR(100),
    IN p_email  VARCHAR(150),
    IN p_rol    ENUM('admin','user')
)
BEGIN
    UPDATE usuarios SET nombre = p_nombre, email = p_email, rol = p_rol WHERE id = p_id;
END$$
DELIMITER ;

