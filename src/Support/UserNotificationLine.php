<?php

namespace UserNotification\Support;

use UserNotification\Support\ChannelVisibilityControl;

class UserNotificationLine
{
    use ChannelVisibilityControl;

    public function __construct(
        public string $template,
        public array $values = []
    ) {
        foreach ($values as &$value) {
            $value = $this->escapeMarkdown($value);
        }
    }

    public function format(): string
    {
        // Используем стандартную локализацию Laravel
        // Laravel автоматически подставит значения через :param или {param}
        return __($this->template, $this->values);
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
