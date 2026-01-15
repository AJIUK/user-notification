<?php

namespace UserNotification\Contracts;

use UserNotification\Channels\ChannelInterface;

/**
 * Интерфейс для enum каналов уведомлений
 * Пользователь библиотеки должен создать enum, реализующий этот интерфейс
 */
interface NotificationChannelEnum
{
    /**
     * Получить экземпляр канала для данного enum
     * @return ChannelInterface
     */
    public function getChannel(): ChannelInterface;

    /**
     * Получить имя класса канала для данного enum
     * @return string
     */
    public function getChannelClassName(): string;

    public function getTitle(): string;

    public function getValue();
    public function getName(): string;
}
