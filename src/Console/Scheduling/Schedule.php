<?php

declare(strict_types=1);

namespace Ract\Console\Scheduling;

use DateTimeInterface;
use Ract\Console\ConsoleApplication;
use Ract\Console\Output;
use Ract\Container\Container;

final class Schedule
{
    /** @var list<ScheduledEvent> */
    private array $events = [];

    public function __construct(
        private readonly Container $container,
        private readonly ConsoleApplication $console,
    ) {
    }

    /**
     * @param callable|array{class-string|object, string} $callback
     * @param array<int|string, mixed> $parameters
     */
    public function call(callable|array $callback, array $parameters = []): ScheduledEvent
    {
        return $this->events[] = new ScheduledEvent(
            function (Output $output) use ($callback, $parameters): int {
                $result = $this->container->call($callback, $parameters);

                return is_int($result) ? $result : 0;
            },
        );
    }

    /** @param list<string> $arguments */
    public function command(string $command, array $arguments = []): ScheduledEvent
    {
        return $this->events[] = new ScheduledEvent(
            fn (Output $output): int => $this->console->runCommand($command, $arguments, $output),
            'Command: ' . $command,
        );
    }

    /** @return list<ScheduledEvent> */
    public function events(): array
    {
        return $this->events;
    }

    /** @return list<ScheduledEvent> */
    public function dueEvents(DateTimeInterface $date): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (ScheduledEvent $event): bool => $event->isDue($date),
        ));
    }
}
