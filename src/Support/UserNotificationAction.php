<?php

namespace UserNotification\Support;

use UserNotification\Support\ChannelVisibilityControl;

class UserNotificationAction
{
    use ChannelVisibilityControl;

    public function __construct(
        public UserNotificationLine $text,
        public string $url,
    ) {}
}
