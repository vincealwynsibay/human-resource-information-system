<?php
namespace App\Enum;

enum DayLengthEnum: int
{
    case FULL_DAY = 0;
    case HALF_DAY = 1;
    case NO_WORK = 2;

    public function isFullDay(): bool
    {
        return $this->value === self::FULL_DAY->value;
    }

    public function isHalfDay(): bool
    {
        return $this->value === self::HALF_DAY->value;
    }

    public function isNoWork(): bool
    {
        return $this->value === self::NO_WORK->value;
    }
}