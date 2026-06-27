<?php

namespace App\Tests\Unit;

use App\Entity\Ticket;
use App\Entity\TicketResponse;
use App\Entity\User;
use App\Enum\TicketStatus;
use App\Repository\TicketRepository;
use App\Service\TicketService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Tests unitaires sur TicketService.
 *
 * On utilise des Mocks pour EntityManager et Repository :
 * pas besoin de BDD, le test s'exécute en mémoire en millisecondes.
 * On teste uniquement la LOGIQUE MÉTIER, pas la persistance.
 */
class TicketServiceTest extends TestCase
{
    private TicketService $service;
    private EntityManagerInterface&MockObject $em;

    protected function setUp(): void
    {
        // Création des mocks — on simule les dépendances sans les instancier réellement
        $this->em = $this->createMock(EntityManagerInterface::class);
        $repo     = $this->createMock(TicketRepository::class);

        $this->service = new TicketService($this->em, $repo);
    }

    // ── createTicket ────────────────────────────────────────────────────────

    public function testCreateTicketSetsCreatorAndStatus(): void
    {
        $ticket  = new Ticket();
        $creator = $this->makeUser();

        // On s'assure que persist() et flush() sont bien appelés une fois
        $this->em->expects($this->once())->method('persist')->with($ticket);
        $this->em->expects($this->once())->method('flush');

        $this->service->createTicket($ticket, $creator);

        $this->assertSame($creator,            $ticket->getCreator());
        $this->assertSame(TicketStatus::OPEN,  $ticket->getStatus());
    }

    // ── assignAgent ─────────────────────────────────────────────────────────

    public function testAssignAgentSetsAgentAndPassesToInProgress(): void
    {
        $ticket = $this->makeTicket(TicketStatus::OPEN);
        $agent  = $this->makeAgent();

        $this->em->expects($this->once())->method('flush');

        $this->service->assignAgent($ticket, $agent);

        $this->assertSame($agent,                    $ticket->getAssignedAgent());
        $this->assertSame(TicketStatus::IN_PROGRESS, $ticket->getStatus());
    }

    public function testAssignAgentOnInProgressTicketDoesNotChangeStatus(): void
    {
        $ticket = $this->makeTicket(TicketStatus::IN_PROGRESS);
        $agent  = $this->makeAgent();

        $this->em->expects($this->once())->method('flush');

        $this->service->assignAgent($ticket, $agent);

        // Le statut ne doit pas rétrograder à OPEN
        $this->assertSame(TicketStatus::IN_PROGRESS, $ticket->getStatus());
    }

    public function testAssignAgentOnClosedTicketThrows(): void
    {
        $this->expectException(\LogicException::class);

        $ticket = $this->makeTicket(TicketStatus::CLOSED);
        $this->em->expects($this->never())->method('flush');

        $this->service->assignAgent($ticket, $this->makeAgent());
    }

    // ── changeStatus ─────────────────────────────────────────────────────────

    public function testChangeStatusUpdatesTicket(): void
    {
        $ticket = $this->makeTicket(TicketStatus::IN_PROGRESS);
        $agent  = $this->makeAgent();

        $this->em->expects($this->once())->method('flush');

        $this->service->changeStatus($ticket, TicketStatus::RESOLVED, $agent);

        $this->assertSame(TicketStatus::RESOLVED, $ticket->getStatus());
    }

    public function testChangeStatusOnClosedTicketByAgentThrows(): void
    {
        $this->expectException(AccessDeniedException::class);

        $ticket = $this->makeTicket(TicketStatus::CLOSED);
        $agent  = $this->makeAgent();          // pas admin

        $this->em->expects($this->never())->method('flush');

        $this->service->changeStatus($ticket, TicketStatus::OPEN, $agent);
    }

    public function testChangeStatusOnClosedTicketByAdminSucceeds(): void
    {
        $ticket = $this->makeTicket(TicketStatus::CLOSED);
        $admin  = $this->makeAdmin();

        $this->em->expects($this->once())->method('flush');

        $this->service->changeStatus($ticket, TicketStatus::OPEN, $admin);

        $this->assertSame(TicketStatus::OPEN, $ticket->getStatus());
    }

