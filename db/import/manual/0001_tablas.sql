-- ============================================================
-- PARTE 1: TABLAS
-- Importar primero este archivo
-- ============================================================

-- 0001 usuarios
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 0002 productos
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
    creado_en      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_codigo_barras (codigo_barras)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 0003 importaciones (ya incluye estado_zkong)
CREATE TABLE IF NOT EXISTS importaciones (
    id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    nombre_fichero   VARCHAR(255)  NOT NULL,
    total_productos  INT UNSIGNED  NOT NULL DEFAULT 0,
    nuevos           INT UNSIGNED  NOT NULL DEFAULT 0,
    modificados      INT UNSIGNED  NOT NULL DEFAULT 0,
    eliminados       INT UNSIGNED  NOT NULL DEFAULT 0,
    estado           ENUM('procesando','completada','fallida')  NOT NULL DEFAULT 'procesando',
    estado_zkong     ENUM('pendiente','sincronizado','fallido') NOT NULL DEFAULT 'pendiente',
    creado_en        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 0004 sesiones (expira_en con DEFAULT para compatibilidad con MySQL antiguo)
CREATE TABLE IF NOT EXISTS sesiones (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    token       VARCHAR(100)    NOT NULL,
    creado_en   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_en   TIMESTAMP       NOT NULL DEFAULT '2000-01-01 00:00:00',
    usuario_id  INT UNSIGNED    NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_token (token),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 0005 logs_zkong (SIN ALTER TABLE, estado_zkong ya está en importaciones)
CREATE TABLE IF NOT EXISTS logs_zkong (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    endpoint            VARCHAR(200)    NOT NULL,
    metodo              VARCHAR(10)     NOT NULL,
    cantidad_productos  INT             NOT NULL DEFAULT 0,
    codigo_respuesta    INT             DEFAULT NULL,
    mensaje_respuesta   TEXT            DEFAULT NULL,
    duracion_ms         INT             DEFAULT NULL,
    creado_en           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 0006 emparejamientos
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 0007 alter seguro (si estado_zkong ya existe no hace nada)
ALTER TABLE importaciones
    ADD COLUMN IF NOT EXISTS estado_zkong
        ENUM('pendiente','sincronizado','fallido') NOT NULL DEFAULT 'pendiente'
    AFTER estado;


-- 0009 estado_zkong en productos (IF NOT EXISTS para re-ejecuciones seguras)
ALTER TABLE productos
    ADD COLUMN IF NOT EXISTS estado_zkong
        ENUM('pendiente','sincronizado','fallido') NOT NULL DEFAULT 'pendiente'
    AFTER cant_caja;


-- Usuario admin por defecto (INSERT IGNORE para re-ejecuciones seguras)
INSERT IGNORE INTO usuarios (nombre, email, password, rol, activo)
VALUES ('Administrador', 'admin@supermarket.com', '$2y$10$guv8ahiK5B9cSMHJ7h2MhObRZ8Vp.pk9t9yLg1tyF9Iu/DWnIbkGC', 'admin', 1);


-- Asegurar collation uniforme en todas las tablas
ALTER TABLE usuarios        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE productos       CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE importaciones   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE sesiones        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE logs_zkong      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE emparejamientos CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
