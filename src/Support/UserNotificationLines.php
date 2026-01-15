<?php

namespace UserNotification\Support;

use Illuminate\Support\Collection;
use UserNotification\Support\ChannelVisibilityControl;

class UserNotificationLines
{
    use ChannelVisibilityControl;

    protected Collection $lines;

    public function __construct(
        public ?UserNotificationComponent $component = null,
        public bool $glue = true,
    ) {
        $this->lines = new Collection();
    }

    public function add(string $template, array $values = []): self
    {
        return $this->addLine(new UserNotificationLine($template, $values));
    }

    public function addLine(UserNotificationLine $line): self
    {
        $this->lines->push($line);
        return $this;
    }

    /**
     * @return UserNotificationLine[]
     */
    public function all(): array
    {
        return $this->lines->all();
    }

    /**
     * @return Collection<UserNotificationLine>
     */
    public function lines(): Collection
    {
        return $this->lines;
    }
}
