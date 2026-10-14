<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Contract\TranscriptionException;
use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Entity\AudioSource;
use App\EventListener\TranscriptionFailureListener;
use App\Message\TranscribeAudioMessage;
use App\Repository\AudioRecordingRepository;
use App\Service\Telegram\TelegramClient;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\TestHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranscriptionFailureListenerTest extends KernelTestCase
{
    private const APP_CONTENT_HASH = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

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
        $appAudioRecording = $this->audioRecordingRepository->findOneByContentHash(self::APP_CONTENT_HASH);
        if (null !== $appAudioRecording) {
            $this->entityManager->remove($appAudioRecording);
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

    public function testFinalFailureIsLoggedWithTheSourceAndTheTelegramFileId(): void
    {
        self::getContainer()->set(HttpClientInterface::class, new MockHttpClient(new MockResponse('{"ok":true}')));

        $audioRecording = $this->persistAudioRecording('listener-msg-1', 'listener-file-1');

        $this->createListener()($this->failedEvent($audioRecording));

        $records = $this->logRecordsWithEvent('transcription.retry_exhausted');
        self::assertCount(1, $records);
        self::assertSame('telegram', $records[0]->context['audio_source']);
        self::assertSame('listener-file-1', $records[0]->context['telegram_file_unique_id']);
    }

    public function testFinalFailureOfAnAppAudioIsNotifiedAndLoggedWithoutTelegramFileId(): void
    {
        $requests = 0;
        self::getContainer()->set(HttpClientInterface::class, new MockHttpClient(
            static function () use (&$requests): MockResponse {
                ++$requests;

                return new MockResponse('{"ok":true}');
            },
        ));

        $audioRecording = new AudioRecording();
        $audioRecording
            ->setSource(AudioSource::APP)
            ->setContentHash(self::APP_CONTENT_HASH)
            ->setFilePath('/data/audio/'.self::APP_CONTENT_HASH.'.m4a')
            ->setReceivedAt(new \DateTimeImmutable('2020-01-01 10:00:00'))
            ->setDurationSeconds(5)
        ;
        $this->entityManager->persist($audioRecording);
        $this->entityManager->flush();

        $this->createListener()($this->failedEvent($audioRecording));

        $this->entityManager->refresh($audioRecording);
        self::assertSame(AudioRecordingStatus::ERROR, $audioRecording->getStatus());
        self::assertSame(1, $requests, 'El aviso de fallo se envía por Telegram también para los audios de la app');

        $records = $this->logRecordsWithEvent('transcription.retry_exhausted');
        self::assertCount(1, $records);
        self::assertSame('app', $records[0]->context['audio_source']);
        self::assertArrayNotHasKey('telegram_file_unique_id', $records[0]->context);
    }

    private function failedEvent(AudioRecording $audioRecording): WorkerMessageFailedEvent
    {
        return new WorkerMessageFailedEvent(
            new Envelope(new TranscribeAudioMessage($audioRecording->getId())),
            'async',
            new TranscriptionException('TIMEOUT', 'No se pudo contactar con el servicio de transcripción.'),
        );
    }

    /**
     * @return list<\Monolog\LogRecord>
     */
    private function logRecordsWithEvent(string $event): array
    {
        /** @var TestHandler $logHandler */
        // Handler "test" definido solo en when@test (PHPStan analiza el contenedor de dev)
        $logHandler = self::getContainer()->get('monolog.handler.test'); // @phpstan-ignore symfonyContainer.serviceNotFound

        return array_values(array_filter(
            $logHandler->getRecords(),
            static fn ($record) => $event === ($record->context['event'] ?? null),
        ));
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
