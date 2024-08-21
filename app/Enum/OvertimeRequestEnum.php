<?php

namespace App\Enum;

enum OvertimeRequestEnum: string
{
    case APPROVED = 'APPROVED';
    case PENDING = 'PENDING';
    case REJECTED = 'REJECTED';
}