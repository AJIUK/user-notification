<?php

namespace UserNotification\Channels;

use UserNotification\Contracts\NotifiableUser;
use UserNotification\UserNotification;

/**
 * Интерфейс для каналов уведомлений
 * Соответствует стандартному интерфейсу Laravel NotificationChannel
 */
interface ChannelInterface
{
    /**
     * Отправить уведомление через канал
     *
     * @param NotifiableUser $notifiable
     * @param UserNotification $notification
     * @return void
     */
    public function send(NotifiableUser $notifiable, UserNotification $notification): void;

    /**
     * Получить middleware для канала
     *
     * @param NotifiableUser $notifiable
     * @return array
     */
    public function middleware(NotifiableUser $notifiable): array;
}
