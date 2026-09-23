<?php

namespace UserNotification\Enums;

enum NotificationMailingStatus: int
{
    case DRAFT = 1;
    case PENDING = 2;
    case PROCESSING = 3;
    case COMPLETED = 4;
    case FAILED = 5;
    case CANCELLED = 6;

    public function name(): string
    {
        return match ($this) {
            self::DRAFT => __('user-notification::mailing_status.draft'),
            self::PENDING => __('user-notification::mailing_status.pending'),
            self::PROCESSING => __('user-notification::mailing_status.processing'),
            self::COMPLETED => __('user-notification::mailing_status.completed'),
            self::FAILED => __('user-notification::mailing_status.failed'),
            self::CANCELLED => __('user-notification::mailing_status.cancelled'),
        };
    }

    public function toString(): string
    {
        return $this->name();
    }
}
