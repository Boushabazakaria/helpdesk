<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tickets', name: 'app_ticket_')]
class TicketController extends AbstractController
{
    // Stub — sera complété à l'étape 3
    #[Route('', name: 'user_tickets')]
    public function userTickets(): Response
    {
        return $this->render('ticket/index.html.twig');
    }
}
