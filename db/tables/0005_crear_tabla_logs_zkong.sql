CREATE TABLE IF NOT EXISTS logs_zkong (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    endpoint            VARCHAR(200)    NOT NULL,
    metodo              VARCHAR(10)     NOT NULL,
    cantidad_productos  INT             NOT NULL DEFAULT 0,
    codigo_respuesta    INT             DEFAULT NULL,
    mensaje_respuesta   TEXT            DEFAULT NULL,
    duracion_ms         INT             DEFAULT NULL,
    creado_en           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
 
    PRIMARY KEY (id),
    INDEX idx_creado_en (creado_en),
    INDEX idx_endpoint  (endpoint(100)),
    INDEX idx_codigo    (codigo_respuesta)
) 
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

