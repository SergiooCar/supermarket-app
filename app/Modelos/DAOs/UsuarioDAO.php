<?php

/**
 */

declare(strict_types=1);

namespace App\Modelos\DAOs;

use App\Modelos\DTOs\UsuarioDTO;
use App\Modelos\Helpers\Database;

/**
 * DAO de usuarios del sistema.
 *
 * Gestiona la tabla 'usuarios'. La consulta de login usa SQL directo porque
 * necesita devolver el hash de contraseña (que los stored procedures de
 * listado omiten por seguridad). El resto de operaciones usan stored procedures
 * para centralizar la lógica de validación en la BD.
 */
class UsuarioDAO
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerInstancia();
    }

    /**
     * Busca un usuario por email para el proceso de login.
     *
     * Usa SQL directo en vez de SP porque es la única consulta que necesita
     * devolver el campo 'password' (hash bcrypt). Los SPs de listado lo omiten
     * deliberadamente para no exponer hashes en respuestas de administración.
     *
     * @param string $email Email del usuario a buscar.
     * @return UsuarioDTO|null  null si el email no existe.
     */
    public function buscarPorEmailParaLogin(string $email): ?UsuarioDTO
    {
        $stmt = $this->db->prepare(
            'SELECT id, nombre, email, password, rol, activo
             FROM   usuarios
             WHERE  email = ?
             LIMIT  1'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ? UsuarioDTO::desdeArray($row) : null;
    }

    /**
     * Devuelve todos los usuarios del sistema sin sus hashes de contraseña.
     *
     * El SP sp_listar_usuarios no devuelve el campo 'password'; se rellena
     * con cadena vacía al construir el DTO para satisfacer el constructor.
     *
     * @return UsuarioDTO[]
     *
     * @throws \PDOException Si el SP lanza un error.
     */
    public function obtenerTodos(): array
    {
        $stmt = $this->db->prepare('CALL sp_listar_usuarios()');
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $stmt->closeCursor();
        return array_map(fn($r) => UsuarioDTO::desdeArray(
            array_merge($r, ['password' => ''])
        ), $rows);
    }

    /**
     * Crea un nuevo usuario y devuelve su ID generado.
     *
     * La contraseña debe venir ya hasheada con bcrypt antes de llamar a este método.
     *
     * @param string $nombre       Nombre visible del usuario.
     * @param string $email        Email único del usuario.
     * @param string $passwordHash Hash bcrypt de la contraseña.
     * @param string $rol          Rol del usuario ('admin' o 'user').
     * @return int  ID del usuario recién creado.
     *
     * @throws \PDOException Si el email ya existe (SQLSTATE 23000) u otro error de BD.
     */
    public function crear(string $nombre, string $email, string $passwordHash, string $rol): int
    {
        $stmt = $this->db->prepare('CALL sp_crear_usuario(?, ?, ?, ?)');
        $stmt->execute([$nombre, $email, $passwordHash, $rol]);
        $row = $stmt->fetch();
        $stmt->closeCursor();
        return (int) ($row['id'] ?? 0);
    }

    /**
     * Actualiza el nombre, email y rol de un usuario existente.
     *
     * @param int    $id     ID del usuario a editar.
     * @param string $nombre Nuevo nombre.
     * @param string $email  Nuevo email (debe ser único).
     * @param string $rol    Nuevo rol ('admin' o 'user').
     * @return void
     *
     * @throws \PDOException Si el nuevo email ya está en uso.
     */
    public function editar(int $id, string $nombre, string $email, string $rol): void
    {
        $stmt = $this->db->prepare('CALL sp_editar_usuario(?, ?, ?, ?)');
        $stmt->execute([$id, $nombre, $email, $rol]);
        $stmt->closeCursor();
    }

    /**
     * Invierte el estado activo/inactivo de un usuario.
     *
     * @param int $id ID del usuario a alternar.
     * @return void
     *
     * @throws \PDOException Si el SP lanza un error.
     */
    public function toggleActivo(int $id): void
    {
        $stmt = $this->db->prepare('CALL sp_toggle_activo_usuario(?)');
        $stmt->execute([$id]);
        $stmt->closeCursor();
    }

    /**
     * Elimina un usuario del sistema.
     *
     * El SP sp_eliminar_usuario comprueba que el administrador no se elimine
     * a sí mismo usando el parámetro adminId. La validación en BD previene
     * que una llamada directa o un bug en el controlador deje al sistema sin admins.
     *
     * @param int $id      ID del usuario a eliminar.
     * @param int $adminId ID del administrador que ejecuta la acción.
     * @return void
     *
     * @throws \PDOException Si el SP lanza un error (p. ej. auto-eliminación).
     */
    public function eliminar(int $id, int $adminId): void
    {
        $stmt = $this->db->prepare('CALL sp_eliminar_usuario(?, ?)');
        $stmt->execute([$id, $adminId]);
        $stmt->closeCursor();
    }
}
