<?php

declare(strict_types=1);

namespace App\Contract;

interface AudioProbeInterface
{
    /**
     * Examina el contenido de un fichero y devuelve su formato de audio y su duración.
     *
     * @throws AudioProbeException si el fichero no es un audio en un formato admitido
     */
    public function probe(string $audioFilePath): AudioProbeResult;
}
