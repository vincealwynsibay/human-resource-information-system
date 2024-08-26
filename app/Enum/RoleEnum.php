<?php
namespace App\Enum;

enum RoleEnum: int
{
    case ADMIN = 1;
    case EMPLOYEE = 2;

    public static function fromName(string $name)
    {
        return constant("self::$name");
    }
}