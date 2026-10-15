<?php

declare(strict_types=1);

namespace App\Tests;

use App\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * La compilación del contenedor no deja deprecaciones (p. ej. autowiring por nombre de parámetro):
 * se detectan aquí, en CI, y no en el canal `deprecation` de producción tras un despliegue.
 *
 * Bajo PHPUnit el kernel no escribe `<Container>Deprecations.log` (KernelTrait lo omite si existe
 * PHPUNIT_COMPOSER_INSTALL), así que se compila un contenedor nuevo en un directorio temporal
 * capturando las deprecaciones con un manejador propio.
 */
class ContainerDeprecationsTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir().'/mydiary-container-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->cacheDir);
    }

    public function testContainerCompilesWithoutDeprecations(): void
    {
        $cacheDir = $this->cacheDir;
        $kernel = new class ('test', true, $cacheDir) extends Kernel {
            public function __construct(string $environment, bool $debug, private readonly string $tmpCacheDir)
            {
                parent::__construct($environment, $debug);
            }

            public function getCacheDir(): string
            {
                return $this->tmpCacheDir;
            }

            public function getBuildDir(): string
            {
                return $this->tmpCacheDir;
            }
        };

        $deprecations = [];
        set_error_handler(static function (int $type, string $message) use (&$deprecations): bool {
            if (\E_USER_DEPRECATED === $type || \E_DEPRECATED === $type) {
                $deprecations[] = $message;
            }

            return true;
        });

        try {
            $kernel->boot();
        } finally {
            restore_error_handler();
            $kernel->shutdown();
        }

        self::assertSame([], array_values(array_unique($deprecations)));
    }
}
