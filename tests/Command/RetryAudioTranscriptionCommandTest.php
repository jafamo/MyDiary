<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\RetryAudioTranscriptionCommand;
use App\Entity\AudioRecordingStatus;
use App\Repository\AudioRecordingRepository;
use App\Service\AudioRecordingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class RetryAudioTranscriptionCommandTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AudioRecordingRepository $audioRecordingRepository;
    private AudioRecordingService $audioRecordingService;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->audioRecordingRepository = $container->get(AudioRecordingRepository::class);
        $this->audioRecordingService = $container->get(AudioRecordingService::class);

        $this->cleanUp();

        $application = new Application();
        $application->addCommand(new RetryAudioTranscriptionCommand($this->audioRecordingRepository, $this->audioRecordingService));
        $this->commandTester = new CommandTester($application->find('app:audio:retry-transcription'));
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    public function testRetriesErrorRecordByExplicitId(): void
    {
        $this->audioRecordingService->receive('msg-retry-1', 'file-retry-1', 10, fn () => '/data/audio/file-retry-1.ogg');
        $audioRecording = $this->audioRecordingRepository->findOneByTelegramFileUniqueId('file-retry-1');
        $audioRecording->setStatus(AudioRecordingStatus::ERROR)->setErrorCode('TIMEOUT');
        $this->entityManager->flush();

        $this->commandTester->execute(['ids' => [$audioRecording->getId()], '--yes' => true]);

        $this->commandTester->assertCommandIsSuccessful();
        $this->entityManager->refresh($audioRecording);
        self::assertSame(AudioRecordingStatus::PENDING, $audioRecording->getStatus());
        self::assertNull($audioRecording->getErrorCode());
    }

    public function testRetriesStuckPendingRecordsAutomatically(): void
    {
        $this->audioRecordingService->receive('msg-retry-2', 'file-retry-2', 10, fn () => '/data/audio/file-retry-2.ogg');
        $audioRecording = $this->audioRecordingRepository->findOneByTelegramFileUniqueId('file-retry-2');
        $audioRecording->setReceivedAt(new \DateTimeImmutable('-1 hour'));
        $this->entityManager->flush();

        $this->commandTester->execute(['--yes' => true, '--pending-minutes' => '15']);

        $this->commandTester->assertCommandIsSuccessful();
        $this->entityManager->refresh($audioRecording);
        self::assertSame(AudioRecordingStatus::PENDING, $audioRecording->getStatus());
        self::assertStringContainsString('1 audio(s) reencolado(s)', $this->commandTester->getDisplay());
    }

    public function testRecentPendingRecordIsNotConsideredStuck(): void
    {
        $this->audioRecordingService->receive('msg-retry-3', 'file-retry-3', 10, fn () => '/data/audio/file-retry-3.ogg');

        $this->commandTester->execute(['--yes' => true, '--pending-minutes' => '15']);

        $this->commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('No hay audios que reencolar', $this->commandTester->getDisplay());
    }

    public function testTranscribedRecordIsSkipped(): void
    {
        $this->audioRecordingService->receive('msg-retry-4', 'file-retry-4', 10, fn () => '/data/audio/file-retry-4.ogg');
        $audioRecording = $this->audioRecordingRepository->findOneByTelegramFileUniqueId('file-retry-4');
        $audioRecording->setStatus(AudioRecordingStatus::TRANSCRIBED);
        $this->entityManager->flush();

        $this->commandTester->execute(['ids' => [$audioRecording->getId()], '--yes' => true]);

        $this->commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('No hay audios que reencolar', $this->commandTester->getDisplay());
        $this->entityManager->refresh($audioRecording);
        self::assertSame(AudioRecordingStatus::TRANSCRIBED, $audioRecording->getStatus());
    }

    public function testCancelsWithoutYesWhenNotConfirmed(): void
    {
        $this->audioRecordingService->receive('msg-retry-5', 'file-retry-5', 10, fn () => '/data/audio/file-retry-5.ogg');
        $audioRecording = $this->audioRecordingRepository->findOneByTelegramFileUniqueId('file-retry-5');
        $audioRecording->setStatus(AudioRecordingStatus::ERROR)->setErrorCode('TIMEOUT');
        $this->entityManager->flush();

        $this->commandTester->setInputs(['no']);
        $this->commandTester->execute(['ids' => [$audioRecording->getId()]]);

        $this->commandTester->assertCommandIsSuccessful();
        $this->entityManager->refresh($audioRecording);
        self::assertSame(AudioRecordingStatus::ERROR, $audioRecording->getStatus());
    }

    private function cleanUp(): void
    {
        foreach (['msg-retry-1', 'msg-retry-2', 'msg-retry-3', 'msg-retry-4', 'msg-retry-5'] as $telegramMessageId) {
            $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId($telegramMessageId);

            if (null !== $audioRecording) {
                $this->entityManager->remove($audioRecording);
            }
        }

        $this->entityManager->flush();
    }
}
