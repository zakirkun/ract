<?php

declare(strict_types=1);

namespace Ract\Console\Commands;

use Ract\Application;
use Ract\Console\Command;
use Ract\Console\Input;
use Ract\Console\Output;

final class RoutesCommand extends Command
{
    public const NAME = 'routes';

    public const DESCRIPTION = 'List registered application routes';

    public function __construct(private readonly Application $app)
    {
    }

    public function handle(Input $input, Output $output): int
    {
        $output->writeln(sprintf('%-20s %-30s %s', 'METHOD', 'URI', 'NAME'));
        $output->writeln(sprintf('%-20s %-30s %s', str_repeat('-', 20), str_repeat('-', 30), str_repeat('-', 20)));

        foreach ($this->app->router()->routes() as $route) {
            $output->writeln(sprintf(
                '%-20s %-30s %s',
                implode('|', $route->methods()),
                $route->uri(),
                $route->routeName() ?? '-',
            ));
        }

        return 0;
    }
}
