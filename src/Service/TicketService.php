<?php

namespace App\Service;

use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\TicketStatus;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Contient toute la logique métier des tickets.
 * Les controllers appellent ce service — ils ne manipulent jamais l'EntityManager directement.
 */
class TicketService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TicketRepository $ticketRepository,
    ) {}

    /**
     * Crée un ticket et l'associe à son créateur.
     */
    public function createTicket(Ticket $ticket, User $creator): void
    {
        $ticket->setCreator($creator);
        $ticket->setStatus(TicketStatus::OPEN);

        $this->em->persist($ticket);
        $this->em->flush();
    }

    /**
     * Un agent s'assigne un ticket.
     * Règle métier : on ne peut assigner que si le ticket est ouvert ou en cours.
     */
    public function assignAgent(Ticket $ticket, User $agent): void
    {
        if ($ticket->getStatus() === TicketStatus::CLOSED) {
            throw new \LogicException('Impossible d\'assigner un ticket fermé.');
        }

        $ticket->setAssignedAgent($agent);

        // Si le ticket est encore "ouvert", on le passe automatiquement "en cours"
        if ($ticket->getStatus() === TicketStatus::OPEN) {
            $ticket->setStatus(TicketStatus::IN_PROGRESS);
        }

        $this->em->flush();
    }

    /**
     * Change le statut d'un ticket.
     * Règle métier : un ticket fermé ne peut pas être réouvert par un agent (seulement admin).
     */
    public function changeStatus(Ticket $ticket, TicketStatus $newStatus, User $actor): void
    {
        if ($ticket->getStatus() === TicketStatus::CLOSED && !$actor->isAdmin()) {
            throw new AccessDeniedException('Seul un admin peut réouvrir un ticket fermé.');
        }

        $ticket->setStatus($newStatus);
        $this->em->flush();
    }

    /**
     * Vérifie qu'un utilisateur a le droit de voir un ticket.
     * Un user ne voit que ses propres tickets. Un agent/admin voit tout.
     */
    public function canUserViewTicket(Ticket $ticket, User $user): bool
    {
        if ($user->isAgent()) {
            return true;
        }

        return $ticket->getCreator()->getId() === $user->getId();
    }

    /**
     * Retourne les tickets visibles par un utilisateur (avec filtres optionnels).
     */
    public function getTicketsForUser(User $user, array $filters = []): array
    {
        return $this->ticketRepository->findWithFilters(
            status:   $filters['status'] ?? null,
            priority: $filters['priority'] ?? null,
            agent:    $filters['agent'] ?? null,
            creator:  $user->isAgent() ? null : $user, // un user ne voit que ses tickets
        );
    }

    public function updateTicket(Ticket $ticket): void
    {
        // Le PreUpdate lifecycle callback mettra à jour updatedAt automatiquement
        $this->em->flush();
    }

    public function deleteTicket(Ticket $ticket): void
    {
        $this->em->remove($ticket);
        $this->em->flush();
    }
}
