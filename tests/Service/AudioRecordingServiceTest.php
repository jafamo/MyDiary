<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\AudioRecordingStatus;
use App\Entity\AudioSource;
use App\Repository\AudioRecordingRepository;
use App\Service\AudioRecordingReceiveResult;
use App\Service\AudioRecordingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class AudioRecordingServiceTest extends KernelTestCase
{
    private const HASH = '5f2b1c0e9a7d4c3b8e6f1a2d3c4b5a69788796a5b4c3d2e1f0a9b8c7d6e5f401';

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

        $upload = $this->audioRecordingRepository->findOneByContentHash(self::HASH);
        if (null !== $upload) {
            $this->entityManager->remove($upload);
        }

        $this->entityManager->flush();
    }

    public function testAudioFromTelegramKeepsTelegramAsSource(): void
    {
        $this->service->receive('msg-1', 'file-1', 42, fn () => '/data/audio/file-1.ogg');

        $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId('msg-1');

        self::assertSame(AudioSource::TELEGRAM, $audioRecording->getSource());
        self::assertNull($audioRecording->getContentHash());
    }

    public function testNewUploadIsCreatedAndMessageDispatched(): void
    {
        $upload = $this->service->receiveUpload(self::HASH, fn () => ['filePath' => '/data/audio/upload.m4a', 'durationSeconds' => 37]);

        self::assertSame(AudioRecordingReceiveResult::CREATED, $upload->result);

        $audioRecording = $this->audioRecordingRepository->findOneByContentHash(self::HASH);
        self::assertNotNull($audioRecording);
        self::assertSame($audioRecording->getId(), $upload->audioRecording->getId());
        self::assertSame(AudioSource::APP, $audioRecording->getSource());
        self::assertSame(AudioRecordingStatus::PENDING, $audioRecording->getStatus());
        self::assertSame('/data/audio/upload.m4a', $audioRecording->getFilePath());
        self::assertSame(37, $audioRecording->getDurationSeconds());
        self::assertNull($audioRecording->getTelegramMessageId());
        self::assertNull($audioRecording->getTelegramFileUniqueId());
        self::assertSame([$audioRecording->getId()], $this->dispatchedAudioRecordingIds());
    }

    public function testDuplicateUploadDoesNotStoreNorDispatch(): void
    {
        $first = $this->service->receiveUpload(self::HASH, fn () => ['filePath' => '/data/audio/upload.m4a', 'durationSeconds' => 37]);

        $storeCalled = false;
        $second = $this->service->receiveUpload(self::HASH, function () use (&$storeCalled): array {
            $storeCalled = true;

            return ['filePath' => '/data/audio/upload.m4a', 'durationSeconds' => 37];
        });

        self::assertSame(AudioRecordingReceiveResult::DUPLICATE_FILE, $second->result);
        self::assertSame($first->audioRecording->getId(), $second->audioRecording->getId());
        self::assertFalse($storeCalled);
        self::assertCount(1, $this->dispatchedAudioRecordingIds());
    }

    public function testUploadAfterErrorResetsStatusAndDispatchesNewMessage(): void
    {
        $audioRecording = $this->service->receiveUpload(self::HASH, fn () => ['filePath' => '/data/audio/upload.m4a', 'durationSeconds' => 37])->audioRecording;
        $audioRecording
            ->setStatus(AudioRecordingStatus::ERROR)
            ->setErrorCode('TIMEOUT')
            ->setErrorMessage('algo falló')
        ;
        $this->entityManager->flush();

        $storeCalled = false;
        $upload = $this->service->receiveUpload(self::HASH, function () use (&$storeCalled): array {
            $storeCalled = true;

            return ['filePath' => '/data/audio/upload.m4a', 'durationSeconds' => 37];
        });

        self::assertSame(AudioRecordingReceiveResult::RETRYING_AFTER_ERROR, $upload->result);
        self::assertFalse($storeCalled, 'No debe volver a guardar el fichero en un reintento tras error');

        $this->entityManager->refresh($audioRecording);

        self::assertSame(AudioRecordingStatus::PENDING, $audioRecording->getStatus());
        self::assertNull($audioRecording->getErrorCode());
        self::assertNull($audioRecording->getErrorMessage());
        self::assertSame([$audioRecording->getId(), $audioRecording->getId()], $this->dispatchedAudioRecordingIds());
    }

    public function testRejectedUploadCreatesNothing(): void
    {
        try {
            $this->service->receiveUpload(self::HASH, fn () => throw new \RuntimeException('no es un audio'));
            self::fail('Se esperaba la excepción del guardado');
        } catch (\RuntimeException) {
        }

        self::assertNull($this->audioRecordingRepository->findOneByContentHash(self::HASH));
        self::assertSame([], $this->dispatchedAudioRecordingIds());
    }

    /**
     * @return list<int>
     */
    private function dispatchedAudioRecordingIds(): array
    {
        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async');

        return array_map(
            static fn ($envelope): int => $envelope->getMessage()->audioRecordingId,
            array_values($transport->getSent()),
        );
    }
}
