# Пример использования библиотеки UserNotification

## Полный пример интеграции

### 1. Модель User

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use UserNotification\Contracts\NotifiableUser;

class User extends Authenticatable implements NotifiableUser
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'notification_locale',
    ];

    public function getNotificationLocale(): ?string
    {
        return $this->notification_locale ?? 'ru';
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

### 2. Enum каналов

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

### 3. Enum типов

```php
<?php

namespace App\Enums;

use UserNotification\Contracts\NotificationTypeEnum;

enum UserNotificationType: int implements NotificationTypeEnum
{
    case DEAL_CREATED = 1;
    case DEAL_UPDATED = 2;
    case PAYMENT_RECEIVED = 3;

    public function getTitle(): string
    {
        return match($this) {
            self::DEAL_CREATED => __('notifications.types.deal_created.title'),
            self::DEAL_UPDATED => __('notifications.types.deal_updated.title'),
            self::PAYMENT_RECEIVED => __('notifications.types.payment_received.title'),
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::DEAL_CREATED => __('notifications.types.deal_created.description'),
            self::DEAL_UPDATED => __('notifications.types.deal_updated.description'),
            self::PAYMENT_RECEIVED => __('notifications.types.payment_received.description'),
        };
    }

    public function getDefaultChannels(): array
    {
        return match($this) {
            self::DEAL_CREATED => [UserNotificationChannel::EMAIL, UserNotificationChannel::TELEGRAM],
            self::DEAL_UPDATED => [UserNotificationChannel::EMAIL],
            self::PAYMENT_RECEIVED => [UserNotificationChannel::EMAIL, UserNotificationChannel::WHATSAPP],
        };
    }

    public function getValue()
    {
        return $this->value;
    }
}
```

### 4. Регистрация типов и каналов

После публикации файлов будет создан `App\Providers\UserNotificationServiceProvider`. 
Зарегистрируйте его в `config/app.php` и настройте регистрацию:

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

### 6. Пример уведомления

```php
<?php

namespace App\Notifications;

use App\Enums\UserNotificationChannel;
use App\Enums\UserNotificationType;
use App\Models\Deal;
use UserNotification\Contracts\NotificationChannelEnum;
use UserNotification\Contracts\NotificationTypeEnum;
use UserNotification\Contracts\NotifiableUser;
use UserNotification\Support\UserNotificationAction;
use UserNotification\Support\UserNotificationLayout;
use UserNotification\Support\UserNotificationLines;
use UserNotification\Support\UserNotificationLine;
use UserNotification\Support\UserNotificationTest;
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

    public function getSubject(NotifiableUser $user): UserNotificationLine
    {
        // Возвращаем UserNotificationLine с ключом локализации или переведенной строкой
        return new UserNotificationLine('notifications.deal_created.subject');
    }

    public function getLayout(
        NotifiableUser $user, 
        NotificationChannelEnum $channel
    ): UserNotificationLayout {
        $layout = new UserNotificationLayout();
        
        // Основной текст
        $lines = new UserNotificationLines();
        // Используем ключи локализации с параметрами (формат Laravel :param)
        $lines->add('notifications.deal_created.message', ['id' => $this->deal->id]);
        $lines->add('notifications.deal_created.project', ['name' => $this->deal->project->name ?? 'N/A']);
        
        $layout->addLines($lines);
        
        // Кнопка действия
        $action = new UserNotificationAction(
            __('notifications.deal_created.action'),
            config('app.front_url') . '/deals/' . $this->deal->id
        );
        
        $layout->addAction($action);
        
        return $layout;
    }

    public static function testList(NotifiableUser $user): UserNotificationTestList
    {
        $list = new UserNotificationTestList();
        
        // Создаем тестовое уведомление
        $testDeal = new Deal();
        $testDeal->id = 123;
        $testNotification = new static($testDeal);
        
        $list->add(new UserNotificationTest($testNotification, $user));
        
        return $list;
    }
}
```

### 7. Использование

```php
use App\Notifications\DealCreatedNotification;

// Отправка уведомления
$user->notify(new DealCreatedNotification($deal));

// Принудительная отправка в определенные каналы
$notification = new DealCreatedNotification($deal);
$notification->setChannels([
    \Illuminate\Notifications\Channels\MailChannel::class,
]);
$user->notify($notification);

// Важное уведомление
$notification = new DealCreatedNotification($deal);
$notification->setImportant(true);
$user->notify($notification);
```
