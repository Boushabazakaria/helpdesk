<?php

namespace App\Tests\Unit;

use App\Enum\TicketPriority;
use PHPUnit\Framework\TestCase;

class TicketPriorityTest extends TestCase
{
    public function testAllCasesHaveLabel(): void
    {
        foreach (TicketPriority::cases() as $priority) {
            $this->assertNotEmpty($priority->getLabel());
        }
    }

    public function testLabels(): void
    {
        $this->assertSame('Basse',   TicketPriority::LOW->getLabel());
        $this->assertSame('Moyenne', TicketPriority::MEDIUM->getLabel());
        $this->assertSame('Haute',   TicketPriority::HIGH->getLabel());
        $this->assertSame('Urgente', TicketPriority::URGENT->getLabel());
    }

    public function testSortOrderIsConsistent(): void
    {
        // URGENT doit toujours avoir l'ordre le plus élevé
        $this->assertGreaterThan(TicketPriority::HIGH->getSortOrder(),   TicketPriority::URGENT->getSortOrder());
        $this->assertGreaterThan(TicketPriority::MEDIUM->getSortOrder(), TicketPriority::HIGH->getSortOrder());
        $this->assertGreaterThan(TicketPriority::LOW->getSortOrder(),    TicketPriority::MEDIUM->getSortOrder());
    }
}
