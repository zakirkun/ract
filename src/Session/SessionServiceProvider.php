<?php

declare(strict_types=1);

namespace Ract\Session;

use LogicException;
use Ract\Application;
use Ract\Config\Config;
use Ract\Container\Container;
use Ract\Support\ServiceProvider;

final class SessionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SessionDriver::class, static function (Container $container): SessionDriver {
            $config = $container->make(Config::class);
            $application = $container->make(Application::class);
            $driver = (string) $config->get('session.driver', 'file');

            if ($driver !== 'file') {
                throw new LogicException(sprintf('Unsupported session driver "%s".', $driver));
            }

            $path = (string) $config->get(
                'session.files',
                $application->rootPath() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'sessions',
            );

            return new FileSessionDriver($path);
        });

        $this->app->singleton(SessionManager::class, static function (Container $container): SessionManager {
            $config = $container->make(Config::class);

            return new SessionManager(
                $container->make(SessionDriver::class),
                (string) $config->get('session.cookie', 'ract_session'),
                (int) $config->get('session.lifetime', 120),
                (string) $config->get('session.path', '/'),
                is_string($config->get('session.domain')) ? $config->get('session.domain') : null,
                (bool) $config->get('session.secure', false),
                (bool) $config->get('session.http_only', true),
                (string) $config->get('session.same_site', 'Lax'),
            );
        });
    }
}
