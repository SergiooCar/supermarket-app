DROP PROCEDURE IF EXISTS sp_cerrar_sesion;

DELIMITER $$

CREATE PROCEDURE sp_cerrar_sesion(
    IN p_token VARCHAR(100)
)
BEGIN
    DECLARE v_sesion_id INT DEFAULT NULL;

    SELECT id INTO v_sesion_id FROM sesiones WHERE token = p_token LIMIT 1;

    IF v_sesion_id IS NULL THEN
        SELECT 0 AS exito, 'Token no encontrado' AS mensaje;

    ELSE
        DELETE FROM sesiones WHERE id = v_sesion_id;

        SELECT 1 AS exito, 'Sesion cerrada' AS mensaje;
    END IF;
END$$

DELIMITER ;
