<?php

namespace UserNotification\Support;

class UserNotificationMarkdown
{
    public function __construct(
        public string $view,
        public array $data,
    ) {}
}
