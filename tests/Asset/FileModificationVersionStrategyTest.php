<?php

declare(strict_types=1);

namespace App\Tests\Asset;

use App\Asset\FileModificationVersionStrategy;
use PHPUnit\Framework\TestCase;

class FileModificationVersionStrategyTest extends TestCase
{
    private string $publicDir;

    protected function setUp(): void
    {
        $this->publicDir = sys_get_temp_dir().'/'.uniqid('asset-version-test-', true);
        mkdir($this->publicDir);
        file_put_contents($this->publicDir.'/app.css', 'body{}');
    }

    protected function tearDown(): void
    {
        unlink($this->publicDir.'/app.css');
        rmdir($this->publicDir);
    }

    public function testApplyVersionAppendsFileModificationTime(): void
    {
        $strategy = new FileModificationVersionStrategy($this->publicDir);

        $result = $strategy->applyVersion('app.css');

        self::assertMatchesRegularExpression('/^app\.css\?v=\d+$/', $result);
    }

    public function testApplyVersionReturnsPathUnchangedWhenFileMissing(): void
    {
        $strategy = new FileModificationVersionStrategy($this->publicDir);

        self::assertSame('missing.css', $strategy->applyVersion('missing.css'));
    }
}
