<?php

namespace App\Enum;

enum TicketStatus: string
{
    case OPEN       = 'open';
    case IN_PROGRESS = 'in_progress';
    case RESOLVED   = 'resolved';
    case CLOSED     = 'closed';

    public function getLabel(): string
    {
        return match($this) {
            self::OPEN        => 'Ouvert',
            self::IN_PROGRESS => 'En cours',
            self::RESOLVED    => 'Résolu',
            self::CLOSED      => 'Fermé',
        };
    }

    public function getBadgeColor(): string
    {
        return match($this) {
            self::OPEN        => 'blue',
            self::IN_PROGRESS => 'yellow',
            self::RESOLVED    => 'green',
            self::CLOSED      => 'gray',
        };
    }
}
