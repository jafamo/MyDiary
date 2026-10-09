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

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $telegramMessageId = null;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $telegramFileUniqueId = null;

    #[ORM\Column(length: 16, enumType: AudioSource::class, options: ['default' => 'telegram'])]
    private AudioSource $source = AudioSource::TELEGRAM;

    /** SHA-256 del fichero; solo lo tienen los audios subidos desde la app. */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $contentHash = null;

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

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\OneToOne(mappedBy: 'audioRecording', targetEntity: Transcription::class, cascade: ['remove'])]
    private ?Transcription $transcription = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTelegramMessageId(): ?string
    {
        return $this->telegramMessageId;
    }

    public function setTelegramMessageId(?string $telegramMessageId): static
    {
        $this->telegramMessageId = $telegramMessageId;

        return $this;
    }

    public function getTelegramFileUniqueId(): ?string
    {
        return $this->telegramFileUniqueId;
    }

    public function setTelegramFileUniqueId(?string $telegramFileUniqueId): static
    {
        $this->telegramFileUniqueId = $telegramFileUniqueId;

        return $this;
    }

    public function getSource(): AudioSource
    {
        return $this->source;
    }

    public function setSource(AudioSource $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getContentHash(): ?string
    {
        return $this->contentHash;
    }

    public function setContentHash(?string $contentHash): static
    {
        $this->contentHash = $contentHash;

        return $this;
    }

    /**
     * Clave estable del audio para nombrar sus ficheros: el identificador de Telegram o,
     * en los audios subidos desde la app, el hash del contenido.
     */
    public function getStorageKey(): string
    {
        return $this->telegramFileUniqueId ?? $this->contentHash ?? throw new \LogicException('El audio no tiene identificador de Telegram ni hash de contenido.');
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
