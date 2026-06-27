<?php

namespace App\Controller;

use App\Entity\Ticket;
use App\Entity\TicketResponse;
use App\Form\TicketFilterType;
use App\Form\TicketResponseType;
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
     * Détail d'un ticket avec formulaire de réponse et de changement de statut.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Ticket $ticket): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$this->ticketService->canUserViewTicket($ticket, $user)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce ticket.');
        }

        // Formulaire de réponse — affiché si le ticket n'est pas fermé
        $responseForm = null;
        if (!$ticket->isClosed()) {
            $responseForm = $this->createForm(TicketResponseType::class, new TicketResponse(), [
                'action' => $this->generateUrl('app_ticket_reply', ['id' => $ticket->getId()]),
                'method' => 'POST',
            ]);
        }

        // Formulaire de changement de statut — agents/admins uniquement
        $statusForm = null;
        if ($user->isAgent() && !$ticket->isClosed()) {
            $statusForm = $this->createForm(TicketStatusType::class, ['status' => $ticket->getStatus()], [
                'action' => $this->generateUrl('app_ticket_status', ['id' => $ticket->getId()]),
                'method' => 'POST',
            ]);
        }

        return $this->render('ticket/show.html.twig', [
            'ticket'       => $ticket,
            'responseForm' => $responseForm,
            'statusForm'   => $statusForm,
        ]);
    }

    /**
     * Soumettre une réponse à un ticket.
     * Séparé de show() pour respecter POST-Redirect-GET et éviter la double soumission.
     */
    #[Route('/{id}/reply', name: 'reply', methods: ['POST'])]
    public function reply(Ticket $ticket, Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$this->ticketService->canUserViewTicket($ticket, $user)) {
            throw $this->createAccessDeniedException();
        }

        $response = new TicketResponse();
        $form     = $this->createForm(TicketResponseType::class, $response);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // L'agent peut cocher "marquer comme résolu" en même temps qu'il répond
            $closeTicket = $request->request->getBoolean('close_ticket');
            $this->ticketService->addResponse($ticket, $response, $user, $closeTicket);
            $this->addFlash('success', 'Réponse ajoutée.');
        }

        return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
    }

    /**
     * Changer le statut d'un ticket (agents/admins).
     */
    #[Route('/{id}/status', name: 'status', methods: ['POST'])]
    #[IsGranted('ROLE_AGENT')]
    public function changeStatus(Ticket $ticket, Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $form = $this->createForm(TicketStatusType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newStatus = $form->get('status')->getData();
            $this->ticketService->changeStatus($ticket, $newStatus, $user);
            $this->addFlash('success', 'Statut mis à jour.');
        }

        return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
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
