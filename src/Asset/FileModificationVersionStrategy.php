<?php

declare(strict_types=1);

namespace App\Asset;

use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

/**
 * Añade `?v=<filemtime>` a cada asset, para que el navegador pida el fichero
 * de nuevo tras cada despliegue en vez de servir una copia cacheada obsoleta
 * (sin esto, el HTML se actualiza pero el CSS/JS puede quedarse cacheado).
 */
class FileModificationVersionStrategy implements VersionStrategyInterface
{
    public function __construct(
        private readonly string $publicDir,
    ) {
    }

    public function getVersion(string $path): string
    {
        $file = $this->publicDir.'/'.ltrim($path, '/');

        return file_exists($file) ? (string) filemtime($file) : '';
    }

    public function applyVersion(string $path): string
    {
        $version = $this->getVersion($path);

        if ('' === $version) {
            return $path;
        }

        return $path.(str_contains($path, '?') ? '&' : '?').'v='.$version;
    }
}
