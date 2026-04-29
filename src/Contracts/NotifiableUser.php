<?php

namespace UserNotification\Contracts;

/**
 * Интерфейс для модели пользователя
 * Пользователь библиотеки должен реализовать этот интерфейс в своей модели User
 */
interface NotifiableUser
{
    /**
     * Получить локаль для уведомлений
     * @return string|null
     */
    public function getNotificationLocale(): ?string;

    /**
     * Получить имя пользователя
     * @return string
     */
    public function getNotificationName(): string;

    /**
     * Получить email пользователя
     * @return string
     */
    public function getNotificationEmail(): ?string;

    /**
     * Получить id пользователя
     * @return mixed
     */
    public function getKey();
}
