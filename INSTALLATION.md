# Инструкция по установке и настройке

## Шаг 1: Установка через Composer

```bash
composer require your-vendor/user-notification
```

## Шаг 2: Публикация файлов

```bash
# Публикация всех файлов (миграция, конфигурация, контроллеры, ресурсы и т.д.)
php artisan vendor:publish --tag=user-notification

# Или отдельно:
# php artisan vendor:publish --tag=user-notification-controllers  # Контроллер, ресурсы, Request
# php artisan vendor:publish --tag=user-notification-migrations  # Миграции
# php artisan vendor:publish --tag=user-notification-config      # Конфигурация

# Применение миграции
php artisan migrate
```

**Примечание:** Контроллер `UserNotificationPreferencesController`, ресурсы и Request класс публикуются в `app/Http/` и могут быть изменены пользователем по необходимости.

## Шаг 3: Регистрация ServiceProvider

После публикации будет создан `App\Providers\UserNotificationServiceProvider`. 
**Важно:** Провайдер автоматически регистрируется в `bootstrap/providers.php` (Laravel 11+) при загрузке библиотеки.

Если автоматическая регистрация не сработала, зарегистрируйте его вручную в `bootstrap/providers.php`:

```php
return [
    // ...
    App\Providers\UserNotificationServiceProvider::class,
];
```

## Шаг 4: Реализация обязательных интерфейсов

### 3.1. Модель User

Ваша модель `User` должна реализовывать интерфейс `NotifiableUser` и использовать трейт `Notifiable`:

```php
use Illuminate\Notifications\Notifiable;
use UserNotification\Contracts\NotifiableUser;

class User extends Authenticatable implements NotifiableUser
{
    use Notifiable;

    public function getNotificationLocale(): ?string
    {
        return $this->notification_locale ?? null;
    }

    public function getNotificationName(): string
    {
        return $this->name ?? $this->first_name ?? '';
    }

    public function getNotificationEmail(): string
    {
        return $this->email ?? '';
    }

    public function getKey(): mixed
    {
        return parent::getKey();
    }
}
```

### 4.2. Enum каналов уведомлений

Создайте enum, реализующий `NotificationChannelEnum`:

```php
use UserNotification\Contracts\NotificationChannelEnum;

enum UserNotificationChannel: int implements NotificationChannelEnum
{
    case EMAIL = 1;
    case TELEGRAM = 2;
    case WHATSAPP = 3;

    public function getChannel(): ChannelInterface
    {
        return match($this) {
            self::EMAIL => app(\App\Notifications\Channels\MailChannel::class),
            self::TELEGRAM => app(\App\Notifications\Channels\TelegramChannel::class),
            self::WHATSAPP => app(\App\Notifications\Channels\WhatsAppChannel::class),
        };
    }

    public function getChannelClassName(): string
    {
        return match($this) {
            self::EMAIL => \App\Notifications\Channels\MailChannel::class,
            self::TELEGRAM => \App\Notifications\Channels\TelegramChannel::class,
            self::WHATSAPP => \App\Notifications\Channels\WhatsAppChannel::class,
        };
    }

    public function getValue()
    {
        return $this->value;
    }
}
```

### 4.3. Enum типов уведомлений

Создайте enum, реализующий `NotificationTypeEnum`:

```php
use UserNotification\Contracts\NotificationTypeEnum;

enum UserNotificationType: int implements NotificationTypeEnum
{
    case DEAL_CREATED = 1;
    // ... другие типы

    public function getTitle(): string
    {
        return match($this) {
            self::DEAL_CREATED => __('notifications.types.deal_created.title'),
            // ...
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::DEAL_CREATED => __('notifications.types.deal_created.description'),
            // ...
        };
    }

    public function getDefaultChannels(): array
    {
        return match($this) {
            self::DEAL_CREATED => [UserNotificationChannel::EMAIL, UserNotificationChannel::TELEGRAM],
            // ...
        };
    }

    public function getValue()
    {
        return $this->value;
    }
}
```

