<?php

declare(strict_types=1);

namespace Ract\Validation;

use InvalidArgumentException;

final class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $validated = [];

    private bool $ran = false;

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, string> $messages
     */
    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $messages = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, string> $messages
     */
    public static function make(array $data, array $rules, array $messages = []): self
    {
        return new self($data, $rules, $messages);
    }

    public function passes(): bool
    {
        $this->run();

        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        $this->run();

        return $this->errors;
    }

    /** @return array<string, mixed> */
    public function validate(): array
    {
        $this->run();

        if ($this->errors !== []) {
            throw new ValidationException($this->errors);
        }

        return $this->validated;
    }

    private function run(): void
    {
        if ($this->ran) {
            return;
        }

        $this->ran = true;
        $parsedRules = [];

        foreach ($this->rules as $attribute => $definition) {
            if ($attribute === '') {
                throw new InvalidArgumentException('Validation attribute names cannot be empty.');
            }

            foreach ($this->normalizeRules($definition) as $ruleDefinition) {
                [$rule, $parameters] = $this->parseRule($ruleDefinition);
                $this->assertValidParameters($rule, $parameters);
                $parsedRules[$attribute][] = [$rule, $parameters];
            }
        }

        foreach ($parsedRules as $attribute => $rules) {
            $present = self::has($this->data, $attribute);
            $value = self::get($this->data, $attribute);
            $ruleNames = array_column($rules, 0);

            if (in_array('sometimes', $ruleNames, true) && !$present) {
                continue;
            }

            foreach ($rules as [$rule, $parameters]) {
                if (in_array($rule, ['required', 'present'], true)
                    && !$this->rulePasses($rule, $value, $present, $parameters, $attribute, false)
                ) {
                    $this->errors[$attribute][] = $this->message($attribute, $rule, $parameters);
                }
            }

            if (isset($this->errors[$attribute]) || !$present) {
                continue;
            }

            if (in_array('nullable', $ruleNames, true) && ($value === null || $value === '')) {
                self::set($this->validated, $attribute, $value);
                continue;
            }

            $numericSize = in_array('numeric', $ruleNames, true) || in_array('integer', $ruleNames, true);

            foreach ($rules as [$rule, $parameters]) {
                if (in_array($rule, ['nullable', 'sometimes', 'required', 'present'], true)) {
                    continue;
                }

                if (!$this->rulePasses($rule, $value, true, $parameters, $attribute, $numericSize)) {
                    $this->errors[$attribute][] = $this->message($attribute, $rule, $parameters);
                }
            }

            if (!isset($this->errors[$attribute])) {
                self::set($this->validated, $attribute, $value);
            }
        }
    }

    /** @param list<string> $parameters */
    private function assertValidParameters(string $rule, array $parameters): void
    {
        if (in_array($rule, ['min', 'max'], true)
            && (!isset($parameters[0]) || !is_numeric($parameters[0]))
        ) {
            throw new InvalidArgumentException('The min and max validation rules require a numeric parameter.');
        }

        if ($rule === 'between'
            && (!isset($parameters[0], $parameters[1])
                || !is_numeric($parameters[0])
                || !is_numeric($parameters[1]))
        ) {
            throw new InvalidArgumentException('The between validation rule requires two numeric parameters.');
        }

        if (in_array($rule, ['in', 'not_in', 'same'], true)
            && (!isset($parameters[0]) || $parameters[0] === '')
        ) {
            throw new InvalidArgumentException(sprintf('The %s validation rule requires at least one parameter.', $rule));
        }
    }

    /** @param string|list<string> $definition @return list<string> */
    private function normalizeRules(string|array $definition): array
    {
        $rules = is_string($definition) ? explode('|', $definition) : $definition;
        $normalized = [];

        foreach ($rules as $rule) {
            if (!is_string($rule) || trim($rule) === '') {
                throw new InvalidArgumentException('Validation rules must be non-empty strings.');
            }

            $normalized[] = trim($rule);
        }

        return $normalized;
    }

    /** @return array{string, list<string>} */
    private function parseRule(string $definition): array
    {
        [$rule, $parameterList] = array_pad(explode(':', $definition, 2), 2, null);
        $rule = strtolower(trim($rule));
        $parameters = $parameterList === null ? [] : str_getcsv($parameterList);
        $supported = [
            'array', 'between', 'boolean', 'confirmed', 'date', 'email', 'in', 'integer',
            'max', 'min', 'not_in', 'nullable', 'numeric', 'present', 'required', 'same',
            'sometimes', 'string', 'url',
        ];

        if (!in_array($rule, $supported, true)) {
            throw new InvalidArgumentException(sprintf('Unsupported validation rule "%s".', $rule));
        }

        return [$rule, $parameters];
    }

    /** @param list<string> $parameters */
    private function rulePasses(
        string $rule,
        mixed $value,
        bool $present,
        array $parameters,
        string $attribute,
        bool $numericSize,
    ): bool {
        return match ($rule) {
            'required' => $present && !$this->isEmpty($value),
            'present' => $present,
            'string' => is_string($value),
            'integer' => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/D', $value) === 1),
            'numeric' => is_numeric($value),
            'boolean' => in_array($value, [true, false, 0, 1, '0', '1'], true),
            'array' => is_array($value),
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false,
            'date' => is_string($value) && strtotime($value) !== false,
            'min' => $this->compareSize($value, $parameters, $numericSize, static fn (float $size, float $limit): bool => $size >= $limit),
            'max' => $this->compareSize($value, $parameters, $numericSize, static fn (float $size, float $limit): bool => $size <= $limit),
            'between' => $this->between($value, $parameters, $numericSize),
            'in' => is_scalar($value) && in_array((string) $value, $parameters, true),
            'not_in' => is_scalar($value) && !in_array((string) $value, $parameters, true),
            'same' => self::has($this->data, $parameters[0])
                && $value === self::get($this->data, $parameters[0]),
            'confirmed' => self::has($this->data, $attribute . '_confirmation')
                && $value === self::get($this->data, $attribute . '_confirmation'),
            default => true,
        };
    }

    /** @param list<string> $parameters */
    private function compareSize(mixed $value, array $parameters, bool $numericSize, callable $comparison): bool
    {
        $size = $this->size($value, $numericSize);

        return $size !== null && $comparison($size, (float) $parameters[0]);
    }

    /** @param list<string> $parameters */
    private function between(mixed $value, array $parameters, bool $numericSize): bool
    {
        $size = $this->size($value, $numericSize);

        return $size !== null && $size >= (float) $parameters[0] && $size <= (float) $parameters[1];
    }

    private function size(mixed $value, bool $numericSize): ?float
    {
        if (is_int($value) || is_float($value) || ($numericSize && is_numeric($value))) {
            return (float) $value;
        }

        if (is_string($value)) {
            return (float) (function_exists('mb_strlen') ? mb_strlen($value) : strlen($value));
        }

        return is_array($value) ? (float) count($value) : null;
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || (is_array($value) && $value === []);
    }

    /** @param list<string> $parameters */
    private function message(string $attribute, string $rule, array $parameters): string
    {
        $message = $this->messages[$attribute . '.' . $rule]
            ?? $this->messages[$attribute]
            ?? $this->defaultMessage($rule);
        $replacements = [
            ':attribute' => str_replace(['.', '_'], ' ', $attribute),
            ':value' => isset($parameters[0]) ? $parameters[0] : '',
            ':min' => $parameters[0] ?? '',
            ':max' => $parameters[0] ?? '',
            ':other' => $parameters[0] ?? '',
        ];

        if ($rule === 'between') {
            $replacements[':min'] = $parameters[0] ?? '';
            $replacements[':max'] = $parameters[1] ?? '';
        }

        return strtr($message, $replacements);
    }

    private function defaultMessage(string $rule): string
    {
        return match ($rule) {
            'required' => 'The :attribute field is required.',
            'present' => 'The :attribute field must be present.',
            'string' => 'The :attribute field must be a string.',
            'integer' => 'The :attribute field must be an integer.',
            'numeric' => 'The :attribute field must be numeric.',
            'boolean' => 'The :attribute field must be true or false.',
            'array' => 'The :attribute field must be an array.',
            'email' => 'The :attribute field must be a valid email address.',
            'url' => 'The :attribute field must be a valid URL.',
            'date' => 'The :attribute field must be a valid date.',
            'min' => 'The :attribute field must be at least :min.',
            'max' => 'The :attribute field must not be greater than :max.',
            'between' => 'The :attribute field must be between :min and :max.',
            'in' => 'The selected :attribute is invalid.',
            'not_in' => 'The selected :attribute is invalid.',
            'same' => 'The :attribute field must match :other.',
            'confirmed' => 'The :attribute confirmation does not match.',
            default => 'The :attribute field is invalid.',
        };
    }

    /** @param array<string, mixed> $data */
    private static function has(array $data, string $key): bool
    {
        foreach (explode('.', $key) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return false;
            }

            $data = $data[$segment];
        }

        return true;
    }

    /** @param array<string, mixed> $data */
    private static function get(array $data, string $key): mixed
    {
        foreach (explode('.', $key) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return null;
            }

            $data = $data[$segment];
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private static function set(array &$data, string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $target = &$data;

        foreach ($segments as $index => $segment) {
            if ($index === array_key_last($segments)) {
                $target[$segment] = $value;
                break;
            }

            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        unset($target);
    }
}
