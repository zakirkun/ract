<?php

declare(strict_types=1);

namespace Ract\Session;

final class Session
{
    /** @var list<string> */
    private array $newFlash = [];

    private ?string $previousId = null;

    /**
     * @param array<string, mixed> $data
     * @param list<string> $oldFlash
     */
    public function __construct(
        private string $id,
        private array $data = [],
        private array $oldFlash = [],
    ) {
    }

    public static function generateId(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function id(): string
    {
        return $this->id;
    }

    public function previousId(): ?string
    {
        return $this->previousId;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data) && $this->data[$key] !== null;
    }

    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->data;
    }

    public function put(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    public function forget(string $key): self
    {
        unset($this->data[$key]);
        $this->oldFlash = array_values(array_diff($this->oldFlash, [$key]));
        $this->newFlash = array_values(array_diff($this->newFlash, [$key]));

        return $this;
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    public function flash(string $key, mixed $value): self
    {
        $this->put($key, $value);

        if (!in_array($key, $this->newFlash, true)) {
            $this->newFlash[] = $key;
        }

        return $this;
    }

    /** @param string|list<string>|null $keys */
    public function keep(string|array|null $keys = null): self
    {
        $keys = $keys === null ? $this->oldFlash : (is_string($keys) ? [$keys] : $keys);

        foreach ($keys as $key) {
            if (in_array($key, $this->oldFlash, true) && !in_array($key, $this->newFlash, true)) {
                $this->newFlash[] = $key;
            }
        }

        return $this;
    }

    public function reflash(): self
    {
        return $this->keep();
    }

    public function regenerate(bool $destroy = false): self
    {
        if ($destroy || $this->previousId === null) {
            $this->previousId = $this->id;
        }

        $this->id = self::generateId();

        return $this;
    }

    public function invalidate(): self
    {
        $this->data = [];
        $this->oldFlash = [];
        $this->newFlash = [];

        return $this->regenerate(true);
    }

    /** @return array{data: array<string, mixed>, flash: list<string>} */
    public function payload(): array
    {
        foreach ($this->oldFlash as $key) {
            if (!in_array($key, $this->newFlash, true)) {
                unset($this->data[$key]);
            }
        }

        return [
            'data' => $this->data,
            'flash' => array_values(array_unique($this->newFlash)),
        ];
    }
}
