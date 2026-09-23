<?php

namespace UserNotification\Channels;

use Throwable;
use UserNotification\Contracts\NotifiableUser;
use UserNotification\Contracts\NotificationChannelEnum;
use UserNotification\Services\NotificationDeliveryLogger;
use UserNotification\UserNotification;

/**
 * Базовый канал уведомлений.
 * send() — обёртка с delivery-логом; deliver() — реальная отправка в наследнике.
 */
abstract class BaseChannel
{
    /**
     * Отправить уведомление через канал (вызывается Laravel ChannelManager).
     *
     * @param  NotifiableUser|mixed  $notifiable
     * @param  UserNotification|mixed  $notification
     */
    final public function send($notifiable, $notification): void
    {
        if (!$notification instanceof UserNotification) {
            $this->deliver($notifiable, $notification);

            return;
        }

        $logger = $this->deliveryLogger();
        $channel = $logger->resolveChannelEnum(static::class);

        if (!$channel || !$logger->enabled() || !$notification->getLogId()) {
            $this->deliverWithAvailabilityCheck($notifiable, $notification);

            return;
        }

        if ($notifiable instanceof NotifiableUser && !$this->isAvailable($notifiable)) {
            $logger->markSkipped($notification, $channel, 'channel_unavailable');

            return;
        }

        $logger->markSending($notification, $channel);

        try {
            $this->deliver($notifiable, $notification);
            $logger->markSent($notification, $channel);
        } catch (Throwable $exception) {
            $logger->markFailed($notification, $channel, $exception);
            throw $exception;
        }
    }

    /**
     * Реальная отправка. Реализуется в конкретном канале приложения.
     *
     * @param  NotifiableUser|mixed  $notifiable
     * @param  UserNotification|mixed  $notification
     */
    abstract protected function deliver($notifiable, $notification): void;

    /**
     * Проверить доступность канала для пользователя
     *
     * @param  NotifiableUser  $notifiable
     */
    abstract public function isAvailable(NotifiableUser $notifiable): bool;

    /**
     * Получить middleware для канала
     *
     * @param  NotifiableUser  $notifiable
     * @return array
     */
    public function middleware(NotifiableUser $notifiable)
    {
        return [];
    }

    /**
     * Получить очередь для канала
     * По умолчанию используется очередь из конфига user-notification.default_queue
     */
    public function queue(): ?string
    {
        return config('user-notification.default_queue', 'default');
    }

    /**
     * Получить метаданные для канала
     */
    public function getMeta(mixed ...$args): ?array
    {
        return null;
    }

    protected function deliveryLogger(): NotificationDeliveryLogger
    {
        return app(NotificationDeliveryLogger::class);
    }

    protected function resolveChannelEnum(): ?NotificationChannelEnum
    {
        return $this->deliveryLogger()->resolveChannelEnum(static::class);
    }

    protected function deliverWithAvailabilityCheck($notifiable, $notification): void
    {
        if ($notifiable instanceof NotifiableUser && !$this->isAvailable($notifiable)) {
            return;
        }

        $this->deliver($notifiable, $notification);
    }
}
