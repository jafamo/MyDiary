<?php

declare(strict_types=1);

namespace App\Contract;

interface SummaryGeneratorInterface
{
    /**
     * @param list<string> $transcriptions
     *
     * @return array{summary: string, topics: list<string>, legend: list<array{emoji: string, meaning: string}>, usage: array{promptTokens: ?int, completionTokens: ?int, model: string}}
     *
     * @throws SummaryGenerationException si la generación falla
     */
    public function generate(array $transcriptions): array;
}
