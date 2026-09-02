<?php

declare(strict_types=1);

namespace Ract\Console;

use Ract\Application;
use Ract\Console\Commands\MakeControllerCommand;
use Ract\Console\Commands\MakeCrudCommand;
use Ract\Console\Commands\MakeMigrationCommand;
use Ract\Console\Commands\MakeModelCommand;
use Ract\Console\Commands\MigrateCommand;
use Ract\Console\Commands\MigrateRollbackCommand;
use Ract\Console\Commands\RoutesCommand;
use Ract\Console\Commands\ScheduleRunCommand;
use Ract\Console\Commands\ScheduleWorkCommand;
use Ract\Console\Commands\ServeCommand;
use Ract\Console\Scheduling\Schedule;

class Kernel
{
    private readonly ConsoleApplication $console;

    private readonly Schedule $scheduler;

    public function __construct(protected readonly Application $app)
    {
        $container = $this->app->container();
        $container->instance(CodeGenerator::class, new CodeGenerator($this->app->rootPath()));

        $this->console = new ConsoleApplication($container);
        $this->scheduler = new Schedule($container, $this->console);
        $container->instance(ConsoleApplication::class, $this->console);
        $container->instance(Schedule::class, $this->scheduler);
        $container->instance(self::class, $this);
        $container->instance($this::class, $this);

        foreach ($this->commandClasses() as $command) {
            $this->console->add($command);
        }

        $this->schedule($this->scheduler);
    }

    /** @param list<string> $argv */
    public function handle(array $argv, ?Output $output = null): int
    {
        return $this->console->run($argv, $output);
    }

    /** @param list<string> $arguments */
    public function call(string $command, array $arguments = [], ?Output $output = null): int
    {
        return $this->console->runCommand($command, $arguments, $output);
    }

    public function scheduler(): Schedule
    {
        return $this->scheduler;
    }

    protected function schedule(Schedule $schedule): void
    {
    }

    /** @param class-string<Command> $command */
    protected function addCommand(string $command): void
    {
        $this->console->add($command);
    }

    /** @return list<class-string<Command>> */
    protected function commandClasses(): array
    {
        return [
            MakeControllerCommand::class,
            MakeCrudCommand::class,
            MakeMigrationCommand::class,
            MakeModelCommand::class,
            MigrateCommand::class,
            MigrateRollbackCommand::class,
            RoutesCommand::class,
            ScheduleRunCommand::class,
            ScheduleWorkCommand::class,
            ServeCommand::class,
        ];
    }
}