    // ── addResponse ──────────────────────────────────────────────────────────

    public function testAddResponsePersistsAndFlushes(): void
    {
        $ticket   = $this->makeTicket(TicketStatus::IN_PROGRESS);
        $response = new TicketResponse();
        $user     = $this->makeUser();

        $this->em->expects($this->once())->method('persist')->with($response);
        $this->em->expects($this->once())->method('flush');

        $this->service->addResponse($ticket, $response, $user);

        $this->assertSame($ticket, $response->getTicket());
        $this->assertSame($user,   $response->getAuthor());
    }

    public function testAgentResponseOnOpenTicketPassesToInProgress(): void
    {
        $ticket   = $this->makeTicket(TicketStatus::OPEN);
        $response = new TicketResponse();
        $agent    = $this->makeAgent();

        $this->em->method('persist');
        $this->em->method('flush');

        $this->service->addResponse($ticket, $response, $agent);

        $this->assertSame(TicketStatus::IN_PROGRESS, $ticket->getStatus());
    }

    public function testAgentResponseWithCloseOptionResolvesTicket(): void
    {
        $ticket   = $this->makeTicket(TicketStatus::IN_PROGRESS);
        $response = new TicketResponse();
        $agent    = $this->makeAgent();

        $this->em->method('persist');
        $this->em->method('flush');

        $this->service->addResponse($ticket, $response, $agent, closeTicket: true);

        $this->assertSame(TicketStatus::RESOLVED, $ticket->getStatus());
    }

    public function testAddResponseOnClosedTicketThrows(): void
    {
        $this->expectException(\LogicException::class);

        $ticket   = $this->makeTicket(TicketStatus::CLOSED);
        $response = new TicketResponse();

        $this->em->expects($this->never())->method('persist');

        $this->service->addResponse($ticket, $response, $this->makeUser());
    }

    // ── canUserViewTicket ────────────────────────────────────────────────────

    public function testUserCanViewOwnTicket(): void
    {
        $user   = $this->makeUser(id: 1);
        $ticket = $this->makeTicket(TicketStatus::OPEN, creator: $user);

        $this->assertTrue($this->service->canUserViewTicket($ticket, $user));
    }

    public function testUserCannotViewOtherUsersTicket(): void
    {
        $owner  = $this->makeUser(id: 1);
        $other  = $this->makeUser(id: 2);
        $ticket = $this->makeTicket(TicketStatus::OPEN, creator: $owner);

        $this->assertFalse($this->service->canUserViewTicket($ticket, $other));
    }

    public function testAgentCanViewAnyTicket(): void
    {
        $owner  = $this->makeUser(id: 1);
        $agent  = $this->makeAgent(id: 99);
        $ticket = $this->makeTicket(TicketStatus::OPEN, creator: $owner);

        $this->assertTrue($this->service->canUserViewTicket($ticket, $agent));
    }

    // ── Helpers (factories) ──────────────────────────────────────────────────

    private function makeUser(int $id = 1): User
    {
        $user = new User();
        $user->setEmail("user{$id}@test.fr")
             ->setPassword('hashed')
             ->setFirstName('Jean')
             ->setLastName('Test')
             ->setRoles(['ROLE_USER']);

        // On force l'id via Reflection car il est en auto_increment
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);

        return $user;
    }

    private function makeAgent(int $id = 10): User
    {
        $agent = $this->makeUser($id);
        $agent->setRoles(['ROLE_AGENT']);
        return $agent;
    }

    private function makeAdmin(int $id = 20): User
    {
        $admin = $this->makeUser($id);
        $admin->setRoles(['ROLE_ADMIN']);
        return $admin;
    }

    private function makeTicket(TicketStatus $status, ?User $creator = null): Ticket
    {
        $ticket = new Ticket();
        $ticket->setTitle('Test ticket')
               ->setDescription('Description test')
               ->setStatus($status)
               ->setCreator($creator ?? $this->makeUser());
        return $ticket;
    }
}
