<?php

namespace App\Support;

class ParkingName
{
    public static function clean(string $name): string
    {
        return trim(preg_replace('/[\p{Z}\s]+/u', ' ', $name));
    }

    public static function key(string $name): string
    {
        return hash('sha256', mb_strtolower(self::clean($name), 'UTF-8'));
    }
}
