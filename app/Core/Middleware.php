<?php

/**
 */

declare(strict_types=1);

namespace App\Core;

/**
 * Guardas de acceso por sesión y rol.
 *
 * Capa de protección invocada por el Router antes de ejecutar cualquier
 * acción de controlador. Todos sus métodos son estáticos porque se llaman
 * sin instanciar la clase y deben poder finalizar la petición con exit.
 */
class Middleware
{
    /**
     * Garantiza que existe una sesión de usuario activa.
     *
     * Redirige a la página de login y detiene la ejecución si la sesión
     * no contiene usuario_id. Usado por rutas con protección 'auth'.
     *
     * @return void
     */
    public static function requiereLogin(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: ' . BASE_URL . '/auth/login');
            exit;
        }
    }

    /**
     * Garantiza que el usuario autenticado tiene el rol 'admin'.
     *
     * Llama primero a requiereLogin() para asegurarse de que hay sesión.
     * Si el rol no es 'admin', sirve una página 403 y detiene la ejecución.
     * Usado por rutas con protección 'admin'.
     *
     * @return void
     */
    public static function requiereAdmin(): void
    {
        self::requiereLogin();

        if (($_SESSION['rol'] ?? '') !== 'admin') {
            http_response_code(403);
            include  dirname(__DIR__, 1) . '/Vistas/errors/403.php';
            exit;
        }
    }
}
