<?php

namespace UserNotification;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;
use UserNotification\Channels\BaseChannel;
use UserNotification\Contracts\HasLogEvent;
use UserNotification\Contracts\NotifiableUser;
use UserNotification\Contracts\NotificationChannelEnum;
use UserNotification\Services\NotificationPreferencesService;
use UserNotification\Contracts\NotificationTypeEnum;
use UserNotification\Support\UserNotificationLayout;
use UserNotification\Support\UserNotificationLine;
use UserNotification\Support\UserNotificationLines;
use UserNotification\Support\UserNotificationTestList;

abstract class UserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private ?bool $_isImportant = null;
    private ?array $_channels = null;
    private bool $_allowLogEvent = true;
    private bool $_isTest = false;
    private ?MailMessage $_toMail = null;

    /**
     * Получить сервис для работы с настройками уведомлений
     * По умолчанию используется NotificationPreferencesService из библиотеки
     * Пользователь библиотеки может переопределить через метод setPreferencesService
     */
    protected static ?NotificationPreferencesService $preferencesService = null;

    /**
     * Установить локаль пользователя для локализации
     */
    public function setUserLocale(NotifiableUser $user): void
    {
        $locale = $user->getNotificationLocale();
        if ($locale) {
            App::setLocale($locale);
        }
    }

    /**
     * Установить сервис для работы с настройками уведомлений
     */
    public static function setPreferencesService(NotificationPreferencesService $service): void
    {
        self::$preferencesService = $service;
    }

    /**
     * Получить класс канала для логирования событий
     * Пользователь библиотеки может переопределить этот метод
     */
    protected function getLogEventChannelClass(): ?string
    {
        return null;
    }

    final public function getAllowLogEvent(): bool
    {
        return $this->_allowLogEvent;
    }

    final public function setAllowLogEvent(bool $allow = true): static
    {
        $this->_allowLogEvent = $allow;
        return $this;
    }

    /**
     * Получаем каналы, в которые отправляем уведомление
     * @param NotifiableUser $notifiable
     * @return array
     */
    final public function via(NotifiableUser $notifiable): array
    {
        $channels = $this->getViaChannels($notifiable);

        if ($this->getAllowLogEvent() && $this instanceof HasLogEvent) {
            $logEventChannel = $this->getLogEventChannelClass();
            if ($logEventChannel) {
                $channels[] = $logEventChannel;
            }
        }

        if (!App::environment('production') && !$this->isTest()) {
            return [];
        }

        return $channels;
    }

    final public function getViaChannels(NotifiableUser $user): array
    {
        $channels = $this->getChannels($user);

        if (!is_null($channels)) {
            return $channels;
        }

        if ($this->isImportant()) {
            // TODO: продумать логику important уведомлений
            // TODO: по-сути они должны слаться во все каналы, игнорируя настройки пользователя
        }

        $preferencesService = self::$preferencesService ?? app(NotificationPreferencesService::class);

        $preferences = $preferencesService->getNotificationPreferences($user, $this->getNotificationType(), null);
        $channelEnums = $preferences->where('is_active', true)->pluck('channel')->unique()->values();
        $channels = $channelEnums->map(function($channelEnum) {
            if (!$channelEnum instanceof NotificationChannelEnum) {
                return null;
            }
            return $channelEnum->getChannelClassName();
        })->filter()->values();

        return $channels->all();
    }

    /**
     * Получаем важность уведомления
     * @return bool
     */
    final public function isImportant(): bool
    {
        return $this->_isImportant ?? $this->defaultImportance();
    }

    /**
     * Устанавливаем важность уведомления
     * @param bool $value
     * @return UserNotification
     */
    final public function setImportant(bool $value = true): static
    {
        $this->_isImportant = $value;
        return $this;
    }

    /**
     * Устанавливаем важность по умолчанию
     * Отдельный экземпляр может переопределить методом setImportant
     * @return bool
     */
    public function defaultImportance(): bool
    {
        return false;
    }

    /**
     * Получаем каналы, в которые отправится уведомление
     * @return ?array
     */
    final public function getChannels(NotifiableUser $user): ?array
    {
        return $this->_channels ?? $this->defaultChannels($user);
    }

    /**
     * Устанавливаем каналы, игнорируя настройки пользователя
     * Значение null включает проверку настроек пользователя
     * @param ?array $channels
     * @return UserNotification
     */
    final public function setChannels(?array $channels): static
    {
        $this->_channels = $channels;
        return $this;
    }

    /**
     * Устанавливаем каналы по умолчанию, игнорируя настройки пользователя
     * Отдельный экземпляр может переопределить методом setChannels
     * Значение null включает проверку настроек пользователя
     * @return ?array
     */
    public function defaultChannels(NotifiableUser $user): ?array
    {
        return null;
    }

    /**
     * Устанавливаем тип уведомления
     * @return NotificationTypeEnum
     */
    abstract public function getNotificationType(): NotificationTypeEnum;

    /**
     * Тема письма
     * Используется как заголовок, если не переопределить getTitle
     * Возвращает ключ локализации или переведенную строку
     * @return UserNotificationLine
     */
    abstract public function getSubject(NotifiableUser $user): UserNotificationLine;

    /**
     * Заголовок письма
     * По-умолчанию используется тема из getSubject
     * Возвращает ключ локализации или переведенную строку
     * @return UserNotificationLine|null
     */
    public function getTitle(NotifiableUser $user): ?UserNotificationLine
    {
        return $this->getSubject($user);
    }

    /**
     * Структура уведомления
     * @return UserNotificationLayout
     */
    abstract public function getLayout(NotifiableUser $user, NotificationChannelEnum $channel): UserNotificationLayout;

    /**
     * @return bool
     */
    final public function isTest(): bool
    {
        return $this->_isTest;
    }

    /**
     * @return self
     */
    final public function setTest($value = true): self
    {
        $this->_isTest = $value;
        return $this;
    }

    /**
     * Тестовые уведомления
     * @return UserNotificationTestList
     */
    abstract static public function testList(NotifiableUser $user): UserNotificationTestList;

    public function toMail($notifiable): ?MailMessage
    {
        return $this->_toMail;
    }

    public function setToMail(?MailMessage $message = null): self
    {
        $this->_toMail = $message;
        return $this;
    }

    public function middleware(NotifiableUser $notifiable, string $channel): array
    {
        $manager = app(\Illuminate\Notifications\ChannelManager::class);
        $channelInstance = $manager->driver($channel);
        if ($channelInstance instanceof BaseChannel) {
            return $channelInstance->middleware($notifiable);
        }
        return [];
    }

    public function viaQueues(): array
    {
        $manager = app(\Illuminate\Notifications\ChannelManager::class);
        $channels = $manager->getDrivers();
        $queues = [];
        foreach ($channels as $channel => $channelInstance) {
            if ($channelInstance instanceof BaseChannel) {
                $queues[$channel] = $channelInstance->queue();
            }
        }
        return $queues;
    }
}
