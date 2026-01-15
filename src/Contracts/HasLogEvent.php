<?php

namespace UserNotification\Contracts;

use UserNotification\UserNotification;

/**
 * Интерфейс для уведомлений, которые должны логировать события
 * Пользователь библиотеки может реализовать этот интерфейс в своих уведомлениях
 */
interface HasLogEvent
{
    public function toLogEvent(UserNotification $user): LogEventAction;
}
