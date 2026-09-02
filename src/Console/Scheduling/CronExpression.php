<?php

declare(strict_types=1);

namespace Ract\Console\Scheduling;

use DateTimeInterface;
use InvalidArgumentException;

final class CronExpression
{
    /** @var list<string> */
    private array $fields;

    public function __construct(private readonly string $expression)
    {
        $fields = preg_split('/\s+/', trim($this->expression)) ?: [];

        if (count($fields) !== 5) {
            throw new InvalidArgumentException('A cron expression must contain five fields.');
        }

        $definitions = [
            ['name' => 'minute', 'minimum' => 0, 'maximum' => 59],
            ['name' => 'hour', 'minimum' => 0, 'maximum' => 23],
            ['name' => 'day of month', 'minimum' => 1, 'maximum' => 31],
            ['name' => 'month', 'minimum' => 1, 'maximum' => 12],
            ['name' => 'day of week', 'minimum' => 0, 'maximum' => 7],
        ];

        foreach ($fields as $index => $field) {
            $definition = $definitions[$index];
            $this->validateField($field, $definition['name'], $definition['minimum'], $definition['maximum']);
        }

        /** @var list<string> $fields */
        $this->fields = $fields;
    }

    public function __toString(): string
    {
        return $this->expression;
    }

    public function isDue(DateTimeInterface $date): bool
    {
        $dayOfMonthMatches = $this->matches($this->fields[2], (int) $date->format('j'), 1, 31);
        $dayOfWeekMatches = $this->matches($this->fields[4], (int) $date->format('w'), 0, 7, true);
        $dayMatches = $this->isWildcard($this->fields[2]) || $this->isWildcard($this->fields[4])
            ? $dayOfMonthMatches && $dayOfWeekMatches
            : $dayOfMonthMatches || $dayOfWeekMatches;

        return $this->matches($this->fields[0], (int) $date->format('i'), 0, 59)
            && $this->matches($this->fields[1], (int) $date->format('G'), 0, 23)
            && $dayMatches
            && $this->matches($this->fields[3], (int) $date->format('n'), 1, 12);
    }

    private function validateField(string $field, string $name, int $minimum, int $maximum): void
    {
        if ($field === '') {
            throw new InvalidArgumentException(sprintf('The cron %s field cannot be empty.', $name));
        }

        foreach (explode(',', $field) as $part) {
            $segments = explode('/', $part);

            if (count($segments) > 2 || $segments[0] === '') {
                throw new InvalidArgumentException(sprintf('The cron %s field "%s" is invalid.', $name, $field));
            }

            if (isset($segments[1])) {
                $step = $this->integer($segments[1], $name, $field);

                if ($step < 1 || $step > ($maximum - $minimum + 1)) {
                    throw new InvalidArgumentException(sprintf('The cron %s step in "%s" is out of range.', $name, $field));
                }
            }

            if ($segments[0] === '*') {
                continue;
            }

            $range = explode('-', $segments[0]);

            if (count($range) > 2) {
                throw new InvalidArgumentException(sprintf('The cron %s field "%s" is invalid.', $name, $field));
            }

            $start = $this->integer($range[0], $name, $field);
            $end = isset($range[1]) ? $this->integer($range[1], $name, $field) : $start;

            if ($start < $minimum || $end > $maximum || $start > $end) {
                throw new InvalidArgumentException(sprintf('The cron %s field "%s" is out of range.', $name, $field));
            }

            if (isset($segments[1]) && count($range) === 1) {
                throw new InvalidArgumentException(sprintf('The cron %s field "%s" cannot step a single value.', $name, $field));
            }
        }
    }

    private function integer(string $value, string $name, string $field): int
    {
        if (preg_match('/^\d+$/D', $value) !== 1) {
            throw new InvalidArgumentException(sprintf('The cron %s field "%s" is invalid.', $name, $field));
        }

        return (int) $value;
    }

    private function matches(
        string $field,
        int $value,
        int $minimum,
        int $maximum,
        bool $sundayAlias = false,
    ): bool {
        foreach (explode(',', $field) as $part) {
            [$range, $stepValue] = array_pad(explode('/', $part, 2), 2, '1');
            $step = (int) $stepValue;

            if ($range === '*') {
                $start = $minimum;
                $end = $maximum;
            } elseif (str_contains($range, '-')) {
                [$start, $end] = array_map('intval', explode('-', $range, 2));
            } else {
                $start = (int) $range;
                $end = $start;
            }

            for ($candidate = $start; $candidate <= $end; $candidate += $step) {
                $normalized = $sundayAlias && $candidate === 7 ? 0 : $candidate;

                if ($normalized === $value) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isWildcard(string $field): bool
    {
        return $field === '*';
    }
}
