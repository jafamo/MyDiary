<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DailySummaryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Pgvector\Vector;

#[ORM\Entity(repositoryClass: DailySummaryRepository::class)]
class DailySummary
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'date_immutable', unique: true)]
    private \DateTimeImmutable $date;

    #[ORM\Column(type: 'text')]
    private string $summaryText;

    #[ORM\Column]
    private \DateTimeImmutable $generatedAt;

    /**
     * @var list<array{emoji: string, meaning: string}>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $emojiLegend = null;

    #[ORM\Column(type: 'vector', length: 768, nullable: true)]
    private ?Vector $embedding = null;

    #[ORM\Column(nullable: true)]
    private ?int $promptTokens = null;

    #[ORM\Column(nullable: true)]
    private ?int $completionTokens = null;

    #[ORM\Column(nullable: true)]
    private ?int $generationMs = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $model = null;

    /**
     * @var Collection<int, Topic>
     */
    #[ORM\ManyToMany(targetEntity: Topic::class, inversedBy: 'dailySummaries')]
    #[ORM\JoinTable(name: 'daily_summary_topic')]
    private Collection $topics;

    public function __construct()
    {
        $this->topics = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getSummaryText(): string
    {
        return $this->summaryText;
    }

    public function setSummaryText(string $summaryText): static
    {
        $this->summaryText = $summaryText;

        return $this;
    }

    public function getGeneratedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(\DateTimeImmutable $generatedAt): static
    {
        $this->generatedAt = $generatedAt;

        return $this;
    }

    /**
     * @return list<array{emoji: string, meaning: string}>|null
     */
    public function getEmojiLegend(): ?array
    {
        return $this->emojiLegend;
    }

    /**
     * @param list<array{emoji: string, meaning: string}>|null $emojiLegend
     */
    public function setEmojiLegend(?array $emojiLegend): static
    {
        $this->emojiLegend = $emojiLegend;

        return $this;
    }

    public function getEmbedding(): ?Vector
    {
        return $this->embedding;
    }

    /**
     * @param list<float> $embedding
     */
    public function setEmbedding(?array $embedding): static
    {
        $this->embedding = null === $embedding ? null : new Vector($embedding);

        return $this;
    }

    public function getPromptTokens(): ?int
    {
        return $this->promptTokens;
    }

    public function setPromptTokens(?int $promptTokens): static
    {
        $this->promptTokens = $promptTokens;

        return $this;
    }

    public function getCompletionTokens(): ?int
    {
        return $this->completionTokens;
    }

    public function setCompletionTokens(?int $completionTokens): static
    {
        $this->completionTokens = $completionTokens;

        return $this;
    }

    public function getTotalTokens(): ?int
    {
        if (null === $this->promptTokens || null === $this->completionTokens) {
            return null;
        }

        return $this->promptTokens + $this->completionTokens;
    }

    public function getGenerationMs(): ?int
    {
        return $this->generationMs;
    }

    public function setGenerationMs(?int $generationMs): static
    {
        $this->generationMs = $generationMs;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * @return Collection<int, Topic>
     */
    public function getTopics(): Collection
    {
        return $this->topics;
    }

    public function addTopic(Topic $topic): static
    {
        if (!$this->topics->contains($topic)) {
            $this->topics->add($topic);
        }

        return $this;
    }

    public function removeTopic(Topic $topic): static
    {
        $this->topics->removeElement($topic);

        return $this;
    }
}
