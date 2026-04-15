<?php

declare(strict_types=1);

namespace App\Enum;

enum RoleName: string
{
    case STORE_MANAGER = 'store_manager';
    case DISPATCHER = 'dispatcher';
    case OPERATIONS_ANALYST = 'operations_analyst';
    case RECRUITER = 'recruiter';
    case COMPLIANCE_OFFICER = 'compliance_officer';
    case ADMINISTRATOR = 'administrator';
}
