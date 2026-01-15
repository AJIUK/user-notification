<?php

namespace UserNotification\Support;

use UserNotification\Contracts\NotificationChannelEnum;

trait ChannelVisibilityControl
{
    private array $_hideFromChannels = [];

    public function hideFrom(NotificationChannelEnum $channel, bool $value = true): self
    {
        $key = array_search($channel, $this->_hideFromChannels);

        if ($key !== false) {
            if (!$value) {
                unset($this->_hideFromChannels[$key]);
            }
        } else {
            if ($value) {
                $this->_hideFromChannels[] = $channel;
            }
        }

        return $this;
    }

    public function isHideFrom(NotificationChannelEnum $channel): bool
    {
        return in_array($channel, $this->_hideFromChannels);
    }
}
