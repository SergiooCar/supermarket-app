DELIMITER $$
 
DROP PROCEDURE IF EXISTS sp_desemparejar_etiqueta$$
 
CREATE PROCEDURE sp_desemparejar_etiqueta(
    IN p_codigo_etiqueta VARCHAR(60)
)
BEGIN
    UPDATE emparejamientos
       SET desemparejado_en = NOW()
     WHERE codigo_etiqueta  = p_codigo_etiqueta
       AND desemparejado_en IS NULL;
END$$
 
DELIMITER ;
 