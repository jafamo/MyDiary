<?php

declare(strict_types=1);

namespace App\Service;

/**
 * El audio existe en la base de datos pero su fichero no está en disco.
 */
class AudioFileMissingException extends \RuntimeException
{
}
