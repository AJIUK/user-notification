<?php

namespace UserNotification\Contracts;

use UserNotification\Models\UserNotificationMailing;
use UserNotification\UserNotification;

/**
 * Уведомление можно собрать из рассылки (subject + meta).
 */
interface CreatesFromMailing
{
    public static function fromMailing(UserNotificationMailing $mailing): UserNotification;
}
