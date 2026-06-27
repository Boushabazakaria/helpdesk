<?php

namespace App\Enum;

enum TicketPriority: string
{
    case LOW    = 'low';
    case MEDIUM = 'medium';
    case HIGH   = 'high';
    case URGENT = 'urgent';

    public function getLabel(): string
    {
        return match($this) {
            self::LOW    => 'Basse',
            self::MEDIUM => 'Moyenne',
            self::HIGH   => 'Haute',
            self::URGENT => 'Urgente',
        };
    }

    public function getBadgeColor(): string
    {
        return match($this) {
            self::LOW    => 'gray',
            self::MEDIUM => 'blue',
            self::HIGH   => 'orange',
            self::URGENT => 'red',
        };
    }

    public function getSortOrder(): int
    {
        return match($this) {
            self::URGENT => 4,
            self::HIGH   => 3,
            self::MEDIUM => 2,
            self::LOW    => 1,
        };
    }
}
