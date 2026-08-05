<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AudioRecordingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AudioRecordingRepository::class)]
class AudioRecording
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $telegramMessageId;

    #[ORM\Column(length: 255, unique: true)]
    private string $telegramFileUniqueId;

    #[ORM\Column(length: 1024)]
    private string $filePath;

    #[ORM\Column]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column(enumType: AudioRecordingStatus::class)]
    private AudioRecordingStatus $status = AudioRecordingStatus::PENDING;

    #[ORM\Column]
    private int $durationSeconds;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $errorCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\OneToOne(mappedBy: 'audioRecording', targetEntity: Transcription::class, cascade: ['remove'])]
    private ?Transcription $transcription = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTelegramMessageId(): string
    {
        return $this->telegramMessageId;
    }

    public function setTelegramMessageId(string $telegramMessageId): static
    {
        $this->telegramMessageId = $telegramMessageId;

        return $this;
    }

    public function getTelegramFileUniqueId(): string
    {
        return $this->telegramFileUniqueId;
    }

    public function setTelegramFileUniqueId(string $telegramFileUniqueId): static
    {
        $this->telegramFileUniqueId = $telegramFileUniqueId;

        return $this;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(\DateTimeImmutable $receivedAt): static
    {
        $this->receivedAt = $receivedAt;

        return $this;
    }

    public function getStatus(): AudioRecordingStatus
    {
        return $this->status;
    }

    public function setStatus(AudioRecordingStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDurationSeconds(): int
    {
        return $this->durationSeconds;
    }

    public function setDurationSeconds(int $durationSeconds): static
    {
        $this->durationSeconds = $durationSeconds;

        return $this;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function setErrorCode(?string $errorCode): static
    {
        $this->errorCode = $errorCode;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getTranscription(): ?Transcription
    {
        return $this->transcription;
    }
}
