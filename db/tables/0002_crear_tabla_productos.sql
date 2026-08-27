CREATE TABLE IF NOT EXISTS productos (
    id             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    codigo_barras  VARCHAR(50)      NOT NULL,
    referencia     VARCHAR(20)      NOT NULL,
    nombre         VARCHAR(255)     NOT NULL,
    precio_venta   DECIMAL(10,2)    NOT NULL,
    precio_oferta  DECIMAL(10,2)    NULL,
    precio_tarifa  DECIMAL(10,2)    NULL,
    precio_frio    DECIMAL(10,2)    NULL,
    precio_unidad  DECIMAL(10,4)    NOT NULL,
    tipo_unidad    VARCHAR(20)      NOT NULL,
    cant_caja      SMALLINT UNSIGNED NOT NULL,
    estado_zkong   ENUM('pendiente','sincronizado','fallido') NOT NULL DEFAULT 'pendiente',
    creado_en      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_codigo_barras (codigo_barras)
) 
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

