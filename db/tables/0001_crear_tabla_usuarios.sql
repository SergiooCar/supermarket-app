CREATE TABLE IF NOT EXISTS usuarios (
    id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre     VARCHAR(100)    NOT NULL,
    email      VARCHAR(150)    NOT NULL,
    password   VARCHAR(255)    NOT NULL,
    rol        ENUM('admin','user') NOT NULL DEFAULT 'user',
    activo     TINYINT(1)      NOT NULL DEFAULT 1,
    creado_en  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_email (email)
) 
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;