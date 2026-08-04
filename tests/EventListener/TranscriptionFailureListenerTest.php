<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Contract\TranscriptionException;
use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\EventListener\TranscriptionFailureListener;
use App\Message\TranscribeAudioMessage;
use App\Repository\AudioRecordingRepository;
use App\Service\Telegram\TelegramClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranscriptionFailureListenerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AudioRecordingRepository $audioRecordingRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->audioRecordingRepository = self::getContainer()->get(AudioRecordingRepository::class);
    }

    protected function tearDown(): void
    {
        foreach (['listener-msg-1', 'listener-msg-2'] as $telegramMessageId) {
            $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId($telegramMessageId);

            if (null !== $audioRecording) {
                $this->entityManager->remove($audioRecording);
            }
        }
        $this->entityManager->flush();

        parent::tearDown();
    }

    public function testFinalFailureMarksRecordAsErrorAndNotifies(): void
    {
        self::getContainer()->set(HttpClientInterface::class, new MockHttpClient(new MockResponse('{"ok":true}')));

        $audioRecording = $this->persistAudioRecording('listener-msg-1', 'listener-file-1');

        $listener = $this->createListener();

        $exception = new TranscriptionException('TIMEOUT', 'No se pudo contactar con el servicio de transcripción.');
        $envelope = new Envelope(new TranscribeAudioMessage($audioRecording->getId()));
        $event = new WorkerMessageFailedEvent($envelope, 'async', $exception);

        $listener($event);

        $this->entityManager->refresh($audioRecording);

        self::assertSame(AudioRecordingStatus::ERROR, $audioRecording->getStatus());
        self::assertSame('TIMEOUT', $audioRecording->getErrorCode());
        self::assertSame('No se pudo contactar con el servicio de transcripción.', $audioRecording->getErrorMessage());
    }

    public function testRetryInProgressDoesNotChangeRecord(): void
    {
        $audioRecording = $this->persistAudioRecording('listener-msg-2', 'listener-file-2');

        $listener = $this->createListener();

        $exception = new TranscriptionException('TIMEOUT', 'fallo transitorio');
        $envelope = new Envelope(new TranscribeAudioMessage($audioRecording->getId()));
        $event = new WorkerMessageFailedEvent($envelope, 'async', $exception);
        $event->setForRetry();

        $listener($event);

        $this->entityManager->refresh($audioRecording);

        self::assertSame(AudioRecordingStatus::PENDING, $audioRecording->getStatus());
        self::assertNull($audioRecording->getErrorCode());
    }

    private function createListener(): TranscriptionFailureListener
    {
        return new TranscriptionFailureListener(
            $this->audioRecordingRepository,
            $this->entityManager,
            self::getContainer()->get(TelegramClient::class),
            self::getContainer()->get('logger'),
            $_ENV['TELEGRAM_AUTHORIZED_CHAT_ID'],
        );
    }

    private function persistAudioRecording(string $telegramMessageId, string $telegramFileUniqueId): AudioRecording
    {
        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId($telegramMessageId)
            ->setTelegramFileUniqueId($telegramFileUniqueId)
            ->setFilePath('/data/audio/'.$telegramFileUniqueId.'.ogg')
            ->setReceivedAt(new \DateTimeImmutable())
            ->setDurationSeconds(5)
        ;
        $this->entityManager->persist($audioRecording);
        $this->entityManager->flush();

        return $audioRecording;
    }
}
