<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Entity\Transcription;
use App\Service\AudioRecordingDeleter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class AudioRecordingDeleterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/audio-deleter-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory.'/*') ?: []);
        rmdir($this->directory);
    }

    public function testOnlyFinishedAudiosCanBeDeleted(): void
    {
        $deleter = new AudioRecordingDeleter($this->createStub(EntityManagerInterface::class));

        self::assertTrue($deleter->canDelete($this->audio(AudioRecordingStatus::TRANSCRIBED)));
        self::assertTrue($deleter->canDelete($this->audio(AudioRecordingStatus::ERROR)));
        self::assertFalse($deleter->canDelete($this->audio(AudioRecordingStatus::PENDING)));
    }

    public function testDeletesFilesTranscriptionAndAudio(): void
    {
        $audioRecording = $this->audio(AudioRecordingStatus::TRANSCRIBED);
        touch($audioRecording->getFilePath());
        $transcription = (new Transcription())->setFilePath($this->directory.'/audio.txt');
        touch($transcription->getFilePath());
        // Lado inverso de la relación, que en la aplicación rellena Doctrine al cargar el audio.
        (new \ReflectionProperty(AudioRecording::class, 'transcription'))->setValue($audioRecording, $transcription);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $removed = [];
        $entityManager->method('remove')->willReturnCallback(static function (object $entity) use (&$removed): void {
            $removed[] = $entity;
        });
        $entityManager->expects(self::once())->method('flush');

        (new AudioRecordingDeleter($entityManager))->delete($audioRecording);

        self::assertSame([$transcription, $audioRecording], $removed);
        self::assertFileDoesNotExist($audioRecording->getFilePath());
        self::assertFileDoesNotExist($transcription->getFilePath());
    }

    public function testMissingFileDoesNotStopTheDeletion(): void
    {
        $audioRecording = $this->audio(AudioRecordingStatus::ERROR);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($audioRecording);
        $entityManager->expects(self::once())->method('flush');

        (new AudioRecordingDeleter($entityManager))->delete($audioRecording);
    }

    private function audio(AudioRecordingStatus $status): AudioRecording
    {
        return (new AudioRecording())
            ->setStatus($status)
            ->setFilePath($this->directory.'/audio.ogg')
        ;
    }
}
