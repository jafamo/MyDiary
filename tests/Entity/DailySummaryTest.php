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

    public function testUsageMetricsDefaultToNull(): void
    {
        $dailySummary = new DailySummary();

        self::assertNull($dailySummary->getPromptTokens());
        self::assertNull($dailySummary->getCompletionTokens());
        self::assertNull($dailySummary->getTotalTokens());
        self::assertNull($dailySummary->getGenerationMs());
        self::assertNull($dailySummary->getModel());
    }

    public function testTotalTokensSumsPromptAndCompletion(): void
    {
        $dailySummary = new DailySummary();
        $dailySummary
            ->setPromptTokens(2980)
            ->setCompletionTokens(432)
            ->setGenerationMs(38000)
            ->setModel('qwen2.5:7b')
        ;

        self::assertSame(3412, $dailySummary->getTotalTokens());
        self::assertSame(38000, $dailySummary->getGenerationMs());
        self::assertSame('qwen2.5:7b', $dailySummary->getModel());
    }

    public function testTotalTokensIsNullWhenCompletionIsMissing(): void
    {
        $dailySummary = new DailySummary();
        $dailySummary->setPromptTokens(2980);

        self::assertNull($dailySummary->getTotalTokens());
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
