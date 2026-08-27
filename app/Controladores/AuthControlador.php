<?php

/**
 */

declare(strict_types=1);

namespace App\Controladores;

use App\Core\Controlador;
use App\Servicios\AuthServicio;
use App\Excepciones\CredencialesInvalidasException;
use App\Excepciones\UsuarioInactivoException;

/**
 * Controlador de autenticación.
 *
 * Gestiona el ciclo login/logout: muestra el formulario, valida credenciales
 * via AuthServicio, escribe los datos de sesión y redirige tras el acceso.
 * Vive en la capa de Controladores — no contiene lógica de negocio.
 */
class AuthControlador extends Controlador
{
    private AuthServicio $servicio;

    public function __construct()
    {
        $this->servicio = new AuthServicio();
    }

    /**
     * Muestra el formulario de inicio de sesión.
     *
     * Si ya hay sesión activa redirige directamente al dashboard para evitar
     * que un usuario autenticado vea la pantalla de login.
     *
     * @return void
     */
    public function formularioLogin(): void
    {
        if (!empty($_SESSION['usuario_id'])) {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }

        $errorLogin = '';
        include dirname(__DIR__, 1) . '/Vistas/auth/login.php';
    }

    /**
     * Procesa el envío del formulario de login.
     *
     * Valida que email y contraseña no estén vacíos, delega la verificación
     * al servicio y, si todo es correcto, regenera el ID de sesión (prevención
     * de fijación de sesión) y redirige al dashboard.
     *
     * @return void
     */
    public function procesarLogin(): void
    {
        $email    = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $this->mostrarLogin('Rellena todos los campos.');
            return;
        }

        try {
            $usuario = $this->servicio->login($email, $password);

            session_regenerate_id(true);
            $_SESSION['usuario_id'] = $usuario->getId();
            $_SESSION['nombre']     = $usuario->getNombre();
            $_SESSION['rol']        = $usuario->getRol();

            header('Location: ' . BASE_URL . '/dashboard');
            exit;

        } catch (CredencialesInvalidasException $e) {
            $this->mostrarLogin($e->getMessage());
        } catch (UsuarioInactivoException $e) {
            $this->mostrarLogin($e->getMessage());
        }
    }

    /**
     * Cierra la sesión del usuario y redirige al login.
     *
     * @return void
     */
    public function logout(): void
    {
        session_unset();
        session_destroy();

        header('Location: ' . BASE_URL . '/auth/login');
        exit;
    }

    /**
     * Re-renderiza el formulario de login mostrando un mensaje de error.
     *
     * @param string $error Mensaje descriptivo del motivo del fallo.
     * @return void
     */
    private function mostrarLogin(string $error): void
    {
        $errorLogin = $error;
        include  dirname(__DIR__, 1) . '/Vistas/auth/login.php';
    }
}
