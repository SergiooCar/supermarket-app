DROP PROCEDURE IF EXISTS sp_crear_usuario;
DELIMITER $$
CREATE PROCEDURE sp_crear_usuario(
    IN p_nombre   VARCHAR(100),
    IN p_email    VARCHAR(150),
    IN p_password VARCHAR(255),
    IN p_rol      ENUM('admin','user')
)
BEGIN
    INSERT INTO usuarios (nombre, email, password, rol, activo)
    VALUES (p_nombre, p_email, p_password, p_rol, 1);
    SELECT LAST_INSERT_ID() AS id;
END$$
DELIMITER ;
