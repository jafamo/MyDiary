<?php

declare(strict_types=1);

namespace App\Contract;

interface SummaryGeneratorInterface
{
    /**
     * @param list<string> $transcriptions
     *
     * @return array{summary: string, topics: list<string>}
     *
     * @throws SummaryGenerationException si la generación falla
     */
    public function generate(array $transcriptions): array;
}
