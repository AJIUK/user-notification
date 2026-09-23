<?php

namespace UserNotification\Enums;

enum NotificationLogChannelStatus: int
{
    case QUEUED = 1;
    case SENDING = 2;
    case SENT = 3;
    case FAILED = 4;
    case SKIPPED = 5;

    public function name(): string
    {
        return match ($this) {
            self::QUEUED => __('user-notification::log_channel_status.queued'),
            self::SENDING => __('user-notification::log_channel_status.sending'),
            self::SENT => __('user-notification::log_channel_status.sent'),
            self::FAILED => __('user-notification::log_channel_status.failed'),
            self::SKIPPED => __('user-notification::log_channel_status.skipped'),
        };
    }

    public function toString(): string
    {
        return $this->name();
    }
}
