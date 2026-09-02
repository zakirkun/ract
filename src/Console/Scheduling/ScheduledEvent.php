<?php

declare(strict_types=1);

namespace Ract\Console\Scheduling;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Ract\Console\Output;

final class ScheduledEvent
{
    private CronExpression $expression;

    private ?DateTimeZone $timezone = null;

    private string $summary;

    /** @param Closure(Output): int $runner */
    public function __construct(private readonly Closure $runner, string $summary = 'Callback')
    {
        $this->expression = new CronExpression('* * * * *');
        $this->summary = $summary;
    }

    public function cron(string $expression): self
    {
        $this->expression = new CronExpression($expression);

        return $this;
    }

    public function everyMinute(): self
    {
        return $this->cron('* * * * *');
    }

    public function everyFiveMinutes(): self
    {
        return $this->cron('*/5 * * * *');
    }

    public function everyTenMinutes(): self
    {
        return $this->cron('*/10 * * * *');
    }

    public function everyFifteenMinutes(): self
    {
        return $this->cron('*/15 * * * *');
    }

    public function everyThirtyMinutes(): self
    {
        return $this->cron('*/30 * * * *');
    }

    public function hourly(): self
    {
        return $this->cron('0 * * * *');
    }

    public function daily(): self
    {
        return $this->cron('0 0 * * *');
    }

    public function dailyAt(string $time): self
    {
        [$hour, $minute] = $this->parseTime($time);

        return $this->cron(sprintf('%d %d * * *', $minute, $hour));
    }

    public function weekly(): self
    {
        return $this->cron('0 0 * * 0');
    }

    public function weeklyOn(int $dayOfWeek, string $time = '00:00'): self
    {
        if ($dayOfWeek < 0 || $dayOfWeek > 7) {
            throw new InvalidArgumentException('The schedule day of week must be between 0 and 7.');
        }

        [$hour, $minute] = $this->parseTime($time);

        return $this->cron(sprintf('%d %d * * %d', $minute, $hour, $dayOfWeek));
    }

    public function weekdays(): self
    {
        return $this->cron('0 0 * * 1-5');
    }

    public function monthly(): self
    {
        return $this->cron('0 0 1 * *');
    }

    public function monthlyOn(int $dayOfMonth, string $time = '00:00'): self
    {
        if ($dayOfMonth < 1 || $dayOfMonth > 31) {
            throw new InvalidArgumentException('The schedule day of month must be between 1 and 31.');
        }

        [$hour, $minute] = $this->parseTime($time);

        return $this->cron(sprintf('%d %d %d * *', $minute, $hour, $dayOfMonth));
    }

    public function timezone(string $timezone): self
    {
        try {
            $this->timezone = new DateTimeZone($timezone);
        } catch (\Exception $exception) {
            throw new InvalidArgumentException(sprintf('Invalid schedule timezone "%s".', $timezone), 0, $exception);
        }

        return $this;
    }

    public function name(string $summary): self
    {
        $summary = trim($summary);

        if ($summary === '') {
            throw new InvalidArgumentException('A scheduled event name cannot be empty.');
        }

        $this->summary = $summary;

        return $this;
    }

    public function description(): string
    {
        return $this->summary;
    }

    public function expression(): string
    {
        return (string) $this->expression;
    }

    public function isDue(DateTimeInterface $date): bool
    {
        $date = DateTimeImmutable::createFromInterface($date);

        if ($this->timezone !== null) {
            $date = $date->setTimezone($this->timezone);
        }

        return $this->expression->isDue($date);
    }

    public function run(Output $output): int
    {
        return ($this->runner)($output);
    }

    /** @return array{int, int} */
    private function parseTime(string $time): array
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/D', $time, $matches) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid schedule time "%s"; expected HH:MM.', $time));
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour > 23 || $minute > 59) {
            throw new InvalidArgumentException(sprintf('Invalid schedule time "%s"; expected HH:MM.', $time));
        }

        return [$hour, $minute];
    }
}
