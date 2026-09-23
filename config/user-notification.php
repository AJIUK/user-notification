<?php

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | Укажите полное имя класса модели User в вашем приложении.
    | По умолчанию используется App\Models\User
    |
    */
    'user_model' => env('USER_NOTIFICATION_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Preferences Table
    |--------------------------------------------------------------------------
    |
    | Укажите имя таблицы для настроек уведомлений.
    | По умолчанию используется 'user_notification_preferences'
    |
    */
    'preferences_table' => env('USER_NOTIFICATION_PREFERENCES_TABLE', 'user_notification_preferences'),

    /*
    |--------------------------------------------------------------------------
    | Delivery Logging Tables
    |--------------------------------------------------------------------------
    */
    'logs_table' => env('USER_NOTIFICATION_LOGS_TABLE', 'user_notification_logs'),
    'log_channels_table' => env('USER_NOTIFICATION_LOG_CHANNELS_TABLE', 'user_notification_log_channels'),
    'mailings_table' => env('USER_NOTIFICATION_MAILINGS_TABLE', 'user_notification_mailings'),
    'mailing_recipients_table' => env(
        'USER_NOTIFICATION_MAILING_RECIPIENTS_TABLE',
        'user_notification_mailing_recipients'
    ),

    /*
    |--------------------------------------------------------------------------
    | Delivery Logging
    |--------------------------------------------------------------------------
    |
    | Логирование доставки: parent-запись на получателя + строки по каналам.
    | Создаётся в via() до постановки в очередь, статусы обновляет BaseChannel.
    |
    */
    'logging' => [
        'enabled' => env('USER_NOTIFICATION_LOGGING_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mailings
    |--------------------------------------------------------------------------
    */
    'mailing' => [
        'queue' => env('USER_NOTIFICATION_MAILING_QUEUE', env('USER_NOTIFICATION_DEFAULT_QUEUE', 'default')),
        'chunk_size' => (int) env('USER_NOTIFICATION_MAILING_CHUNK_SIZE', 100),
        'broadcast' => env('USER_NOTIFICATION_MAILING_BROADCAST', true),
        // private-{name} в Echo; зарегистрируйте канал в routes/channels.php
        'broadcast_channel' => env('USER_NOTIFICATION_MAILING_BROADCAST_CHANNEL', 'user-notification.mailings'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Channel Enum Class
    |--------------------------------------------------------------------------
    |
    | Укажите полное имя класса enum для каналов уведомлений.
    | По умолчанию используется App\Enums\UserNotificationChannel
    |
    */
    'channel_enum' => env('USER_NOTIFICATION_CHANNEL_ENUM', 'App\\Enums\\UserNotificationChannel'),

    /*
    |--------------------------------------------------------------------------
    | Type Enum Class
    |--------------------------------------------------------------------------
    |
    | Укажите полное имя класса enum для типов уведомлений.
    | По умолчанию используется App\Enums\UserNotificationType
    |
    */
    'type_enum' => env('USER_NOTIFICATION_TYPE_ENUM', 'App\\Enums\\UserNotificationType'),

    /*
    |--------------------------------------------------------------------------
    | Default Queue
    |--------------------------------------------------------------------------
    |
    | Укажите очередь по умолчанию для уведомлений.
    | По умолчанию используется 'default'
    |
    */
    'default_queue' => env('USER_NOTIFICATION_DEFAULT_QUEUE', 'default'),
];
