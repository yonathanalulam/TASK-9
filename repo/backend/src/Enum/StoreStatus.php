<?php

declare(strict_types=1);

namespace App\Enum;

enum StoreStatus: string
{
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case CLOSED = 'CLOSED';
}
