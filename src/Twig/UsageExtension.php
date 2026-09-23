<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\UsageFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class UsageExtension extends AbstractExtension
{
    private const MISSING = '—';

    public function __construct(
        private readonly UsageFormatter $formatter,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('ai_duration', [$this, 'duration']),
            new TwigFilter('ai_number', [$this, 'number']),
            new TwigFilter('ai_speed', [$this, 'speed']),
        ];
    }

    public function duration(?int $milliseconds): string
    {
        return null === $milliseconds ? self::MISSING : $this->formatter->duration($milliseconds);
    }

    public function number(?int $value): string
    {
        return null === $value ? self::MISSING : $this->formatter->number($value);
    }

    public function speed(?int $milliseconds, ?int $audioSeconds): string
    {
        if (null === $milliseconds || null === $audioSeconds) {
            return self::MISSING;
        }

        return $this->formatter->speed($audioSeconds, $milliseconds);
    }
}
