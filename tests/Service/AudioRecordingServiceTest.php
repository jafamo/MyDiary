<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\AudioRecordingStatus;
use App\Message\TranscribeAudioMessage;
use App\Repository\AudioRecordingRepository;
use App\Service\AudioRecordingReceiveResult;
use App\Service\AudioRecordingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AudioRecordingServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AudioRecordingRepository $audioRecordingRepository;
    private AudioRecordingService $service;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->audioRecordingRepository = $container->get(AudioRecordingRepository::class);
        $this->service = $container->get(AudioRecordingService::class);

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    public function testNewAudioIsCreatedAndMessageDispatched(): void
    {
        $downloadCalled = false;

        $result = $this->service->receive('msg-1', 'file-1', 42, function () use (&$downloadCalled): string {
            $downloadCalled = true;

            return '/data/audio/file-1.ogg';
        });

        self::assertSame(AudioRecordingReceiveResult::CREATED, $result);
        self::assertTrue($downloadCalled);

        $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId('msg-1');

        self::assertNotNull($audioRecording);
        self::assertSame(AudioRecordingStatus::PENDING, $audioRecording->getStatus());
        self::assertSame('/data/audio/file-1.ogg', $audioRecording->getFilePath());
        self::assertSame(42, $audioRecording->getDurationSeconds());
    }

    public function testDuplicateMessageIdDoesNotCreateOrDownload(): void
    {
        $this->service->receive('msg-2', 'file-2', 10, fn () => '/data/audio/file-2.ogg');

        $downloadCalled = false;
        $result = $this->service->receive('msg-2', 'file-2', 10, function () use (&$downloadCalled): string {
            $downloadCalled = true;

            return '/data/audio/file-2.ogg';
        });

        self::assertSame(AudioRecordingReceiveResult::DUPLICATE_MESSAGE, $result);
        self::assertFalse($downloadCalled);
    }

    public function testDuplicateFileWithPendingStatusDoesNothing(): void
    {
        $this->service->receive('msg-3', 'file-3', 10, fn () => '/data/audio/file-3.ogg');

        $result = $this->service->receive('msg-3b', 'file-3', 10, fn () => '/data/audio/file-3.ogg');

        self::assertSame(AudioRecordingReceiveResult::DUPLICATE_FILE, $result);
    }

    public function testRetryAfterErrorResetsStatusAndDispatchesNewMessage(): void
    {
        $this->service->receive('msg-4', 'file-4', 10, fn () => '/data/audio/file-4.ogg');

        $audioRecording = $this->audioRecordingRepository->findOneByTelegramFileUniqueId('file-4');
        $audioRecording
            ->setStatus(AudioRecordingStatus::ERROR)
            ->setErrorCode('TIMEOUT')
            ->setErrorMessage('algo falló')
        ;
        $this->entityManager->flush();

        $downloadCalled = false;
        $result = $this->service->receive('msg-4b', 'file-4', 10, function () use (&$downloadCalled): string {
            $downloadCalled = true;

            return '/data/audio/file-4.ogg';
        });

        self::assertSame(AudioRecordingReceiveResult::RETRYING_AFTER_ERROR, $result);
        self::assertFalse($downloadCalled, 'No debe volver a descargar el fichero en un reintento tras error');

        $this->entityManager->refresh($audioRecording);

        self::assertSame(AudioRecordingStatus::PENDING, $audioRecording->getStatus());
        self::assertNull($audioRecording->getErrorCode());
        self::assertNull($audioRecording->getErrorMessage());
        self::assertSame('msg-4b', $audioRecording->getTelegramMessageId());
    }

    private function cleanUp(): void
    {
        foreach (['msg-1', 'msg-2', 'msg-3', 'msg-3b', 'msg-4', 'msg-4b'] as $telegramMessageId) {
            $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId($telegramMessageId);

            if (null !== $audioRecording) {
                $this->entityManager->remove($audioRecording);
            }
        }

        $this->entityManager->flush();
    }
}
