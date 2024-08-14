<?php

namespace App\Enum;

enum PayFrequencyEnum: string
{
    case WEEKLY = "WEEKLY";
    case BIWEEKLY = "BIWEEKLY";
    case MONTHLY = "MONTHLY";
    case ANNUALLY = "ANNUALLY";

}