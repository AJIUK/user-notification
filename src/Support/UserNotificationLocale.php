<?php

namespace UserNotification\Support;

enum UserNotificationLocale: string
{
    case RU = 'ru';
    case EN = 'en';

    public static function default(): self
    {
        return self::RU;
    }

    /**
     * Получить массив всех значений enum
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
