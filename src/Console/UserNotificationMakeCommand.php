<?php

namespace UserNotification\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:user-notification')]
class UserNotificationMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:user-notification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new user notification class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'UserNotification';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return $this->resolveStubPath('/stubs/user-notification.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param  string  $stub
     * @return string
     */
    protected function resolveStubPath($stub)
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__.'/../..'.$stub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        $namespace = $rootNamespace.'\Notifications';

        if ($this->option('namespace')) {
            $namespace .= '\\'.$this->option('namespace');
        }

        return $namespace;
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);

        // Заменяем {{ name }} на имя класса в snake_case для использования в ключах локализации
        $name = str_replace('Notification', '', class_basename($name));
        $name = Str::snake($name);

        return str_replace('{{ name }}', $name, $stub);
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the notification already exists'],
            ['namespace', null, InputOption::VALUE_OPTIONAL, 'The namespace for the notification class (e.g., Front)'],
        ];
    }
}
