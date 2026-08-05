<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Service\AudioRecordingService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;

class AudioRecordingController
{
    public function __construct(
        private readonly AudioRecordingService $audioRecordingService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/audio/{audioRecording}/reintentar', name: 'app_audio_recording_retry', methods: ['POST'])]
    public function retry(AudioRecording $audioRecording, Request $request): Response
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('audio_retry', (string) $request->request->get('_token')))) {
            return new Response(status: Response::HTTP_FORBIDDEN);
        }

        if (AudioRecordingStatus::ERROR !== $audioRecording->getStatus()) {
            return new Response(status: Response::HTTP_CONFLICT);
        }

        $this->audioRecordingService->retryAfterError($audioRecording);

        return new RedirectResponse($request->headers->get('Referer', '/'));
    }
}
