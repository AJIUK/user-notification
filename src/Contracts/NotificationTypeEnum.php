<?php

namespace UserNotification\Contracts;

/**
 * Интерфейс для enum типов уведомлений
 * Пользователь библиотеки должен создать enum, реализующий этот интерфейс
 */
interface NotificationTypeEnum
{
    public function getTitle(): string;
    public function getDescription(): string;
    public function getDefaultChannels(): array;
    public function getValue();
    public function getName(): string;
}
