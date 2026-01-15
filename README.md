# UserNotification - Библиотека для работы с уведомлениями в Laravel

Библиотека предоставляет абстрактный класс для создания уведомлений с поддержкой множественных каналов (Email, Telegram, WhatsApp) и локализации.

## Установка

```bash
composer require your-vendor/user-notification
```

После установки опубликуйте миграцию, конфигурацию и views (опционально):

```bash
# Публикация всех файлов
php artisan vendor:publish --tag=user-notification

# Или отдельно:
# php artisan vendor:publish --tag=user-notification-migrations
# php artisan vendor:publish --tag=user-notification-config
# php artisan vendor:publish --tag=user-notification-views
# php artisan vendor:publish --tag=user-notification-lang

# Применение миграции
php artisan migrate
```

**Примечание:**
- Views и языковые файлы загружаются автоматически из библиотеки
- Публикация нужна только для кастомизации шаблонов или языковых файлов
- Библиотека использует стандартную локализацию Laravel с функцией `__()`
- После публикации провайдер `App\Providers\UserNotificationServiceProvider` автоматически регистрируется в `bootstrap/providers.php` (Laravel 11+)

## Требования

- PHP >= 8.1
- Laravel >= 10.0
- SergiX44/Nutgram (для Telegram канала)

## Настройка

### 1. Регистрация ServiceProvider

После публикации файлов будет создан `App\Providers\UserNotificationServiceProvider`.
**Важно:** Провайдер автоматически регистрируется в `bootstrap/providers.php` (Laravel 11+) при загрузке библиотеки.

Если автоматическая регистрация не сработала, зарегистрируйте его вручную в `bootstrap/providers.php`:

```php
return [
    // ...
    App\Providers\UserNotificationServiceProvider::class,
];
```

В этом ServiceProvider зарегистрируйте типы и каналы уведомлений (см. раздел ниже).

### 2. Реализация интерфейсов

#### Модель User должна реализовывать `NotifiableUser`

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
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

#### Регистрация типов и каналов в ServiceProvider

В `App\Providers\UserNotificationServiceProvider`:

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

#### Создание Enum для каналов уведомлений

```php
<?php

namespace App\Enums;

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

**Важно:** Enum должен иметь `int` backing type, так как в БД поля `type` и `channel` хранятся как `unsignedTinyInteger`. Если вам нужно использовать строковые значения, измените миграцию после публикации.

#### Создание Enum для типов уведомлений

```php
<?php

namespace App\Enums;

use UserNotification\Contracts\NotificationTypeEnum;

enum UserNotificationType: int implements NotificationTypeEnum
{
    case DEAL_CREATED = 1;
    case DEAL_UPDATED = 2;
    // ... другие типы

    public function getTitle(): string
    {
        return match($this) {
            self::DEAL_CREATED => __('notifications.types.deal_created.title'),
            self::DEAL_UPDATED => __('notifications.types.deal_updated.title'),
            // ...
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::DEAL_CREATED => __('notifications.types.deal_created.description'),
            self::DEAL_UPDATED => __('notifications.types.deal_updated.description'),
            // ...
        };
    }

    public function getDefaultChannels(): array
    {
        return match($this) {
            self::DEAL_CREATED => [UserNotificationChannel::EMAIL, UserNotificationChannel::TELEGRAM],
            self::DEAL_UPDATED => [UserNotificationChannel::EMAIL],
            // ...
        };
    }

    public function getValue()
    {
        return $this->value;
    }
}
```

**Важно:** Enum должен иметь `int` backing type, так как в БД поле `type` хранится как `unsignedTinyInteger`. Если вам нужно использовать строковые значения, измените миграцию после публикации.

#### Модель UserNotificationPreference

Библиотека включает готовую модель `UserNotificationPreference` для хранения настроек уведомлений.

**Структура таблицы:**
- `user_id` - ID пользователя (unsignedBigInteger, foreign key с cascade delete)
- `type` - тип уведомления (unsignedTinyInteger, int значение enum NotificationTypeEnum)
- `channel` - канал уведомления (unsignedTinyInteger, int значение enum NotificationChannelEnum)
- `is_active` - активна ли настройка (boolean)

**Особенности:**
- Foreign key constraint на `user_id` с `cascadeOnDelete` - при удалении пользователя автоматически удаляются его настройки
- Foreign key создается через модель User из конфига `user-notification.user_model`
- Имя таблицы настроек можно изменить через конфиг `user-notification.preferences_table`

**Важно:** Enum'ы должны иметь int backing type (например, `enum UserNotificationType: int`).

**Особенности модели:**
- Без первичного ключа (составной ключ: user_id, type, channel)
- Без timestamps
- Без auto-increment

Вы можете использовать встроенную модель или создать свою, реализующую интерфейс `UserNotificationPreferenceInterface`.

#### Регистрация типов и каналов уведомлений

После публикации файлов будет создан `App\Providers\UserNotificationServiceProvider`.
**Важно:** Зарегистрируйте его в `config/app.php`:

```php
'providers' => [
    // ...
    App\Providers\UserNotificationServiceProvider::class,
],
```

В этом ServiceProvider нужно зарегистрировать типы и каналы уведомлений:

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

### 2. Создание уведомления

Создайте класс уведомления с помощью команды:

```bash
# Создать в App\Notifications
php artisan make:user-notification MyNotification

