DROP PROCEDURE IF EXISTS sp_listar_usuarios;
DELIMITER $$
CREATE PROCEDURE sp_listar_usuarios()
BEGIN
    SELECT id, nombre, email, rol, activo, creado_en
    FROM   usuarios
    ORDER  BY creado_en DESC;
END$$
DELIMITER ;
