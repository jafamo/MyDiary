<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TopicRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TopicRepository::class)]
class Topic
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $name;

    /**
     * @var Collection<int, DailySummary>
     */
    #[ORM\ManyToMany(targetEntity: DailySummary::class, mappedBy: 'topics')]
    private Collection $dailySummaries;

    public function __construct()
    {
        $this->dailySummaries = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, DailySummary>
     */
    public function getDailySummaries(): Collection
    {
        return $this->dailySummaries;
    }
}
