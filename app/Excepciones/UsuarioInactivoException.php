<?php

/**
 */

declare(strict_types=1);

namespace App\Excepciones;

/**
 * Excepción lanzada cuando un usuario intenta autenticarse con una cuenta desactivada.
 *
 * Se distingue de CredencialesInvalidasException para que el controlador pueda
 * mostrar un mensaje más específico que indique al usuario que contacte con el admin.
 */
class UsuarioInactivoException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Esta cuenta está desactivada. Contacta con el administrador.');
    }
}
