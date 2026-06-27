<?php

namespace App\Repository;

use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
     * Temps moyen de résolution en heures (uniquement tickets résolus)
     */
    public function getAverageResolutionTimeInHours(): float
    {
        $result = $this->createQueryBuilder('t')
            ->select('AVG(TIMESTAMPDIFF(SECOND, t.createdAt, t.resolvedAt)) as avgSeconds')
            ->where('t.resolvedAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? round($result / 3600, 1) : 0.0;
    }
}
