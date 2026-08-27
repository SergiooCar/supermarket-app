CREATE TABLE IF NOT EXISTS importaciones (
    id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    nombre_fichero   VARCHAR(255)  NOT NULL,
    total_productos  INT UNSIGNED  NOT NULL DEFAULT 0,
    nuevos           INT UNSIGNED  NOT NULL DEFAULT 0,
    modificados      INT UNSIGNED  NOT NULL DEFAULT 0,
    eliminados       INT UNSIGNED  NOT NULL DEFAULT 0,
    estado           ENUM('procesando','completada','fallida')        NOT NULL DEFAULT 'procesando',
    estado_zkong     ENUM('pendiente','sincronizado','fallido')        NOT NULL DEFAULT 'pendiente',
    creado_en        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id)
) 
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

