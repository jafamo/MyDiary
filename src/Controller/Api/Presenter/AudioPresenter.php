<?php

declare(strict_types=1);

namespace App\Controller\Api\Presenter;

use App\Controller\Api\ApiFormatter;
use App\Entity\AudioRecording;
use App\Entity\Transcription;

/**
 * Forma JSON de un audio en la API. No expone rutas de ficheros, el hash de contenido
 * ni los identificadores de Telegram.
 */
class AudioPresenter
{
    /**
     * @return array{id: int|null, status: string, source: string, duration_seconds: int, received_at: string|null}
     */
    public function brief(AudioRecording $audioRecording): array
    {
        return [
            'id' => $audioRecording->getId(),
            'status' => $audioRecording->getStatus()->value,
            'source' => $audioRecording->getSource()->value,
            'duration_seconds' => $audioRecording->getDurationSeconds(),
            'received_at' => ApiFormatter::instant($audioRecording->getReceivedAt()),
        ];
    }

    /**
     * El audio con el motivo de su error y su transcripción (null si aún no existe).
     *
     * @return array<string, mixed>
     */
    public function detail(AudioRecording $audioRecording): array
    {
        $transcription = $audioRecording->getTranscription();

        return $this->brief($audioRecording) + [
            'error_code' => $audioRecording->getErrorCode(),
            'error_message' => $audioRecording->getErrorMessage(),
            'transcription' => null !== $transcription ? $this->transcription($transcription) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transcription(Transcription $transcription): array
    {
        return [
            'id' => $transcription->getId(),
            'content' => $transcription->getContent(),
            'edited_manually' => $transcription->isEditedManually(),
            'model' => $transcription->getModel(),
            'processing_ms' => $transcription->getProcessingMs(),
            'created_at' => ApiFormatter::instant($transcription->getCreatedAt()),
            'updated_at' => ApiFormatter::instant($transcription->getUpdatedAt()),
        ];
    }
}
