<?php

/**
 */

declare(strict_types=1);

namespace App\Excepciones;

/**
 * Excepción lanzada cuando el fichero CSV subido no cumple el formato esperado.
 *
 * Casos: fichero vacío, no se puede abrir, falta alguna columna requerida.
 * El mensaje descriptivo se pasa en el constructor en el punto de lanzamiento.
 */
class CsvMalformadoException extends \RuntimeException {}
