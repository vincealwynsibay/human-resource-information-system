<?php

namespace App\Enum;

enum AttendanceStatusEnum: string
{
    case PRESENT = 'PRESENT';
    case HOLIDAY = 'HOLIDAY';
    case LEAVE = 'LEAVE';
    case ABSENT = 'ABSENT';
    case NONE = 'NONE';
    case FUTURE = 'FUTURE';

}