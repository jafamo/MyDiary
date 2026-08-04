<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\DailySummary;
use App\Entity\Topic;
use PHPUnit\Framework\TestCase;

class DailySummaryTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $date = new \DateTimeImmutable('2026-08-04');
        $generatedAt = new \DateTimeImmutable('2026-08-04 21:00:00');

        $dailySummary = new DailySummary();
        $dailySummary
            ->setDate($date)
            ->setSummaryText('Resumen del día')
            ->setGeneratedAt($generatedAt)
        ;

        self::assertSame($date, $dailySummary->getDate());
        self::assertSame('Resumen del día', $dailySummary->getSummaryText());
        self::assertSame($generatedAt, $dailySummary->getGeneratedAt());
    }

    public function testAddTopicDoesNotDuplicate(): void
    {
        $dailySummary = new DailySummary();
        $topic = new Topic();
        $topic->setName('Trabajo');

        $dailySummary->addTopic($topic);
        $dailySummary->addTopic($topic);

        self::assertCount(1, $dailySummary->getTopics());
    }

    public function testRemoveTopic(): void
    {
        $dailySummary = new DailySummary();
        $topic = new Topic();
        $topic->setName('Trabajo');

        $dailySummary->addTopic($topic);
        $dailySummary->removeTopic($topic);

        self::assertCount(0, $dailySummary->getTopics());
    }
}