# Создать в App\Notifications\Front
php artisan make:user-notification MyNotification --namespace=Front
```

Или создайте класс вручную:

```php
<?php

namespace App\Notifications;

use App\Enums\UserNotificationType;
use UserNotification\Contracts\NotificationChannelEnum;
use UserNotification\Contracts\NotificationTypeEnum;
use UserNotification\Contracts\NotifiableUser;
use UserNotification\Support\UserNotificationLayout;
use UserNotification\Support\UserNotificationLines;
use UserNotification\Support\UserNotificationTestList;
use UserNotification\UserNotification;

class DealCreatedNotification extends UserNotification
{
    public function __construct(
        private Deal $deal
    ) {}

    public function getNotificationType(): NotificationTypeEnum
    {
        return UserNotificationType::DEAL_CREATED;
    }

    public function getSubject(NotifiableUser $user): string
    {
        // Возвращаем ключ локализации или переведенную строку
        return 'notifications.deal_created.subject';
    }

    public function getLayout(
        NotifiableUser $user,
        NotificationChannelEnum $channel
    ): UserNotificationLayout {
        $layout = new UserNotificationLayout();

        $lines = new UserNotificationLines();
        // Используем ключи локализации с параметрами (формат Laravel :param)
        $lines->add('notifications.deal_created.message', ['id' => $this->deal->id]);

        $layout->addLines($lines);

        return $layout;
    }

    public static function testList(NotifiableUser $user): UserNotificationTestList
    {
        $list = new UserNotificationTestList();
        // Добавьте тестовые уведомления
        return $list;
    }
}
```

### 3. Использование

```php
use App\Notifications\DealCreatedNotification;

$user->notify(new DealCreatedNotification($deal));
```

### 4. API для управления настройками уведомлений

Библиотека предоставляет готовый контроллер `UserNotificationPreferencesController` для работы с настройками уведомлений через API.

#### Регистрация роутов

Добавьте роуты в `routes/api.php` или `routes/web.php`:

```php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserNotificationPreferencesController;

// Для API с аутентификацией
Route::middleware('auth:sanctum')->group(function () {
    // Получить настройки уведомлений текущего пользователя
    Route::get('/user/notification-preferences', [UserNotificationPreferencesController::class, 'getNotificationPreferences'])
        ->name('user.notification-preferences.index');
    
    // Получить словари (типы и каналы уведомлений)
    Route::get('/notification-dictionaries', [UserNotificationPreferencesController::class, 'getNotificationDictionaries'])
        ->name('notification-dictionaries.index');
    
    // Обновить настройки уведомлений текущего пользователя
    Route::put('/user/notification-preferences', [UserNotificationPreferencesController::class, 'setNotificationPreferences'])
        ->name('user.notification-preferences.update');
});
```

**Примечание:** После публикации контроллер будет находиться в `App\Http\Controllers\UserNotificationPreferencesController` и вы сможете его изменить по необходимости.

#### Методы контроллера

**`getNotificationPreferences(Request $request, NotifiableUser $user)`**
- Возвращает настройки уведомлений для указанного пользователя
- Ответ:
```json
{
    "notification_preferences": [
        {
            "type": 1,
            "channel": 1,
            "is_active": true
        }
    ]
}
```

**`getNotificationDictionaries(Request $request)`**
- Возвращает словари типов и каналов уведомлений
- Ответ:
```json
{
    "notification_types": [
        {
            "id": 1,
            "code": "DEAL_CREATED",
            "title": "Создание сделки",
            "description": "Уведомления о создании новых сделок",
            "default_channels": [1, 2]
        }
    ],
    "notification_channels": [
        {
            "id": 1,
            "code": "EMAIL"
        },
        {
            "id": 2,
            "code": "TELEGRAM"
        }
    ]
}
```

**`setNotificationPreferences(UpdateUserNotificationPreferencesRequest $request, NotifiableUser $user)`**
- Обновляет настройки уведомлений для указанного пользователя
- Принимает:
```json
{
    "locale": "ru",
    "preferences": [
        {
            "type": 1,
            "channel": 1,
            "is_active": true
        },
        {
            "type": 1,
            "channel": 2,
            "is_active": false
        }
    ]
}
```
- Ответ:
```json
{
    "message": "Notification preferences updated successfully"
}
```

#### Примеры запросов

**Получить настройки:**
```bash
GET /api/user/notification-preferences
Authorization: Bearer {token}
```

**Получить словари:**
```bash
GET /api/notification-dictionaries
```

**Обновить настройки:**
```bash
PUT /api/user/notification-preferences
Authorization: Bearer {token}
Content-Type: application/json

