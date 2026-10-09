<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Contract\AudioProbeException;
use App\Contract\AudioProbeInterface;
use App\Contract\AudioProbeResult;

/**
 * Doble de ffprobe para el entorno de test (registrado en `when@test` de services.yaml):
 * reconoce el formato por la cabecera del fichero y devuelve una duración fija.
 */
class FakeAudioProbe implements AudioProbeInterface
{
    public int $durationSeconds = 37;
    public int $calls = 0;

    public function probe(string $audioFilePath): AudioProbeResult
    {
        ++$this->calls;
        $header = (string) file_get_contents($audioFilePath, false, null, 0, 12);

        $format = match (true) {
            str_starts_with($header, 'OggS') => 'ogg',
            str_starts_with($header, 'RIFF') => 'wav',
            str_starts_with($header, 'ID3') => 'mp3',
            'ftyp' === substr($header, 4, 4) => 'm4a',
            default => throw new AudioProbeException('El fichero no es un audio en un formato admitido.'),
        };

        return new AudioProbeResult($format, $this->durationSeconds);
    }
}
