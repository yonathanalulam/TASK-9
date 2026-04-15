<?php

declare(strict_types=1);

namespace App\Enum;

enum ContentStatus: string
{
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case UPDATED = 'UPDATED';
    case ROLLED_BACK = 'ROLLED_BACK';
    case ARCHIVED = 'ARCHIVED';
}
