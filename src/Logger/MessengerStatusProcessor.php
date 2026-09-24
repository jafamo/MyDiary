<?php

declare(strict_types=1);

namespace App\Logger;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Añade "status" (en "extra") a los logs del canal "messenger" con el estado
 * del mensaje, deducido del texto que emite Symfony Messenger, para poder
 * filtrar por él en Kibana. Los fragmentos no contienen placeholders, así que
 * funcionan igual con la plantilla ("{class}") que con el mensaje interpolado.
 */
#[AsMonologProcessor(channel: 'messenger')]
class MessengerStatusProcessor implements ProcessorInterface
{
    /**
     * Fragmento del mensaje => status. El orden importa: gana el primero que encaje.
     */
    private const STATUSES = [
        'Sending for retry' => 'retry',
        'Removing from transport' => 'failed',
        'will be sent to the failure transport' => 'rejected',
        'was handled successfully' => 'acknowledged',
        'No handler for message' => 'no_handler',
        'Received message ' => 'received',
        'Sending message ' => 'sent',
        ' handled by ' => 'handled',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        if ('messenger' !== $record->channel) {
            return $record;
        }

        foreach (self::STATUSES as $fragment => $status) {
            if (str_contains($record->message, $fragment)) {
                return $record->with(extra: $record->extra + ['status' => $status]);
            }
        }

        return $record;
    }
}