### 4.4. Регистрация типов и каналов уведомлений

После публикации файлов будет создан `App\Providers\UserNotificationServiceProvider`. 
**Важно:** Провайдер автоматически регистрируется в `bootstrap/providers.php` (Laravel 11+) при загрузке библиотеки.

В этом ServiceProvider зарегистрируйте типы и каналы:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use UserNotification\Support\NotificationRegistry;
use App\Enums\UserNotificationChannel;
use App\Enums\UserNotificationType;

class UserNotificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Регистрируем типы уведомлений
        NotificationRegistry::registerTypes(UserNotificationType::cases());

        // Регистрируем каналы уведомлений
        NotificationRegistry::registerChannels(UserNotificationChannel::cases());
    }
}
```

**Примечание:** Библиотека предоставляет готовый сервис `NotificationPreferencesService`, который используется по умолчанию. 
Вы можете расширить его или создать свой, зарегистрировав в `AppServiceProvider`.

## Шаг 5: Создание первого уведомления

Создайте класс уведомления с помощью команды:

```bash
# Создать в App\Notifications
php artisan make:user-notification MyNotification

# Создать в App\Notifications\Front
php artisan make:user-notification MyNotification --namespace=Front
```

Или создайте класс вручную, расширяющий `UserNotification`:

```php
use UserNotification\UserNotification;
use UserNotification\Contracts\NotificationTypeEnum;
use UserNotification\Contracts\NotificationChannelEnum;
use UserNotification\Support\UserNotificationLayout;
use UserNotification\Support\UserNotificationLines;
use UserNotification\Support\UserNotificationLine;

class MyNotification extends UserNotification
{
    public function getNotificationType(): NotificationTypeEnum
    {
        return UserNotificationType::MY_TYPE;
    }

    public function getSubject(NotifiableUser $user): UserNotificationLine
    {
        // Возвращаем UserNotificationLine с ключом локализации или переведенной строкой
        return new UserNotificationLine('notifications.my_notification.subject');
    }

    public function getLayout(
        NotifiableUser $user, 
        NotificationChannelEnum $channel
    ): UserNotificationLayout {
        $layout = new UserNotificationLayout();
        
        $lines = new UserNotificationLines();
        // Используем ключи локализации с параметрами (формат Laravel :param)
        $lines->add('notifications.my_notification.message', ['id' => 123]);
        
        $layout->addLines($lines);
        return $layout;
    }

    public static function testList(NotifiableUser $user): UserNotificationTestList
    {
        return new UserNotificationTestList();
    }
}
```

## Шаг 6: Настройка API для управления настройками уведомлений

Библиотека предоставляет готовый контроллер `UserNotificationPreferencesController` для работы с настройками уведомлений через API.

### Регистрация роутов

После публикации контроллера добавьте роуты в `routes/api.php`:

```php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserNotificationPreferencesController;

Route::middleware('auth:sanctum')->group(function () {
    // Получить настройки уведомлений текущего пользователя
    Route::get('/user/notification-preferences', [UserNotificationPreferencesController::class, 'getNotificationPreferences']);
    
    // Получить словари (типы и каналы уведомлений)
    Route::get('/notification-dictionaries', [UserNotificationPreferencesController::class, 'getNotificationDictionaries']);
    
    // Обновить настройки уведомлений текущего пользователя
    Route::put('/user/notification-preferences', [UserNotificationPreferencesController::class, 'setNotificationPreferences']);
});
```

**Примечание:** После публикации контроллер будет находиться в `App\Http\Controllers\UserNotificationPreferencesController` и вы сможете его изменить по необходимости.

### Методы контроллера

- **`getNotificationPreferences()`** - получить настройки уведомлений пользователя
- **`getNotificationDictionaries()`** - получить словари типов и каналов
- **`setNotificationPreferences()`** - обновить настройки уведомлений

## Готово!

Теперь вы можете использовать уведомления:

```php
$user->notify(new MyNotification());
```

И управлять настройками уведомлений через API.
