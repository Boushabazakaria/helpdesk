<?php

namespace App\Repository;

use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ticket>
 */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    /**
     * Recherche filtrée avec JOIN sur creator et assignedAgent pour éviter les N+1 queries
     */
    public function findWithFilters(
        ?TicketStatus $status = null,
        ?TicketPriority $priority = null,
        ?User $agent = null,
        ?User $creator = null,
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.creator', 'c')
            ->addSelect('c')
            ->leftJoin('t.assignedAgent', 'a')
            ->addSelect('a')
            ->orderBy('t.createdAt', 'DESC');

        if ($status !== null) {
            $qb->andWhere('t.status = :status')->setParameter('status', $status);
        }
        if ($priority !== null) {
            $qb->andWhere('t.priority = :priority')->setParameter('priority', $priority);
        }
        if ($agent !== null) {
            $qb->andWhere('t.assignedAgent = :agent')->setParameter('agent', $agent);
        }
        if ($creator !== null) {
            $qb->andWhere('t.creator = :creator')->setParameter('creator', $creator);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Statistiques par statut pour le dashboard admin
     * Retourne un tableau associatif ['status_value' => count]
     */
    public function countByStatus(): array
    {
        $results = $this->createQueryBuilder('t')
            ->select('t.status, COUNT(t.id) as total')
            ->groupBy('t.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['status']->value] = (int) $row['total'];
        }
        return $counts;
    }

    /**
     * Statistiques par agent pour le dashboard admin
     */
    public function countByAgent(): array
    {
        return $this->createQueryBuilder('t')
            ->select('a.firstName, a.lastName, COUNT(t.id) as total')
            ->leftJoin('t.assignedAgent', 'a')
            ->where('t.assignedAgent IS NOT NULL')
            ->groupBy('a.id')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Temps moyen de résolution en heures (SQL natif car TIMESTAMPDIFF n'est pas DQL).
     */
    public function getAverageResolutionTimeInHours(): float
    {
        // On utilise la connexion DBAL pour exécuter du SQL natif MySQL
        $conn = $this->getEntityManager()->getConnection();
        $result = $conn->fetchOne(
            'SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, resolved_at)) FROM ticket WHERE resolved_at IS NOT NULL'
        );

        return $result ? round((float) $result / 3600, 1) : 0.0;
    }

    /**
     * Nombre total de tickets.
     */
    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Tickets des 7 derniers jours groupés par date — pour le mini-graphe du dashboard.
     * Retourne [['date' => 'YYYY-MM-DD', 'total' => N], ...]
     */
    public function countByDayLastWeek(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        return $conn->fetchAllAssociative(
            "SELECT DATE(created_at) as date, COUNT(*) as total
             FROM ticket
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date ASC"
        );
    }

    /**
     * Tickets assignés à un agent spécifique, triés par priorité décroissante.
     */
    public function findAssignedTo(User $agent): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.assignedAgent = :agent')
            ->andWhere('t.status != :closed')
            ->setParameter('agent', $agent)
            ->setParameter('closed', TicketStatus::CLOSED)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
