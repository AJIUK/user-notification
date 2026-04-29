<?php

namespace UserNotification\Channels;

use UserNotification\Contracts\NotifiableUser;
use UserNotification\UserNotification;

/**
 * Интерфейс для каналов уведомлений
 * Соответствует стандартному интерфейсу Laravel NotificationChannel
 */
abstract class BaseChannel
{
    /**
     * Отправить уведомление через канал
     *
     * @param NotifiableUser $notifiable
     * @param UserNotification $notification
     * @return void
     */
    abstract public function send(NotifiableUser $notifiable, UserNotification $notification): void;

    /**
     * Проверить доступность канала для пользователя
     *
     * @param NotifiableUser $notifiable
     * @return bool
     */
    abstract public function isAvailable(NotifiableUser $notifiable): bool;

    /**
     * Получить middleware для канала
     *
     * @param NotifiableUser $notifiable
     * @return array
     */
    public function middleware(NotifiableUser $notifiable)
    {
        return [];
    }

    /**
     * Получить очередь для канала
     * По умолчанию используется очередь из конфига user-notification.default_queue
     *
     * @return ?string
     */
    public function queue(): ?string
    {
        return config('user-notification.default_queue', 'default');
    }

    /**
     * Получить метаданные для канала
     *
     * @return ?array
     */
    public function getMeta(mixed ...$args): ?array
    {
        return null;
    }
}
