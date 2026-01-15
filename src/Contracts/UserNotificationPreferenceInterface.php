<?php

namespace UserNotification\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Интерфейс для модели UserNotificationPreference
 * Пользователь библиотеки может использовать встроенную модель или создать свою,
 * реализующую этот интерфейс
 */
interface UserNotificationPreferenceInterface
{
    /**
     * Получить ID пользователя
     * @return int|string
     */
    public function getUserId();

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
     * Связь с пользователем
     * @return BelongsTo
     */
    public function user(): BelongsTo;
}
