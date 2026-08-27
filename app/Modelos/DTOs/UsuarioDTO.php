<?php

/**
 */

declare(strict_types=1);

namespace App\Modelos\DTOs;

/**
 * DTO inmutable que representa un usuario del sistema.
 *
 * El campo 'password' contiene el hash bcrypt en el flujo de login
 * (vía UsuarioDAO::buscarPorEmailParaLogin) y cadena vacía en los listados
 * de administración (los SPs de listado no devuelven el hash por seguridad).
 */
class UsuarioDTO
{
    /**
     * @param int    $id       Identificador autoincremental.
     * @param string $nombre   Nombre visible del usuario.
     * @param string $email    Email único del usuario.
     * @param string $password Hash bcrypt (vacío en listados de admin).
     * @param string $rol      Rol del usuario ('admin' o 'user').
     * @param bool   $activo   true si la cuenta está habilitada.
     */
    public function __construct(
        private readonly int    $id,
        private readonly string $nombre,
        private readonly string $email,
        private readonly string $password,
        private readonly string $rol,
        private readonly bool   $activo,
    ) {}

    /** @return int */
    public function getId(): int          { return $this->id; }
    /** @return string */
    public function getNombre(): string   { return $this->nombre; }
    /** @return string */
    public function getEmail(): string    { return $this->email; }
    /** @return string Hash bcrypt o cadena vacía en contexto de listado. */
    public function getPassword(): string { return $this->password; }
    /** @return string */
    public function getRol(): string      { return $this->rol; }
    /** @return bool */
    public function isActivo(): bool      { return $this->activo; }

    /**
     * Construye un UsuarioDTO desde una fila de BD (fetchAll con FETCH_ASSOC).
     *
     * @param array<string, mixed> $row Fila con los campos id, nombre, email, password, rol, activo.
     * @return self
     */
    public static function desdeArray(array $row): self
    {
        return new self(
            (int)  $row['id'],
                   $row['nombre'],
                   $row['email'],
                   $row['password'],
                   $row['rol'],
            (bool) $row['activo'],
        );
    }
}
