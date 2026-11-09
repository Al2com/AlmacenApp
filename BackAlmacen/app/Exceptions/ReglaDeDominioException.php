<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando una operación violaría una regla de dominio del almacén
 * (por ejemplo dejar el stock en negativo, consumir un lote caducado o
 * superar la capacidad de una ubicación).
 */
class ReglaDeDominioException extends RuntimeException {}