{
    "locale": "ru",
    "preferences": [
        {
            "type": 1,
            "channel": 1,
            "is_active": true
        },
        {
            "type": 1,
            "channel": 2,
            "is_active": false
        }
    ]
}
```

## Структура библиотеки

```
user-notification/
├── src/
│   ├── Channels/               # Классы каналов уведомлений
│   │   ├── ChannelInterface.php
│   │   ├── MailChannel.php
│   │   ├── TelegramChannel.php
│   │   └── WhatsAppChannel.php
│   ├── Contracts/              # Интерфейсы для реализации
│   │   ├── HasLogEvent.php
│   │   ├── NotifiableUser.php
│   │   ├── NotificationChannelEnum.php
│   │   ├── NotificationTypeEnum.php
│   │   └── UserNotificationPreferenceInterface.php
│   ├── Models/                 # Модели
│   │   └── UserNotificationPreference.php
│   ├── Services/               # Сервисы
│   │   └── NotificationPreferencesService.php
│   ├── Support/                # Вспомогательные классы
│   │   ├── ChannelVisibilityControl.php
│   │   ├── NotificationRegistry.php
│   │   ├── UserNotificationAction.php
│   │   ├── UserNotificationComponent.php
│   │   ├── UserNotificationLayout.php
│   │   ├── UserNotificationLine.php
│   │   ├── UserNotificationLines.php
│   │   ├── UserNotificationLocale.php
│   │   ├── UserNotificationMarkdown.php
│   │   ├── UserNotificationTest.php
│   │   └── UserNotificationTestList.php
│   ├── UserNotification.php   # Основной абстрактный класс
│   └── UserNotificationServiceProvider.php
├── stubs/                      # Stub-файлы для публикации
│   ├── MailChannel.php.stub
│   ├── UpdateUserNotificationPreferencesRequest.php.stub
│   ├── UserNotificationChannel.php.stub
│   ├── UserNotificationChannelResource.php.stub
│   ├── UserNotificationPreferenceResource.php.stub
│   ├── UserNotificationPreferencesController.php.stub
│   ├── UserNotificationServiceProvider.php.stub
│   ├── UserNotificationType.php.stub
│   └── UserNotificationTypeResource.php.stub
├── database/
│   └── migrations/
│       └── create_user_notification_preferences_table.php.stub
├── resources/
│   └── views/
│       └── emails/
│           └── user-notification.blade.php
├── lang/
│   ├── ru/
│   │   └── user-notification.php
│   └── en/
│       └── user-notification.php
├── config/
│   └── user-notification.php
├── composer.json
└── README.md
```

## Публикация файлов

Библиотека использует стандартный механизм Laravel для публикации файлов:

```bash
# Публикация всех файлов
php artisan vendor:publish --tag=user-notification

# Или отдельно:
# php artisan vendor:publish --tag=user-notification-migrations
# php artisan vendor:publish --tag=user-notification-config
# php artisan vendor:publish --tag=user-notification-views
# php artisan vendor:publish --tag=user-notification-lang
# php artisan vendor:publish --tag=user-notification-channels
# php artisan vendor:publish --tag=user-notification-enums
# php artisan vendor:publish --tag=user-notification-provider
# php artisan vendor:publish --tag=user-notification-controllers
```
<｜tool▁calls▁begin｜><｜tool▁call▁begin｜>
read_file

**Views и Lang:** По умолчанию views и языковые файлы загружаются из библиотеки. Публикация нужна только для кастомизации.

## Локализация

Библиотека использует стандартную систему локализации Laravel с функцией `__()`.

Локаль пользователя устанавливается автоматически из метода `getNotificationLocale()` интерфейса `NotifiableUser`.

Пример использования в уведомлениях:

```php
public function getSubject(NotifiableUser $user): string
{
    // Возвращаем ключ локализации
    return 'notifications.deal_created.subject';
}

public function getLayout(NotifiableUser $user, NotificationChannelEnum $channel): UserNotificationLayout
{
    $layout = new UserNotificationLayout();
    $lines = new UserNotificationLines();

    // Используем ключи локализации с параметрами (формат Laravel :param)
    $lines->add('notifications.deal_created.message', ['id' => $this->deal->id]);

    // Или прямые строки с параметрами
    $lines->add('Создана новая сделка #:id', ['id' => $this->deal->id]);

    $layout->addLines($lines);
    return $layout;
}
```

**Важно:** Параметры передаются в формате Laravel `:param` или `{param}`, а не `%s` как в vsprintf.

Библиотека включает базовые языковые файлы для русского и английского языков с ключами:
- `user-notification::notification.thank_you` - "Спасибо, что вы с нами," / "Thank you for being with us,"
- `user-notification::notification.your_team` - "Ваш :team" / "Your :team Team"
- `user-notification::type.title_system` - "Системные" / "System"
- `user-notification::type.description_system` - "Хочу получать системные уведомления" / "I want to receive system notifications"

## Зависимости

Библиотека использует следующие зависимости:

- `illuminate/notifications` - для базового функционала уведомлений Laravel
- `illuminate/support` - для коллекций и других утилит
- `illuminate/queue` - для очередей
- `sergix44/nutgram` - для работы с Telegram (опционально, если используете Telegram канал)

Все зависимости должны быть установлены в вашем Laravel проекте.

## Лицензия

MIT
