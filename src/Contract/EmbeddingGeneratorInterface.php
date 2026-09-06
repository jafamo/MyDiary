<?php

declare(strict_types=1);

namespace App\Contract;

interface EmbeddingGeneratorInterface
{
    /**
     * @return list<float>
     *
     * @throws EmbeddingGenerationException si la generación falla
     */
    public function generate(string $text): array;
}
