DROP PROCEDURE IF EXISTS sp_actualizar_estado_zkong;

DELIMITER $$

CREATE PROCEDURE sp_actualizar_estado_zkong(
    IN p_id     INT UNSIGNED,
    IN p_estado ENUM('pendiente','sincronizado','fallido')
)
BEGIN
    UPDATE importaciones SET estado_zkong = p_estado WHERE id = p_id;
END$$

DELIMITER ;
