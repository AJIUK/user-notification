<?php

namespace UserNotification\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use UserNotification\Enums\NotificationMailingStage;
use UserNotification\Models\UserNotificationMailing;

/**
 * Смена этапа рассылки (для WebSocket / Echo).
 *
 * ShouldBroadcastNow — сразу, без отдельного job'а в очереди.
 * Ошибки Pusher/Soketi глотаются в NotificationMailingService::broadcastStageChanged.
 *
 * Канал по умолчанию: private-user-notification.mailings
 */
class UserNotificationMailingStageChanged implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(
        public UserNotificationMailing $mailing,
        public ?NotificationMailingStage $previousStage = null,
    ) {
    }

    public function broadcastOn(): array
    {
        $channel = config(
            'user-notification.mailing.broadcast_channel',
            'user-notification.mailings'
        );

        return [
            new PrivateChannel($channel),
        ];
    }

    public function broadcastAs(): string
    {
        return 'mailing.stage.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'mailing_id' => $this->mailing->getKey(),
            'user_type' => $this->mailing->user_type,
            'user_id' => $this->mailing->user_id,
            'notification_class' => $this->mailing->notification_class,
            'notification_type' => $this->mailing->notification_type,
            'stage' => $this->mailing->stage?->value,
            'stage_name' => $this->mailing->stage?->name,
            'previous_stage' => $this->previousStage?->value,
            'previous_stage_name' => $this->previousStage?->name,
            'status' => $this->mailing->status?->value,
            'status_name' => $this->mailing->status?->name,
            'failed_at' => $this->mailing->failed_at?->toIso8601String(),
            'recipients_count' => $this->mailing->recipients()->count(),
            'meta' => $this->mailing->meta,
        ];
    }
}
