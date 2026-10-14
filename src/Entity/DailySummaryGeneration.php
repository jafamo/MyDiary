<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DailySummaryGenerationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Estado de la última generación del resumen de un día (una fila por día, que se reescribe en
 * cada generación). Existe aunque el día aún no tenga `DailySummary`.
 */
#[ORM\Entity(repositoryClass: DailySummaryGenerationRepository::class)]
class DailySummaryGeneration
{
    /** Una generación en cola o en curso pedida hace más que esto se da por atascada (worker caído). */
    public const STALL_AFTER_MINUTES = 15;

    public const ERROR_NO_TRANSCRIPTIONS = 'NO_TRANSCRIPTIONS';
    public const ERROR_STALLED = 'STALLED';
    public const ERROR_UNEXPECTED = 'UNEXPECTED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'date_immutable', unique: true)]
    private \DateTimeImmutable $date;

    #[ORM\Column(length: 16, enumType: DailySummaryGenerationStatus::class)]
    private DailySummaryGenerationStatus $status;

    #[ORM\Column]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $errorCode = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    public function __construct(\DateTimeImmutable $date)
    {
        $this->date = $date;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getStatus(): DailySummaryGenerationStatus
    {
        return $this->status;
    }

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * Pedida bajo demanda: pendiente de que el worker la procese.
     */
    public function markQueued(\DateTimeImmutable $now): void
    {
        $this->status = DailySummaryGenerationStatus::QUEUED;
        $this->requestedAt = $now;
        $this->startedAt = null;
        $this->finishedAt = null;
        $this->clearError();
    }

    /**
     * Si no venía de una petición en cola (disparo programado, recheck o comando), empieza ahora.
     */
    public function markRunning(\DateTimeImmutable $now): void
    {
        if (!isset($this->status) || DailySummaryGenerationStatus::QUEUED !== $this->status) {
            $this->requestedAt = $now;
        }
        $this->status = DailySummaryGenerationStatus::RUNNING;
        $this->startedAt = $now;
        $this->finishedAt = null;
        $this->clearError();
    }

    public function markDone(\DateTimeImmutable $now): void
    {
        $this->status = DailySummaryGenerationStatus::DONE;
        $this->finishedAt = $now;
        $this->clearError();
    }

    public function markFailed(\DateTimeImmutable $now, string $errorCode, string $errorMessage): void
    {
        $this->status = DailySummaryGenerationStatus::FAILED;
        $this->finishedAt = $now;
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
    }

    public function isStalled(\DateTimeImmutable $now): bool
    {
        return $this->isQueuedOrRunning()
            && $this->requestedAt < $now->modify(sprintf('-%d minutes', self::STALL_AFTER_MINUTES));
    }

    /**
     * El estado que se muestra: una generación atascada se presenta como fallida.
     */
    public function effectiveStatus(\DateTimeImmutable $now): DailySummaryGenerationStatus
    {
        return $this->isStalled($now) ? DailySummaryGenerationStatus::FAILED : $this->status;
    }

    public function isInProgress(\DateTimeImmutable $now): bool
    {
        return $this->isQueuedOrRunning() && !$this->isStalled($now);
    }

    private function isQueuedOrRunning(): bool
    {
        return \in_array($this->status, [DailySummaryGenerationStatus::QUEUED, DailySummaryGenerationStatus::RUNNING], true);
    }

    private function clearError(): void
    {
        $this->errorCode = null;
        $this->errorMessage = null;
    }
}
