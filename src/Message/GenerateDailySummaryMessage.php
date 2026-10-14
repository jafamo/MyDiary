<?php

declare(strict_types=1);

namespace App\Message;

final class GenerateDailySummaryMessage
{
    /**
     * @param string $date día del resumen, `AAAA-MM-DD` en la zona horaria de la aplicación
     */
    public function __construct(
        public readonly string $date,
    ) {
    }
}
