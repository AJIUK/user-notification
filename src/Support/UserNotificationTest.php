<?php

namespace UserNotification\Support;

use UserNotification\Contracts\NotifiableUser;
use UserNotification\UserNotification;

class UserNotificationTest
{
    public function __construct(
        public UserNotification $notification,
        public ?NotifiableUser $user = null,
    ) {
        //
    }
}
