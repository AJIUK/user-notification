<?php

namespace UserNotification\Support;

use UserNotification\Contracts\NotificationChannelEnum;

trait NotificationTypeDefaults
{
    public function getSort(): int
    {
        return 0;
    }

    /**
     * @return array<int, NotificationChannelEnum>
     */
    public function getHiddenChannels(): array
    {
        return [];
    }

    /**
     * @return array<int, NotificationChannelEnum>
     */
    public function getReadonlyChannels(): array
    {
        return [];
    }

    public function isChannelHidden(NotificationChannelEnum $channel): bool
    {
        return $channel->isHidden() || in_array($channel, $this->getHiddenChannels(), true);
    }

    public function isChannelReadonly(NotificationChannelEnum $channel): bool
    {
        return $this->isChannelHidden($channel)
            || in_array($channel, $this->getReadonlyChannels(), true);
    }
}
