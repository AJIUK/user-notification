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

    /**
     * Порядок типа в словарях и настройках
     */
    public function getSort(): int;

    /**
     * Каналы, скрытые только для этого типа уведомлений
     * Глобально скрытые каналы задаются через NotificationChannelEnum::isHidden
     * @return array<int, NotificationChannelEnum>
     */
    public function getHiddenChannels(): array;

    /**
     * Каналы, которые видны в настройках, но пользователь не может их изменить
     * Скрытые каналы тоже считаются нередактируемыми
     * @return array<int, NotificationChannelEnum>
     */
    public function getReadonlyChannels(): array;

    /**
     * Канал скрыт глобально или только для этого типа
     */
    public function isChannelHidden(NotificationChannelEnum $channel): bool;

    /**
     * Канал нельзя изменить: он скрыт или входит в getReadonlyChannels
     */
    public function isChannelReadonly(NotificationChannelEnum $channel): bool;
}
