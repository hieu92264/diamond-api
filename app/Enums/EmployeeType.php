<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum EmployeeType: string
{
    use HasValues;

    case STAFF = 'staff';
    case SINGER = 'singer';
    case ACTOR = 'actor';
    case DANCER = 'dancer';
    case MC = 'mc';
    case TECHNICIAN = 'technician';
    case MAKEUP = 'makeup';
    case SECURITY = 'security';
    case COLLABORATOR = 'collaborator';
}
