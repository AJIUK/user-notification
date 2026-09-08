<?php

namespace UserNotification\Support;

trait NotificationChannelDefaults
{
    public function isHidden(): bool
    {
        return false;
    }

    public function getSort(): int
    {
        return 0;
    }
}
