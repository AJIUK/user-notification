<?php

namespace UserNotification\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;
use UserNotification\Contracts\NotifiableUser;
use UserNotification\Contracts\NotificationChannelEnum;
use UserNotification\Enums\NotificationLogChannelStatus;
use UserNotification\Models\UserNotificationLog;
use UserNotification\Models\UserNotificationLogChannel;
use UserNotification\Models\UserNotificationMailingRecipient;
use UserNotification\Support\NotificationRegistry;
use UserNotification\UserNotification;

/**
 * Логирование доставки уведомлений: parent log + строки по каналам.
 */
class NotificationDeliveryLogger
{
    public function enabled(): bool
    {
        return (bool) config('user-notification.logging.enabled', true);
    }

    /**
     * Создать (или вернуть уже привязанный) лог и строки каналов со статусом Queued.
     * Вызывается из via() до постановки job'ов в очередь.
     *
     * @param  array<int, class-string>  $channelClassNames
     */
    public function ensureLog(
        NotifiableUser $notifiable,
        UserNotification $notification,
        array $channelClassNames = [],
    ): ?UserNotificationLog {
        if (!$this->enabled()) {
            return null;
        }

        if ($notification->getLogId()) {
            $log = UserNotificationLog::query()->find($notification->getLogId());
            if ($log) {
                $this->ensureLogChannels($log, $channelClassNames);
                return $log;
            }
        }

        if (!$notifiable instanceof Model) {
            Log::warning('UserNotification: notifiable is not an Eloquent model, delivery log skipped', [
                'notifiable' => $notifiable::class,
                'notification' => $notification::class,
            ]);

            return null;
        }

        $log = UserNotificationLog::query()->create([
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'notification_class' => $notification::class,
            'mailing_id' => $notification->getMailingId(),
            'meta' => array_filter([
                'notification_id' => $notification->id ?? null,
            ]),
        ]);

        $notification->setLogId($log->getKey());
        $this->ensureLogChannels($log, $channelClassNames);

        return $log;
    }

    /**
     * @param  array<int, class-string>  $channelClassNames
     */
    public function ensureLogChannels(UserNotificationLog $log, array $channelClassNames): void
    {
        foreach ($channelClassNames as $channelClassName) {
            $channel = $this->resolveChannelEnum($channelClassName);
            if (!$channel) {
                continue;
            }

            UserNotificationLogChannel::query()->firstOrCreate(
                [
                    'log_id' => $log->getKey(),
                    'channel' => $channel->getValue(),
                ],
                [
                    'status' => NotificationLogChannelStatus::QUEUED,
                    'attempts' => 0,
                ]
            );
        }
    }

    public function markSending(UserNotification $notification, NotificationChannelEnum $channel): ?UserNotificationLogChannel
    {
        $row = $this->findOrCreateChannelRow($notification, $channel);
        if (!$row) {
            return null;
        }

        $row->status = NotificationLogChannelStatus::SENDING;
        $row->attempts = (int) $row->attempts + 1;
        $row->failed_at = null;
        $row->save();

        return $row;
    }

    public function markSent(UserNotification $notification, NotificationChannelEnum $channel, ?array $meta = null): ?UserNotificationLogChannel
    {
        $row = $this->findOrCreateChannelRow($notification, $channel);
        if (!$row) {
            return null;
        }

        $row->status = NotificationLogChannelStatus::SENT;
        $row->sent_at = now();
        $row->failed_at = null;
        if ($meta !== null) {
            $row->meta = array_merge($row->meta ?? [], $meta);
        }
        $row->save();

        $this->syncMailingRecipient($notification);

        return $row;
    }

    public function markFailed(
        UserNotification $notification,
        NotificationChannelEnum $channel,
        Throwable $exception,
        ?array $meta = null,
    ): ?UserNotificationLogChannel {
        $row = $this->findOrCreateChannelRow($notification, $channel);
        if (!$row) {
            return null;
        }

        $row->status = NotificationLogChannelStatus::FAILED;
        $row->failed_at = now();
        $row->meta = array_merge($row->meta ?? [], $meta ?? [], [
            'error' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);
        $row->save();

        $this->syncMailingRecipient($notification);

        return $row;
    }

    public function markSkipped(
        UserNotification $notification,
        NotificationChannelEnum $channel,
        ?string $reason = null,
    ): ?UserNotificationLogChannel {
        $row = $this->findOrCreateChannelRow($notification, $channel);
        if (!$row) {
            return null;
        }

        $row->status = NotificationLogChannelStatus::SKIPPED;
        $row->meta = array_merge($row->meta ?? [], array_filter([
            'reason' => $reason,
        ]));
        $row->save();

        $this->syncMailingRecipient($notification);

        return $row;
    }

    protected function findOrCreateChannelRow(
        UserNotification $notification,
        NotificationChannelEnum $channel,
    ): ?UserNotificationLogChannel {
        if (!$this->enabled()) {
            return null;
        }

        $logId = $notification->getLogId();
        if (!$logId) {
            return null;
        }

        return UserNotificationLogChannel::query()->firstOrCreate(
            [
                'log_id' => $logId,
                'channel' => $channel->getValue(),
            ],
            [
                'status' => NotificationLogChannelStatus::QUEUED,
                'attempts' => 0,
            ]
        );
    }

    public function resolveChannelEnum(string $channelClassName): ?NotificationChannelEnum
    {
        return NotificationRegistry::getChannels()->first(
            fn (NotificationChannelEnum $channel) => $channel->getChannelClassName() === $channelClassName
        );
    }

    /**
     * После смены статуса канала — пересчитать recipient рассылки (если есть).
     */
    protected function syncMailingRecipient(UserNotification $notification): void
    {
        if (!$notification->getMailingId() || !$notification->getLogId()) {
            return;
        }

        $log = UserNotificationLog::query()->find($notification->getLogId());
        if (!$log?->mailing_id) {
            return;
        }

        $recipient = UserNotificationMailingRecipient::query()
            ->where('mailing_id', $log->mailing_id)
            ->where('notifiable_type', $log->notifiable_type)
            ->where('notifiable_id', $log->notifiable_id)
            ->first();

        if (!$recipient) {
            return;
        }

        app(NotificationMailingService::class)->refreshRecipientStatus($recipient);
    }
}
