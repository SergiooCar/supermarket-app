DROP PROCEDURE IF EXISTS sp_insertar_log_zkong;

DELIMITER $$

CREATE PROCEDURE sp_insertar_log_zkong(
    IN p_endpoint   VARCHAR(200),
    IN p_metodo     VARCHAR(10),
    IN p_cantidad   INT,
    IN p_codigo     INT,
    IN p_mensaje    TEXT,
    IN p_duracion   INT
)
BEGIN
    INSERT INTO logs_zkong (endpoint, metodo, cantidad_productos, codigo_respuesta, mensaje_respuesta, duracion_ms)
    VALUES (p_endpoint, p_metodo, p_cantidad, p_codigo, p_mensaje, p_duracion);
END$$

DELIMITER ;
