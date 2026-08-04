<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Contract\TranscriberInterface;
use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Message\TranscribeAudioMessage;
use App\MessageHandler\TranscribeAudioMessageHandler;
use App\Repository\AudioRecordingRepository;
use App\Service\Telegram\TelegramClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranscribeAudioMessageHandlerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AudioRecordingRepository $audioRecordingRepository;
    private string $audioFilePath;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->audioRecordingRepository = self::getContainer()->get(AudioRecordingRepository::class);

        $this->audioFilePath = tempnam(sys_get_temp_dir(), 'handler-test-');
        file_put_contents($this->audioFilePath, 'fake-audio-bytes');
    }

    protected function tearDown(): void
    {
        $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId('handler-msg-1');

        if (null !== $audioRecording) {
            $this->entityManager->remove($audioRecording);
            $this->entityManager->flush();
        }

        if (file_exists($this->audioFilePath)) {
            unlink($this->audioFilePath);
        }

        parent::tearDown();
    }

    public function testSuccessfulTranscriptionUpdatesRecordAndNotifiesUser(): void
    {
        self::getContainer()->set(HttpClientInterface::class, new MockHttpClient(new MockResponse('{"ok":true}')));

        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId('handler-msg-1')
            ->setTelegramFileUniqueId('handler-file-1')
            ->setFilePath($this->audioFilePath)
            ->setReceivedAt(new \DateTimeImmutable())
            ->setDurationSeconds(5)
        ;
        $this->entityManager->persist($audioRecording);
        $this->entityManager->flush();

        $fakeTranscriber = new class () implements TranscriberInterface {
            public function transcribe(string $audioFilePath): string
            {
                return 'texto transcrito';
            }
        };

        $handler = new TranscribeAudioMessageHandler(
            $this->audioRecordingRepository,
            $fakeTranscriber,
            self::getContainer()->get(TelegramClient::class),
            $this->entityManager,
            self::getContainer()->get('logger'),
            sys_get_temp_dir(),
            $_ENV['TELEGRAM_AUTHORIZED_CHAT_ID'],
        );

        $handler(new TranscribeAudioMessage($audioRecording->getId()));

        $this->entityManager->refresh($audioRecording);

        self::assertSame(AudioRecordingStatus::TRANSCRIBED, $audioRecording->getStatus());
        self::assertNotNull($audioRecording->getTranscription());
        self::assertSame('texto transcrito', $audioRecording->getTranscription()->getContent());

        $exportPath = $audioRecording->getTranscription()->getFilePath();
        self::assertFileExists($exportPath);
        unlink($exportPath);
    }
}
