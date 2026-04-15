<?php

declare(strict_types=1);

namespace App\Enum;

enum ScopeType: string
{
    case GLOBAL = 'GLOBAL';
    case REGION = 'REGION';
    case STORE = 'STORE';
}
