<?php

namespace App\Controller;

use App\Entity\Ticket;
use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use App\Form\TicketFilterType;
use App\Form\TicketStatusType;
use App\Form\TicketType;
use App\Service\TicketService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tickets', name: 'app_ticket_')]
class TicketController extends AbstractController
{
    public function __construct(private readonly TicketService $ticketService) {}

    /**
     * Liste des tickets.
     * - ROLE_USER : voit uniquement ses propres tickets
     * - ROLE_AGENT/ADMIN : voit tous les tickets avec filtre agent
     */
    #[Route('', name: 'user_tickets', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $filterForm = $this->createForm(TicketFilterType::class, null, [
            'show_agent_filter' => $user->isAgent(),
        ]);
        $filterForm->handleRequest($request);

        // Extraction des filtres depuis le formulaire GET
        $filters = [];
        if ($filterForm->isSubmitted() && $filterForm->isValid()) {
            $data = $filterForm->getData();
            $filters['status']   = $data['status'] ?? null;
            $filters['priority'] = $data['priority'] ?? null;
            $filters['agent']    = $data['agent'] ?? null;
        }

        $tickets = $this->ticketService->getTicketsForUser($user, $filters);

        return $this->render('ticket/index.html.twig', [
            'tickets'     => $tickets,
            'filterForm'  => $filterForm,
        ]);
    }

    /**
     * Création d'un nouveau ticket.
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $ticket = new Ticket();
        $form   = $this->createForm(TicketType::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $this->ticketService->createTicket($ticket, $user);

            $this->addFlash('success', 'Ticket créé avec succès.');
            return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
        }

        return $this->render('ticket/new.html.twig', ['form' => $form]);
    }

    /**
     * Détail d'un ticket avec formulaire de statut (agents) et réponses.
     */
    #[Route('/{id}', name: 'show', methods: ['GET', 'POST'])]
    public function show(Ticket $ticket, Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Vérification d'accès : un user normal ne peut voir que ses tickets
        if (!$this->ticketService->canUserViewTicket($ticket, $user)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce ticket.');
        }

        // Formulaire de changement de statut (agents/admins uniquement)
        $statusForm = null;
        if ($user->isAgent()) {
            $statusForm = $this->createForm(TicketStatusType::class, ['status' => $ticket->getStatus()]);
            $statusForm->handleRequest($request);

            if ($statusForm->isSubmitted() && $statusForm->isValid()) {
                $newStatus = $statusForm->get('status')->getData();
                $this->ticketService->changeStatus($ticket, $newStatus, $user);
                $this->addFlash('success', 'Statut mis à jour.');
                return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
            }
        }

        return $this->render('ticket/show.html.twig', [
            'ticket'     => $ticket,
            'statusForm' => $statusForm,
        ]);
    }

    /**
     * Modification d'un ticket (admin uniquement ou créateur si ticket ouvert).
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Ticket $ticket, Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Seul l'admin ou le créateur (ticket encore ouvert) peut éditer
        $canEdit = $user->isAdmin()
            || ($ticket->getCreator()->getId() === $user->getId() && $ticket->isOpen());

        if (!$canEdit) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce ticket.');
        }

        $form = $this->createForm(TicketType::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->ticketService->updateTicket($ticket);
            $this->addFlash('success', 'Ticket modifié.');
            return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
        }

        return $this->render('ticket/edit.html.twig', [
            'ticket' => $ticket,
            'form'   => $form,
        ]);
    }

    /**
     * Un agent s'auto-assigne un ticket.
     */
    #[Route('/{id}/assign', name: 'assign', methods: ['POST'])]
    #[IsGranted('ROLE_AGENT')]
    public function assign(Ticket $ticket, Request $request): Response
    {
        // Protection CSRF sur l'action d'assignation
        if (!$this->isCsrfTokenValid('assign_ticket_' . $ticket->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        /** @var \App\Entity\User $agent */
        $agent = $this->getUser();
        $this->ticketService->assignAgent($ticket, $agent);

        $this->addFlash('success', 'Ticket assigné à vous.');
        return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
    }

    /**
     * Suppression d'un ticket (admin uniquement).
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Ticket $ticket, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_ticket_' . $ticket->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $this->ticketService->deleteTicket($ticket);
        $this->addFlash('success', 'Ticket supprimé.');

        return $this->redirectToRoute('app_ticket_user_tickets');
    }
}
