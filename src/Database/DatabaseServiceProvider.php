<?php

declare(strict_types=1);

namespace Ract\Database;

use Ract\Application;
use Ract\Config\Config;
use Ract\Container\Container;
use Ract\Database\Migrations\Migrator;
use Ract\Database\Schema\SchemaBuilder;
use Ract\Support\ServiceProvider;

final class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            DatabaseManager::class,
            static fn (Container $app): DatabaseManager => new DatabaseManager($app->make(Config::class)),
        );
        $this->app->singleton(
            SchemaBuilder::class,
            static fn (Container $app): SchemaBuilder => new SchemaBuilder(
                $app->make(DatabaseManager::class)->connection(),
            ),
        );
        $this->app->singleton(
            Migrator::class,
            static fn (Container $app): Migrator => new Migrator(
                $app->make(DatabaseManager::class)->connection(),
                $app->make(SchemaBuilder::class),
                $app->make(Application::class)->rootPath()
                    . DIRECTORY_SEPARATOR . 'database'
                    . DIRECTORY_SEPARATOR . 'migrations',
            ),
        );
    }

    public function boot(): void
    {
        Model::setConnectionResolver($this->app->make(DatabaseManager::class));
    }
}
