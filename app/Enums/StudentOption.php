<?php

namespace App\Enums;

/**
 * The degree option a registrant picks during registration.
 */
enum StudentOption: string
{
    case Honors = 'Honors';
    case Masters = 'Masters';

    /**
     * The selectable options, keyed by their stored value.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(self::values(), self::values());
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
