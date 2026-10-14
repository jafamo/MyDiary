<?php

declare(strict_types=1);

namespace App\Contract;

final class AudioProbeResult
{
    public const FORMATS = ['m4a', 'mp3', 'ogg', 'wav'];

    /**
     * @param string $format uno de self::FORMATS; sirve también de extensión del fichero
     */
    public function __construct(
        public readonly string $format,
        public readonly int $durationSeconds,
    ) {
    }
}
