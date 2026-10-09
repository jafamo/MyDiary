<?php

declare(strict_types=1);

namespace App\Service\Audio;

use App\Contract\AudioProbeException;
use App\Contract\AudioProbeInterface;
use App\Contract\AudioProbeResult;
use Symfony\Component\Process\Process;

class FfprobeAudioProbe implements AudioProbeInterface
{
    private const TIMEOUT_SECONDS = 15;
    private const EXIT_CODE_COMMAND_NOT_FOUND = 127;

    public function probe(string $audioFilePath): AudioProbeResult
    {
        $process = new Process([
            'ffprobe',
            '-v', 'error',
            '-show_entries', 'format=format_name,duration:stream=codec_type:stream_disposition=attached_pic',
            '-of', 'json',
            $audioFilePath,
        ]);
        $process->setTimeout(self::TIMEOUT_SECONDS);
        $process->run();

        if (self::EXIT_CODE_COMMAND_NOT_FOUND === $process->getExitCode()) {
            throw new \RuntimeException('ffprobe no está instalado: no se puede examinar el audio.');
        }

        $data = $process->isSuccessful() ? json_decode($process->getOutput(), true) : null;
        if (!\is_array($data)) {
            throw new AudioProbeException('El fichero no es un audio reconocible.');
        }

        $format = $this->format((string) ($data['format']['format_name'] ?? ''));
        if (null === $format || !$this->hasOnlyAudio($data['streams'] ?? [])) {
            throw new AudioProbeException('El fichero no es un audio en un formato admitido.');
        }

        $duration = (float) ($data['format']['duration'] ?? 0);
        if ($duration <= 0) {
            throw new AudioProbeException('No se pudo determinar la duración del audio.');
        }

        return new AudioProbeResult($format, max(1, (int) round($duration)));
    }

    /**
     * ffprobe da una lista de nombres por contenedor (p. ej. `mov,mp4,m4a,3gp,3g2,mj2`).
     */
    private function format(string $formatName): ?string
    {
        $match = array_intersect(AudioProbeResult::FORMATS, explode(',', $formatName));

        return [] === $match ? null : reset($match);
    }

    /**
     * Al menos una pista de audio y ninguna de vídeo, salvo la carátula incrustada.
     *
     * @param array<int, array<string, mixed>> $streams
     */
    private function hasOnlyAudio(array $streams): bool
    {
        $hasAudio = false;
        foreach ($streams as $stream) {
            $codecType = $stream['codec_type'] ?? null;
            if ('audio' === $codecType) {
                $hasAudio = true;
            } elseif ('video' === $codecType && 1 !== ($stream['disposition']['attached_pic'] ?? 0)) {
                return false;
            }
        }

        return $hasAudio;
    }
}
