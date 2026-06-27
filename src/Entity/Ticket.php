<?php

namespace App\Entity;

use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use App\Repository\TicketRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Ticket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(min: 5, max: 255, minMessage: 'Le titre doit faire au moins {{ limit }} caractères.')]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(min: 10, minMessage: 'La description doit faire au moins {{ limit }} caractères.')]
    private ?string $description = null;

    // Priorité via PHP 8.1 Enum : basse, moyenne, haute, urgente
    #[ORM\Column(length: 20, enumType: TicketPriority::class)]
    #[Assert\NotNull(message: 'La priorité est obligatoire.')]
    private ?TicketPriority $priority = TicketPriority::MEDIUM;

    // Statut via PHP 8.1 Enum : ouvert, en_cours, résolu, fermé
    #[ORM\Column(length: 20, enumType: TicketStatus::class)]
    private ?TicketStatus $status = TicketStatus::OPEN;

    // Relation ManyToOne : plusieurs tickets pour un créateur
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'createdTickets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $creator = null;

    // Agent assigné : nullable car un ticket peut ne pas encore être pris en charge
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'assignedTickets')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $assignedAgent = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    // Réponses associées à ce ticket
    #[ORM\OneToMany(targetEntity: TicketResponse::class, mappedBy: 'ticket', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $responses;

    public function __construct()
    {
        $this->responses = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    // Lifecycle callback : met à jour updatedAt automatiquement à chaque modification
    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPriority(): ?TicketPriority
    {
        return $this->priority;
    }

    public function setPriority(TicketPriority $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function getStatus(): ?TicketStatus
    {
        return $this->status;
    }

    public function setStatus(TicketStatus $status): static
    {
        // Si on passe à "résolu", on enregistre la date de résolution
        if ($status === TicketStatus::RESOLVED && $this->status !== TicketStatus::RESOLVED) {
            $this->resolvedAt = new \DateTimeImmutable();
        }
        $this->status = $status;
        return $this;
    }

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    public function setCreator(?User $creator): static
    {
        $this->creator = $creator;
        return $this;
    }

    public function getAssignedAgent(): ?User
    {
        return $this->assignedAgent;
    }

    public function setAssignedAgent(?User $assignedAgent): static
    {
        $this->assignedAgent = $assignedAgent;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    /**
     * @return Collection<int, TicketResponse>
     */
    public function getResponses(): Collection
    {
        return $this->responses;
    }

    public function addResponse(TicketResponse $response): static
    {
        if (!$this->responses->contains($response)) {
            $this->responses->add($response);
            $response->setTicket($this);
        }
        return $this;
    }

    public function removeResponse(TicketResponse $response): static
    {
        if ($this->responses->removeElement($response)) {
            if ($response->getTicket() === $this) {
                $response->setTicket(null);
            }
        }
        return $this;
    }

    public function isOpen(): bool
    {
        return $this->status === TicketStatus::OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === TicketStatus::CLOSED;
    }

    public function isAssigned(): bool
    {
        return $this->assignedAgent !== null;
    }
}
