<?php

namespace UserNotification\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Интерфейс для модели UserNotificationPreference.
 * Настройки принадлежат любой Eloquent-модели (полиморфная связь notifiable).
 */
interface UserNotificationPreferenceInterface
{
    /**
     * Получить ID владельца настроек
     * @return int|string
     */
    public function getNotifiableId();

    /**
     * Получить тип уведомления
     * @return int
     */
    public function getType(): int;

    /**
     * Получить канал уведомления
     * @return int
     */
    public function getChannel(): int;

    /**
     * Проверить, активна ли настройка
     * @return bool
     */
    public function getIsActive(): bool;

    /**
     * Владелец настроек: User, Agent и любая другая модель
     * @return MorphTo
     */
    public function notifiable(): MorphTo;
}
