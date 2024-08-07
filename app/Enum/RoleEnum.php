<?php
namespace App\Enum;

enum RoleEnum: int
{
    case ADMIN = 1;
    case SUPERVISOR = 2;
    case EMPLOYEE = 3;

    public static function fromName(string $name)
    {
        return constant("self::$name");
    }
}