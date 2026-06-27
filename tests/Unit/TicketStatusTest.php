<?php

namespace App\Tests\Unit;

use App\Enum\TicketStatus;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires sur l'Enum TicketStatus.
 * Pas de BDD, pas de Symfony — PHPUnit pur.
 */
class TicketStatusTest extends TestCase
{
    public function testAllCasesHaveLabel(): void
    {
        foreach (TicketStatus::cases() as $status) {
            $this->assertNotEmpty($status->getLabel(), "Le statut {$status->value} n'a pas de label.");
        }
    }

    public function testAllCasesHaveBadgeColor(): void
    {
        foreach (TicketStatus::cases() as $status) {
            $this->assertNotEmpty($status->getBadgeColor(), "Le statut {$status->value} n'a pas de couleur.");
        }
    }

    public function testLabels(): void
    {
        $this->assertSame('Ouvert',   TicketStatus::OPEN->getLabel());
        $this->assertSame('En cours', TicketStatus::IN_PROGRESS->getLabel());
        $this->assertSame('Résolu',   TicketStatus::RESOLVED->getLabel());
        $this->assertSame('Fermé',    TicketStatus::CLOSED->getLabel());
    }

    public function testFromValue(): void
    {
        $this->assertSame(TicketStatus::OPEN,        TicketStatus::from('open'));
        $this->assertSame(TicketStatus::IN_PROGRESS, TicketStatus::from('in_progress'));
        $this->assertSame(TicketStatus::RESOLVED,    TicketStatus::from('resolved'));
        $this->assertSame(TicketStatus::CLOSED,      TicketStatus::from('closed'));
    }

    public function testFromInvalidValueThrows(): void
    {
        $this->expectException(\ValueError::class);
        TicketStatus::from('invalid_status');
    }
}
