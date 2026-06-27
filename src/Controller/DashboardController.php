<?php

namespace App\Controller;

use App\Enum\TicketStatus;
use App\Repository\TicketRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{
    public function __construct(private readonly TicketRepository $ticketRepository) {}

    #[Route('/', name: 'app_dashboard')]
    public function index(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }
        if ($this->isGranted('ROLE_AGENT')) {
            return $this->redirectToRoute('app_agent_dashboard');
        }
        return $this->redirectToRoute('app_ticket_user_tickets');
    }

    #[Route('/admin/dashboard', name: 'app_admin_dashboard')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDashboard(): Response
    {
        // Comptages par statut — sert aux cartes KPI et au graphe en barres
        $countByStatus = $this->ticketRepository->countByStatus();

        // On s'assure que tous les statuts ont une valeur (0 si aucun ticket)
        $statusStats = [];
        foreach (TicketStatus::cases() as $status) {
            $statusStats[$status->value] = [
                'label' => $status->getLabel(),
                'color' => $status->getBadgeColor(),
                'count' => $countByStatus[$status->value] ?? 0,
            ];
        }

        $agentStats = $this->ticketRepository->countByAgent();
        // On calcule le max côté PHP pour l'affichage des barres de progression
        $agentMax = array_reduce($agentStats, fn($carry, $row) => max($carry, (int) $row['total']), 0);

        $weeklyActivity = $this->ticketRepository->countByDayLastWeek();
        $weeklyMax = array_reduce($weeklyActivity, fn($carry, $row) => max($carry, (int) $row['total']), 0);

        return $this->render('dashboard/admin.html.twig', [
            'statusStats'    => $statusStats,
            'totalTickets'   => $this->ticketRepository->countTotal(),
            'agentStats'     => $agentStats,
            'agentMax'       => $agentMax,
            'avgResolution'  => $this->ticketRepository->getAverageResolutionTimeInHours(),
            'weeklyActivity' => $weeklyActivity,
            'weeklyMax'      => $weeklyMax,
        ]);
    }

    #[Route('/agent/dashboard', name: 'app_agent_dashboard')]
    #[IsGranted('ROLE_AGENT')]
    public function agentDashboard(): Response
    {
        /** @var \App\Entity\User $agent */
        $agent = $this->getUser();

        $countByStatus  = $this->ticketRepository->countByStatus();
        $assignedTickets = $this->ticketRepository->findAssignedTo($agent);

        return $this->render('dashboard/agent.html.twig', [
            'assignedTickets' => $assignedTickets,
            'openCount'       => $countByStatus[TicketStatus::OPEN->value] ?? 0,
            'inProgressCount' => $countByStatus[TicketStatus::IN_PROGRESS->value] ?? 0,
            'resolvedCount'   => $countByStatus[TicketStatus::RESOLVED->value] ?? 0,
        ]);
    }
}
