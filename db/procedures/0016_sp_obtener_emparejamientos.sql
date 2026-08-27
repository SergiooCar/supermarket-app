DROP PROCEDURE IF EXISTS sp_obtener_emparejamientos;

DELIMITER $$

CREATE PROCEDURE sp_obtener_emparejamientos()
BEGIN
    SELECT e.id,
           e.codigo_etiqueta,
           e.barcode_articulo,
           p.nombre            AS nombre_articulo,
           e.store_id,
           e.emparejado_en
      FROM emparejamientos e
      LEFT JOIN productos p ON p.codigo_barras = e.barcode_articulo
     WHERE e.desemparejado_en IS NULL
     ORDER BY e.emparejado_en DESC;
END$$

DELIMITER ;
