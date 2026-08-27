<?php

/**
 */

declare(strict_types=1);

namespace App\Servicios;

use App\Modelos\DAOs\UsuarioDAO;
use App\Modelos\DTOs\UsuarioDTO;
use App\Excepciones\CredencialesInvalidasException;
use App\Excepciones\UsuarioInactivoException;

/**
 * Servicio de autenticación de usuarios.
 *
 * Encapsula la lógica de verificación de credenciales: busca al usuario por
 * email, verifica el hash bcrypt y comprueba que la cuenta esté activa.
 * Lanza excepciones tipadas para que el controlador pueda mostrar mensajes
 * de error sin conocer los detalles de la verificación.
 */
class AuthServicio
{
    private UsuarioDAO $dao;

    public function __construct()
    {
        $this->dao = new UsuarioDAO();
    }

    /**
     * Verifica las credenciales y devuelve el DTO del usuario autenticado.
     *
     * No distingue en el mensaje de error si el email no existe o si la
     * contraseña es incorrecta, para no filtrar información sobre qué emails
     * están registrados en el sistema.
     *
     * @param string $email    Email del usuario (ya sanitizado por el controlador).
     * @param string $password Contraseña en texto plano a verificar contra el hash.
     * @return UsuarioDTO  Datos del usuario autenticado.
     *
     * @throws CredencialesInvalidasException  Si el email no existe o la contraseña no coincide.
     * @throws UsuarioInactivoException        Si la cuenta está desactivada.
     */
    public function login(string $email, string $password): UsuarioDTO
    {
        $usuario = $this->dao->buscarPorEmailParaLogin($email);

        if ($usuario === null || !password_verify($password, $usuario->getPassword())) {
            throw new CredencialesInvalidasException();
        }

        if (!$usuario->isActivo()) {
            throw new UsuarioInactivoException();
        }

        return $usuario;
    }
}
