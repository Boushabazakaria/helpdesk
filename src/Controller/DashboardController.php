<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(): Response
    {
        // Redirige selon le rôle pour centraliser le point d'entrée
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        if ($this->isGranted('ROLE_AGENT')) {
            return $this->redirectToRoute('app_agent_dashboard');
        }

        return $this->redirectToRoute('app_user_tickets');
    }

    #[Route('/admin/dashboard', name: 'app_admin_dashboard')]
    public function adminDashboard(): Response
    {
        // Stats seront injectées à l'étape 5
        return $this->render('dashboard/admin.html.twig');
    }

    #[Route('/agent/dashboard', name: 'app_agent_dashboard')]
    public function agentDashboard(): Response
    {
        return $this->render('dashboard/agent.html.twig');
    }
}
