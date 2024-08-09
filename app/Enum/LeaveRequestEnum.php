<?php

namespace App\Enum;

enum LeaveRequestEnum: string
{
    case APPROVED = 'APPROVED';
    case PENDING = 'PENDING';
    case REJECTED = 'REJECTED';
}