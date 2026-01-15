<?php

namespace UserNotification\Support;

enum UserNotificationComponent: string
{
    case PANEL = 'mail::panel';
    case SUBCOPY = 'mail::subcopy';
}
