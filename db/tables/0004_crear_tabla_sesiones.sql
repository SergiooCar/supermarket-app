CREATE TABLE IF NOT EXISTS sesiones (
    -- DEFAULT CURRENT_TIMESTAMP, para que en caso de que php no ponga la hora
    -- sql coge la hora automaticamente y asigna a la sesión 
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    token       VARCHAR(100)    NOT NULL,
    creado_en   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_en   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usuario_id  INT UNSIGNED     NOT NULL,

PRIMARY KEY (id),
UNIQUE KEY uq_token (token),
FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) 
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A partir dfe ahora, todo procedure recibe como mínimo un parámetro: el token.
-- si un procedure funciona significa que aprueba los datos de entrada

-- antes de hacer cualquier operación ejecuta tres comprobaciones:
  -- -1. ¿Este token existe en la tabla sesiones?
  -- -2. ¿Ha caducado?
  -- -3. ¿El rol del usuario dueño del token tiene permiso para esta operación?

-- si cualquiera falla, devuelve success = 0 con un mensaje de error.
-- si todas pasan, ejecuta la operación real y devuelve success = 1 con los datos.
