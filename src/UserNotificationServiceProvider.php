<?php

namespace UserNotification;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Configuration\ApplicationBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use UserNotification\Services\NotificationPreferencesService;

class UserNotificationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/user-notification.php',
            'user-notification'
        );

        // Регистрируем NotificationPreferencesService
        // Пользователь может переопределить, зарегистрировав свой класс в AppServiceProvider
        if (!$this->app->bound(NotificationPreferencesService::class)) {
            $this->app->singleton(NotificationPreferencesService::class);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'user-notification');

        // Устанавливаем сервис в UserNotification после того, как все сервисы зарегистрированы
        UserNotification::setPreferencesService(
            $this->app->make(NotificationPreferencesService::class)
        );

        $this->offerPublishing();
        
        // Автоматически регистрируем провайдер в bootstrap/providers.php (Laravel 11+)
        $this->registerProviderInBootstrap();

        // Регистрируем команды
        if ($this->app->runningInConsole()) {
            $this->commands([
                \UserNotification\Console\UserNotificationMakeCommand::class,
            ]);
        }
    }

    /**
     * Setup the resource publishing groups.
     */
    protected function offerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        if (! function_exists('config_path')) {
            // function not available and 'publish' not relevant in Lumen
            return;
        }

        $this->publishes([
            __DIR__.'/../config/user-notification.php' => config_path('user-notification.php'),
        ], 'user-notification-config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_user_notification_preferences_table.php.stub' =>
                $this->getMigrationFileName('create_user_notification_preferences_table.php'),
        ], 'user-notification-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/user-notification'),
        ], 'user-notification-views');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/user-notification'),
        ], 'user-notification-lang');

        $this->publishes([
            __DIR__.'/../stubs/MailChannel.php.stub' => app_path('Notifications/Channels/MailChannel.php'),
        ], 'user-notification-channels');

        $this->publishes([
            __DIR__.'/../stubs/UserNotificationChannel.php.stub' => app_path('Enums/UserNotificationChannel.php'),
            __DIR__.'/../stubs/UserNotificationType.php.stub' => app_path('Enums/UserNotificationType.php'),
        ], 'user-notification-enums');

        $this->publishes([
            __DIR__.'/../stubs/UserNotificationServiceProvider.php.stub' => app_path('Providers/UserNotificationServiceProvider.php'),
        ], 'user-notification-provider');

        $this->publishes([
            __DIR__.'/../stubs/UserNotificationPreferencesController.php.stub' => app_path('Http/Controllers/UserNotificationPreferencesController.php'),
            __DIR__.'/../stubs/UserNotificationPreferenceResource.php.stub' => app_path('Http/Resources/UserNotificationPreferenceResource.php'),
            __DIR__.'/../stubs/UserNotificationTypeResource.php.stub' => app_path('Http/Resources/UserNotificationTypeResource.php'),
            __DIR__.'/../stubs/UserNotificationChannelResource.php.stub' => app_path('Http/Resources/UserNotificationChannelResource.php'),
            __DIR__.'/../stubs/UpdateUserNotificationPreferencesRequest.php.stub' => app_path('Http/Requests/UpdateUserNotificationPreferencesRequest.php'),
        ], 'user-notification-controllers');

        $this->publishes([
            __DIR__.'/../config/user-notification.php' => config_path('user-notification.php'),
            __DIR__.'/../database/migrations/create_user_notification_preferences_table.php.stub' =>
                $this->getMigrationFileName('create_user_notification_preferences_table.php'),
            __DIR__.'/../resources/views' => resource_path('views/vendor/user-notification'),
            __DIR__.'/../lang' => $this->app->langPath('vendor/user-notification'),
            __DIR__.'/../stubs/MailChannel.php.stub' => app_path('Notifications/Channels/MailChannel.php'),
            __DIR__.'/../stubs/UserNotificationChannel.php.stub' => app_path('Enums/UserNotificationChannel.php'),
            __DIR__.'/../stubs/UserNotificationType.php.stub' => app_path('Enums/UserNotificationType.php'),
            __DIR__.'/../stubs/UserNotificationServiceProvider.php.stub' => app_path('Providers/UserNotificationServiceProvider.php'),
            __DIR__.'/../stubs/UserNotificationPreferencesController.php.stub' => app_path('Http/Controllers/UserNotificationPreferencesController.php'),
            __DIR__.'/../stubs/UserNotificationPreferenceResource.php.stub' => app_path('Http/Resources/UserNotificationPreferenceResource.php'),
            __DIR__.'/../stubs/UserNotificationTypeResource.php.stub' => app_path('Http/Resources/UserNotificationTypeResource.php'),
            __DIR__.'/../stubs/UserNotificationChannelResource.php.stub' => app_path('Http/Resources/UserNotificationChannelResource.php'),
            __DIR__.'/../stubs/UpdateUserNotificationPreferencesRequest.php.stub' => app_path('Http/Requests/UpdateUserNotificationPreferencesRequest.php'),
        ], 'user-notification');

        // Загружаем views из библиотеки
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'user-notification');
    }

    /**
     * Returns existing migration file if found, else uses the current timestamp.
     */
    protected function getMigrationFileName(string $migrationFileName): string
    {
        $timestamp = date('Y_m_d_His');

        $filesystem = $this->app->make(Filesystem::class);

        return Collection::make([$this->app->databasePath().DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR])
            ->flatMap(fn ($path) => $filesystem->glob($path.'*_'.$migrationFileName))
            ->push($this->app->databasePath()."/migrations/{$timestamp}_{$migrationFileName}")
            ->first();
    }

    /**
     * Автоматически регистрирует App\Providers\UserNotificationServiceProvider в bootstrap/providers.php
     * Аналогично тому, как это делает Laravel Nova
     */
    protected function registerProviderInBootstrap(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $files = $this->app->make(Filesystem::class);
        $providerPath = app_path('Providers/UserNotificationServiceProvider.php');
        $appNamespace = $this->app->getNamespace();

        // Проверяем Laravel 11+ и наличие опубликованного провайдера
        if (class_exists(ApplicationBuilder::class) 
            && $files->exists(base_path('bootstrap/providers.php'))
            && $files->exists($providerPath)
            && method_exists(ServiceProvider::class, 'addProviderToBootstrapFile')) {
            
            ServiceProvider::addProviderToBootstrapFile("{$appNamespace}Providers\\UserNotificationServiceProvider");
        }
    }

}
