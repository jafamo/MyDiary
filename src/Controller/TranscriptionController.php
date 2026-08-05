<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Form\TranscriptionEditType;
use App\Service\TranscriptionEditor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class TranscriptionController
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly TranscriptionEditor $transcriptionEditor,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/transcripcion/{audioRecording}/editar', name: 'app_transcription_edit', methods: ['POST'])]
    public function edit(AudioRecording $audioRecording, Request $request): Response
    {
        $transcription = $audioRecording->getTranscription();

        if (AudioRecordingStatus::TRANSCRIBED !== $audioRecording->getStatus() || null === $transcription) {
            return new Response(status: Response::HTTP_CONFLICT);
        }

        $form = $this->formFactory->create(TranscriptionEditType::class, $transcription);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->transcriptionEditor->applyManualEdit($transcription);

        return new RedirectResponse($request->headers->get('Referer', '/'));
    }

    #[Route('/transcripcion/{audioRecording}/eliminar', name: 'app_transcription_delete', methods: ['POST'])]
    public function delete(AudioRecording $audioRecording, Request $request): Response
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('transcription_delete', (string) $request->request->get('_token')))) {
            return new Response(status: Response::HTTP_FORBIDDEN);
        }

        if (!\in_array($audioRecording->getStatus(), [AudioRecordingStatus::TRANSCRIBED, AudioRecordingStatus::ERROR], true)) {
            return new Response(status: Response::HTTP_CONFLICT);
        }

        $transcription = $audioRecording->getTranscription();

        $this->deleteFileIfExists($audioRecording->getFilePath());
        if (null !== $transcription) {
            $this->deleteFileIfExists($transcription->getFilePath());
            $this->entityManager->remove($transcription);
        }
        $this->entityManager->remove($audioRecording);
        $this->entityManager->flush();

        return new RedirectResponse($request->headers->get('Referer', '/'));
    }

    private function deleteFileIfExists(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
