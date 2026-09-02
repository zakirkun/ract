<?php

declare(strict_types=1);

namespace Ract\Console;

use InvalidArgumentException;
use Ract\Container\Container;
use Throwable;

final class ConsoleApplication
{
    /** @var array<string, class-string<Command>> */
    private array $commands = [];

    public function __construct(private readonly Container $container)
    {
    }

    /** @param class-string<Command> $command */
    public function add(string $command): void
    {
        if (!is_subclass_of($command, Command::class) || $command::NAME === '') {
            throw new InvalidArgumentException(sprintf('Console command "%s" is invalid.', $command));
        }

        $this->commands[$command::NAME] = $command;
    }

    /** @param list<string> $argv */
    public function run(array $argv, ?Output $output = null): int
    {
        $output ??= new ConsoleOutput();
        $name = $argv[1] ?? 'help';

        if (in_array($name, ['help', '--help', '-h'], true)) {
            return $this->renderHelp($output);
        }

        return $this->runCommand($name, array_slice($argv, 2), $output);
    }

    /** @param list<string> $arguments */
    public function runCommand(string $name, array $arguments = [], ?Output $output = null): int
    {
        $output ??= new ConsoleOutput();
        $command = $this->commands[$name] ?? null;

        if ($command === null) {
            $output->writeln(sprintf('ERROR: Unknown command "%s".', $name));

            return 1;
        }

        try {
            return $this->container->make($command)->handle(new Input($arguments), $output);
        } catch (Throwable $exception) {
            $output->writeln('ERROR: ' . $exception->getMessage());

            return 1;
        }
    }

    /** @return array<string, class-string<Command>> */
    public function commands(): array
    {
        return $this->commands;
    }

    private function renderHelp(Output $output): int
    {
        $output->writeln('Ract Framework CLI');
        $output->writeln();
        $output->writeln('Usage:');
        $output->writeln('  php bin/ract <command> [arguments] [--options]');
        $output->writeln();
        $output->writeln('Commands:');
        $output->writeln(sprintf('  %-20s %s', 'help', 'Show this help message'));
        ksort($this->commands);

        foreach ($this->commands as $name => $command) {
            $output->writeln(sprintf('  %-20s %s', $name, $command::DESCRIPTION));
        }

        return 0;
    }
}
