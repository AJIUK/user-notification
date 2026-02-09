<?php

namespace UserNotification\Contracts;

use UserNotification\Channels\BaseChannel;

/**
 * Интерфейс для enum каналов уведомлений
 * Пользователь библиотеки должен создать enum, реализующий этот интерфейс
 */
interface NotificationChannelEnum
{
    /**
     * Получить экземпляр канала для данного enum
     * @return BaseChannel
     */
    public function getChannel(): BaseChannel;

    /**
     * Получить имя класса канала для данного enum
     * @return string
     */
    public function getChannelClassName(): string;

    public function getTitle(): string;

    public function getValue();
    public function getName(): string;
}
