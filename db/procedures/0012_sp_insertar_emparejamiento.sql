DROP PROCEDURE IF EXISTS sp_insertar_emparejamiento;

DELIMITER $$

CREATE PROCEDURE sp_insertar_emparejamiento(
    IN p_codigo_etiqueta  VARCHAR(60),
    IN p_barcode_articulo VARCHAR(100),
    IN p_store_id         VARCHAR(20)
)
BEGIN
    -- Marcar como desemparejado cualquier vÃ­nculo vigente de esta etiqueta
    UPDATE emparejamientos
       SET desemparejado_en = NOW()
     WHERE codigo_etiqueta  = p_codigo_etiqueta
       AND desemparejado_en IS NULL;

    INSERT INTO emparejamientos (codigo_etiqueta, barcode_articulo, store_id)
    VALUES (p_codigo_etiqueta, p_barcode_articulo, p_store_id);
END$$

DELIMITER ;
