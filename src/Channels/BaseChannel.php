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
     * Если нужно отключить очередь, то нужно вернуть null
     *
     * @return ?string
     */
    public function queue(): ?string
    {
        return config('user-notification.default_queue', 'default');
    }
}
