<?php

namespace UserNotification\Enums;

enum NotificationMailingRecipientStatus: int
{
    /** Ещё не вызывали notify */
    case PENDING = 1;
    /** Сейчас вызываем notify */
    case SENDING = 2;
    /** notify вызван, каналы в очереди / ждём итог */
    case DISPATCHED = 3;
    /** Все запланированные каналы Sent/Skipped */
    case COMPLETED = 4;
    /** Часть каналов дошла, часть нет */
    case PARTIAL = 5;
    /** Отправка получателю упала целиком */
    case FAILED = 6;

    public function name(): string
    {
        return match ($this) {
            self::PENDING => __('user-notification::mailing_recipient_status.pending'),
            self::SENDING => __('user-notification::mailing_recipient_status.sending'),
            self::DISPATCHED => __('user-notification::mailing_recipient_status.dispatched'),
            self::COMPLETED => __('user-notification::mailing_recipient_status.completed'),
            self::PARTIAL => __('user-notification::mailing_recipient_status.partial'),
            self::FAILED => __('user-notification::mailing_recipient_status.failed'),
        };
    }

    public function toString(): string
    {
        return $this->name();
    }
}
