<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Topic;
use PHPUnit\Framework\TestCase;

class TopicTest extends TestCase
{
    public function testGetterAndSetter(): void
    {
        $topic = new Topic();
        $topic->setName('Trabajo');

        self::assertSame('Trabajo', $topic->getName());
    }

    public function testStartsWithoutDailySummaries(): void
    {
        $topic = new Topic();

        self::assertCount(0, $topic->getDailySummaries());
    }
}
