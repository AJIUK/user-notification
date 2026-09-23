<?php

namespace UserNotification\Enums;

enum NotificationMailingStage: int
{
    case DRAFT = 1;
    case COLLECTING = 2;
    case READY = 3;
    case SENDING = 4;
    case COMPLETED = 5;
    case FAILED = 6;
    case CANCELLED = 7;

    public function name(): string
    {
        return match ($this) {
            self::DRAFT => __('user-notification::mailing_stage.draft'),
            self::COLLECTING => __('user-notification::mailing_stage.collecting'),
            self::READY => __('user-notification::mailing_stage.ready'),
            self::SENDING => __('user-notification::mailing_stage.sending'),
            self::COMPLETED => __('user-notification::mailing_stage.completed'),
            self::FAILED => __('user-notification::mailing_stage.failed'),
            self::CANCELLED => __('user-notification::mailing_stage.cancelled'),
        };
    }

    public function toString(): string
    {
        return $this->name();
    }
}
