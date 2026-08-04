<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TranscriptionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TranscriptionRepository::class)]
class Transcription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'transcription', targetEntity: AudioRecording::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AudioRecording $audioRecording;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(length: 1024)]
    private string $filePath;

    #[ORM\Column]
    private bool $editedManually = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAudioRecording(): AudioRecording
    {
        return $this->audioRecording;
    }

    public function setAudioRecording(AudioRecording $audioRecording): static
    {
        $this->audioRecording = $audioRecording;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

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

    public function isEditedManually(): bool
    {
        return $this->editedManually;
    }

    public function setEditedManually(bool $editedManually): static
    {
        $this->editedManually = $editedManually;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
