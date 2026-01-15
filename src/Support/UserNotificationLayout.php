<?php

namespace UserNotification\Support;

use Illuminate\Support\Collection;

class UserNotificationLayout
{
    protected Collection $items;

    public function __construct()
    {
        $this->items = new Collection();
    }

    public function addLines(UserNotificationLines $lines, bool $prepend = false): self
    {
        if ($prepend) {
            $this->items->prepend($lines);
        } else {
            $this->items->push($lines);
        }
        return $this;
    }

    public function addAction(UserNotificationAction $action, bool $prepend = false): self
    {
        if ($prepend) {
            $this->items->prepend($action);
        } else {
            $this->items->push($action);
        }
        return $this;
    }

    /**
     * Summary of all
     * @return UserNotificationLines[]|UserNotificationAction[]
     */
    public function all(): array
    {
        return $this->items->all();
    }

    /**
     * Summary of items
     * @return Collection<UserNotificationLines|UserNotificationAction>
     */
    public function items(): Collection
    {
        return $this->items;
    }
}
