<?php

declare(strict_types=1);

namespace Ract\Console\Commands;

use DateTimeImmutable;
use Ract\Console\Command;
use Ract\Console\Input;
use Ract\Console\Output;
use Ract\Console\Scheduling\Schedule;
use Throwable;

final class ScheduleRunCommand extends Command
{
    public const NAME = 'schedule:run';

    public const DESCRIPTION = 'Run scheduled events that are due';

    public function __construct(private readonly Schedule $schedule)
    {
    }

    public function handle(Input $input, Output $output): int
    {
        $events = $this->schedule->dueEvents(new DateTimeImmutable());

        if ($events === []) {
            $output->writeln('No scheduled events are due.');

            return 0;
        }

        $failed = 0;

        foreach ($events as $event) {
            $output->writeln('RUNNING: ' . $event->description());

            try {
                $exitCode = $event->run($output);

                if ($exitCode !== 0) {
                    $failed++;
                    $output->writeln(sprintf('FAILED: %s (exit code %d)', $event->description(), $exitCode));
                }
            } catch (Throwable $exception) {
                $failed++;
                $output->writeln(sprintf('FAILED: %s (%s)', $event->description(), $exception->getMessage()));
            }
        }

        $output->writeln(sprintf(
            '%d scheduled event(s) ran; %d failed.',
            count($events),
            $failed,
        ));

        return $failed === 0 ? 0 : 1;
    }
}
