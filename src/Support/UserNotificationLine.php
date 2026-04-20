<?php

namespace UserNotification\Support;

use UserNotification\Support\ChannelVisibilityControl;

class UserNotificationLine
{
    use ChannelVisibilityControl;

    public function __construct(
        public string $template,
        public array $values = [],
        public int $count = 1
    ) {
        foreach ($values as &$value) {
            $value = $this->escapeMarkdown($value);
        }
    }

    public function format(): string
    {
        return trans_choice($this->template, $this->count, $this->values);
    }

    function escapeMarkdown(?string $text, ?string $default = null): ?string
    {
        if (empty($text)) return $default;

        $text = trim($text);

        $specialChars = ['*', '_', '#', '~', '`', '>'];
        foreach ($specialChars as $char) {
            $text = str_replace($char, ' ', $text);
        }
        return $text;
    }
}
