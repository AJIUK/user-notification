<?php

namespace UserNotification\Support;

class UserNotificationTestList
{
    protected array $list = [];

    public function __construct() {
        //
    }

    public function add(UserNotificationTest $test): self
    {
        $this->list[] = $test;
        return $this;
    }

    /**
     * @return UserNotificationTest[]
     */
    public function all(): array
    {
        return $this->list;
    }
}
