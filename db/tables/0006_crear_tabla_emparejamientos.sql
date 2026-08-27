CREATE TABLE IF NOT EXISTS emparejamientos (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo_etiqueta  VARCHAR(60)  NOT NULL,
    barcode_articulo VARCHAR(100) NOT NULL,
    store_id         VARCHAR(20)  NOT NULL,
    emparejado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    desemparejado_en DATETIME     DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_etiqueta (codigo_etiqueta),
    INDEX idx_barcode  (barcode_articulo)
) 
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

