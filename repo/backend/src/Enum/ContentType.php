<?php

declare(strict_types=1);

namespace App\Enum;

enum ContentType: string
{
    case JOB_POST = 'JOB_POST';
    case OPERATIONAL_NOTICE = 'OPERATIONAL_NOTICE';
    case VENDOR_BULLETIN = 'VENDOR_BULLETIN';
}
