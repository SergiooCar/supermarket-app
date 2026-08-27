<?php

/**
 * Punto de entrada raíz de la aplicación.
 *
 * Redirige inmediatamente a public/ para que el servidor web no exponga
 * directamente app/ ni ningún otro directorio interno. En producción el
 * DocumentRoot debería apuntar a public/ directamente, haciendo este fichero
 * innecesario; se conserva como compatibilidad con XAMPP (htdocs sin vhost).
 */
header('Location: public/');
exit;
