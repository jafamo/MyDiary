<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\UiTheme;
use PHPUnit\Framework\TestCase;

class UiThemeTest extends TestCase
{
    public function testCasesHaveExpectedValues(): void
    {
        self::assertSame('moderno', UiTheme::MODERNO->value);
        self::assertSame('cuaderno', UiTheme::CUADERNO->value);
        self::assertCount(2, UiTheme::cases());
    }

    public function testDefaultIsModerno(): void
    {
        self::assertSame(UiTheme::MODERNO, UiTheme::default());
    }

    public function testLabelsAndIcons(): void
    {
        self::assertSame('Moderno', UiTheme::MODERNO->label());
        self::assertSame('Cuaderno', UiTheme::CUADERNO->label());
        self::assertSame('i-grid', UiTheme::MODERNO->icon());
        self::assertSame('i-notebook', UiTheme::CUADERNO->icon());
    }
}
