<?php

namespace App\Tests\Functional;

use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests fonctionnels sur le CRUD tickets.
 *
 * Règle Symfony : toujours appeler createClient() EN PREMIER dans chaque test
 * avant d'accéder au container (static::getContainer()).
 * setUp() ne doit PAS appeler bootKernel() — createClient() le fait déjà.
 */
class TicketControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        // createClient() boot le kernel → on peut ensuite accéder au container
        $this->client = static::createClient();
        $this->em     = static::getContainer()->get('doctrine')->getManager();

        // Nettoyer la BDD de test avant chaque test (état propre garanti)
        $this->cleanDatabase();
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        parent::tearDown();
    }

    private function cleanDatabase(): void
    {
        $this->em->createQuery('DELETE FROM App\Entity\TicketResponse')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Ticket')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    // ── Tests liste tickets ──────────────────────────────────────────────────

    public function testUserSeesOnlyOwnTickets(): void
    {
        [$user, $otherUser] = $this->createUsers();
        $this->createTicket('Mon ticket',   $user);
        $this->createTicket('Autre ticket', $otherUser);

        $this->client->loginUser($user);
        $this->client->request('GET', '/tickets');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Mon ticket');
        $this->assertSelectorTextNotContains('body', 'Autre ticket');
    }

    public function testAgentSeesAllTickets(): void
    {
        [$user, $agent] = $this->createUsers();
        $this->createTicket('Ticket user',  $user);
        $this->createTicket('Ticket agent', $agent);

        $this->client->loginUser($agent);
        $this->client->request('GET', '/tickets');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Ticket user');
        $this->assertSelectorTextContains('body', 'Ticket agent');
    }

    // ── Tests création ticket ────────────────────────────────────────────────

    public function testUserCanCreateTicket(): void
    {
        [$user] = $this->createUsers();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/tickets/new');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Soumettre le ticket')->form([
            'ticket[title]'       => 'Mon problème urgent',
            'ticket[description]' => 'Description détaillée du problème rencontré.',
            'ticket[priority]'    => 'high',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertSelectorTextContains('h1', 'Mon problème urgent');
    }

    public function testCreateTicketWithBlankTitleShowsError(): void
    {
        [$user] = $this->createUsers();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/tickets/new');

        $form = $crawler->selectButton('Soumettre le ticket')->form([
            'ticket[title]'       => '',
            'ticket[description]' => 'Description valide suffisamment longue.',
            'ticket[priority]'    => 'medium',
        ]);
        $this->client->submit($form);

        // Symfony 7 retourne 422 Unprocessable Content sur erreur de validation (pas de redirection)
        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('body', 'Le titre est obligatoire');
    }

    // ── Tests accès ticket ───────────────────────────────────────────────────

    public function testUserCannotViewAnotherUsersTicket(): void
    {
        [$user, $otherUser] = $this->createUsers();
        $ticket = $this->createTicket('Ticket privé', $otherUser);

        $this->client->loginUser($user);
        $this->client->request('GET', '/tickets/' . $ticket->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAgentCanViewAnyTicket(): void
    {
        [$user, $agent] = $this->createUsers();
        $ticket = $this->createTicket('Ticket utilisateur', $user);

        $this->client->loginUser($agent);
        $this->client->request('GET', '/tickets/' . $ticket->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Ticket utilisateur');
    }

    // ── Tests suppression (admin uniquement) ─────────────────────────────────

    public function testUserCannotDeleteTicket(): void
    {
        [$user] = $this->createUsers();
        $ticket = $this->createTicket('À supprimer', $user);

        $this->client->loginUser($user);
        $this->client->request('POST', '/tickets/' . $ticket->getId() . '/delete', [
            '_token' => 'fake_token',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminCanDeleteTicket(): void
    {
        [$user, $agent, $admin] = $this->createUsers();
        $ticket = $this->createTicket('À supprimer par admin', $user);

        $this->client->loginUser($admin);

        // Récupération du vrai token CSRF depuis la page show
        $crawler    = $this->client->request('GET', '/tickets/' . $ticket->getId());
        $csrfToken  = $crawler->filter('input[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/tickets/' . $ticket->getId() . '/delete', [
            '_token' => $csrfToken,
        ]);

        $this->assertResponseRedirects('/tickets');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @return User[] [user, agent, admin] */
    private function createUsers(): array
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $hash   = fn(string $role) => (new User())
            ->setEmail(strtolower($role) . '@test.fr')
            ->setFirstName('Test')->setLastName(ucfirst(strtolower($role)))
            ->setRoles([$role])
            ->setPassword($hasher->hashPassword(new User(), 'pass1234'));

        $user  = $hash('ROLE_USER');
        $agent = $hash('ROLE_AGENT');
        $admin = $hash('ROLE_ADMIN');

        $this->em->persist($user);
        $this->em->persist($agent);
        $this->em->persist($admin);
        $this->em->flush();

        return [$user, $agent, $admin];
    }

    private function createTicket(string $title, User $creator): Ticket
    {
        $ticket = (new Ticket())
            ->setTitle($title)
            ->setDescription('Description de test suffisamment longue.')
            ->setPriority(TicketPriority::MEDIUM)
            ->setStatus(TicketStatus::OPEN)
            ->setCreator($creator);

        $this->em->persist($ticket);
        $this->em->flush();

        return $ticket;
    }
}
