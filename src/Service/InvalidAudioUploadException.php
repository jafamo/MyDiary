<?php

declare(strict_types=1);

namespace App\Service;

/**
 * El fichero subido no se acepta como audio; el mensaje es apto para mostrarse al cliente.
 */
class InvalidAudioUploadException extends \RuntimeException
{
}
