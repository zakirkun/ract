<?php

declare(strict_types=1);

namespace Ract\Console\Commands;

use InvalidArgumentException;
use Ract\Console\Command;
use Ract\Console\ConsoleApplication;
use Ract\Console\Input;
use Ract\Console\Output;

final class ScheduleWorkCommand extends Command
{
    public const NAME = 'schedule:work';

    public const DESCRIPTION = 'Run the scheduler continuously';

    public function __construct(private readonly ConsoleApplication $console)
    {
    }

    public function handle(Input $input, Output $output): int
    {
        if ($input->hasOption('once')) {
            return $this->console->runCommand('schedule:run', [], $output);
        }

        $sleepOption = $input->option('sleep', '1');

        if (!is_string($sleepOption) || filter_var($sleepOption, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('The --sleep option must be an integer from 1 to 60.');
        }

        $sleep = (int) $sleepOption;

        if ($sleep < 1 || $sleep > 60) {
            throw new InvalidArgumentException('The --sleep option must be an integer from 1 to 60.');
        }

        $output->writeln('Scheduler worker started. Press Ctrl+C to stop.');
        $lastMinute = '';

        while (true) {
            $minute = date('Y-m-d H:i');

            if ($minute !== $lastMinute) {
                $this->console->runCommand('schedule:run', [], $output);
                $lastMinute = $minute;
            }

            sleep($sleep);
        }
    }
}
