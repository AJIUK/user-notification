<?php

namespace UserNotification\Support;

use Illuminate\Support\Collection;
use UserNotification\Contracts\NotificationChannelEnum;
use UserNotification\Contracts\NotificationTypeEnum;

/**
 * Реестр для хранения зарегистрированных типов и каналов уведомлений
 */
class NotificationRegistry
{
    /**
     * @var Collection<NotificationTypeEnum>|null
     */
    private static ?Collection $types = null;

    /**
     * @var Collection<NotificationChannelEnum>|null
     */
    private static ?Collection $channels = null;

    /**
     * Инициализация реестра
     */
    public static function init(): void
    {
        if (!isset(self::$types)) {
            self::$types = collect();
        }
        if (!isset(self::$channels)) {
            self::$channels = collect();
        }
    }

    /**
     * Зарегистрировать типы уведомлений
     * @param array<NotificationTypeEnum>|Collection<NotificationTypeEnum> $types
     */
    public static function registerTypes(array|Collection $types): void
    {
        self::init();
        $collection = is_array($types) ? collect($types) : $types;
        self::$types = self::$types->merge($collection)->unique();
    }

    /**
     * Зарегистрировать каналы уведомлений
     * @param array<NotificationChannelEnum>|Collection<NotificationChannelEnum> $channels
     */
    public static function registerChannels(array|Collection $channels): void
    {
        self::init();
        $collection = is_array($channels) ? collect($channels) : $channels;
        self::$channels = self::$channels->merge($collection)->unique();
    }

    /**
     * Получить все зарегистрированные типы
     * @return Collection<NotificationTypeEnum>
     */
    public static function getTypes(): Collection
    {
        self::init();
        return self::$types;
    }

    /**
     * Получить все зарегистрированные каналы
     * @return Collection<NotificationChannelEnum>
     */
    public static function getChannels(): Collection
    {
        self::init();
        return self::$channels;
    }

    /**
     * Получить массив значений типов уведомлений
     * @return array<int>
     */
    public static function getTypeValues(): array
    {
        self::init();
        return self::$types->map(fn($type) => $type->getValue())->toArray();
    }

    /**
     * Получить массив значений каналов уведомлений
     * @return array<int>
     */
    public static function getChannelValues(): array
    {
        self::init();
        return self::$channels->map(fn($channel) => $channel->getValue())->toArray();
    }

    /**
     * Очистить реестр (для тестирования)
     */
    public static function clear(): void
    {
        self::$types = collect();
        self::$channels = collect();
    }
}
