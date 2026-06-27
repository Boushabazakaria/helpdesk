<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels sur les routes de sécurité.
 *
 * WebTestCase simule un navigateur HTTP sans démarrer de vrai serveur.
 * Ces tests vérifient que les routes répondent correctement (codes HTTP, redirections, contenu).
 */
class SecurityControllerTest extends WebTestCase
{
    public function testLoginPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="email"]');
        $this->assertSelectorExists('input[name="password"]');
        $this->assertSelectorTextContains('button[type="submit"]', 'Se connecter');
    }

    public function testRegisterPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="registration_form[email]"]');
    }

    public function testUnauthenticatedUserIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/tickets');

        // Doit rediriger vers /login (accès protégé)
        $this->assertResponseRedirects('/login');
    }

    public function testLoginWithBadCredentialsShowsError(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('Se connecter')->form([
            'email'    => 'nobody@test.fr',
            'password' => 'wrongpassword',
        ]);
        $client->submit($form);

        // Après soumission invalide, on reste sur /login
        $this->assertRouteSame('app_login');
    }

    public function testAdminDashboardRequiresAdminRole(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/dashboard');

        $this->assertResponseRedirects('/login');
    }
}
