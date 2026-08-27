<?php

/**
 */

declare(strict_types=1);

namespace App\Excepciones;

/**
 * Excepción lanzada cuando el email no existe o la contraseña no coincide.
 *
 * El mensaje es deliberadamente genérico para no revelar si el email está
 * registrado en el sistema (prevención de enumeración de usuarios).
 */
class CredencialesInvalidasException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Email o contraseña incorrectos.');
    }
}
