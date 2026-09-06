<?php

declare(strict_types=1);

namespace App\Logger;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

/**
 * Aplana los campos de "context" y "extra" al nivel raíz del JSON, para que
 * Filebeat/Elasticsearch los indexe como campos propios (p. ej. "error_code")
 * en vez de quedar anidados bajo "context.error_code" o "extra.error_code".
 */
class FlattenedContextJsonFormatter extends JsonFormatter
{
    public function format(LogRecord $record): string
    {
        $normalized = $this->normalizeRecord($record);

        foreach (['context', 'extra'] as $key) {
            $values = $normalized[$key] ?? null;
            unset($normalized[$key]);

            if (!\is_array($values)) {
                continue;
            }

            foreach ($values as $name => $value) {
                if (!\array_key_exists($name, $normalized)) {
                    $normalized[$name] = $value;
                }
            }
        }

        return $this->toJson($normalized, true).($this->isAppendingNewlines() ? "\n" : '');
    }
}
