<?php

declare(strict_types=1);

namespace Ract\Session;

interface SessionDriver
{
    public function exists(string $id): bool;

    /** @return array{data?: array<string, mixed>, flash?: list<string>} */
    public function read(string $id): array;

    /** @param array{data: array<string, mixed>, flash: list<string>} $payload */
    public function write(string $id, array $payload, int $expiresAt): void;

    public function destroy(string $id): void;

    public function garbageCollect(int $now): void;
}
