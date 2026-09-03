<?php

declare(strict_types=1);

namespace Ract\Session;

use JsonException;
use RuntimeException;

final class FileSessionDriver implements SessionDriver
{
    public function __construct(private readonly string $directory)
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException(sprintf('Unable to create session directory "%s".', $this->directory));
        }

        if (!is_writable($this->directory)) {
            throw new RuntimeException(sprintf('Session directory "%s" is not writable.', $this->directory));
        }
    }

    public function exists(string $id): bool
    {
        $record = $this->readRecord($id);

        if ($record === null) {
            return false;
        }

        if ($record['expires_at'] <= time()) {
            $this->destroy($id);

            return false;
        }

        return true;
    }

    public function read(string $id): array
    {
        $record = $this->readRecord($id);

        if ($record === null || $record['expires_at'] <= time()) {
            if ($record !== null) {
                $this->destroy($id);
            }

            return [];
        }

        return $record['payload'];
    }

    public function write(string $id, array $payload, int $expiresAt): void
    {
        $encoded = json_encode(
            ['expires_at' => $expiresAt, 'payload' => $payload],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
        $handle = fopen($this->path($id), 'c+b');

        if ($handle === false) {
            throw new RuntimeException('Unable to open the session file for writing.');
        }

        try {
            if (!flock($handle, LOCK_EX) || !ftruncate($handle, 0) || rewind($handle) === false) {
                throw new RuntimeException('Unable to lock the session file for writing.');
            }

            if (fwrite($handle, $encoded) !== strlen($encoded) || !fflush($handle)) {
                throw new RuntimeException('Unable to write the session file.');
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        @chmod($this->path($id), 0600);
    }

    public function destroy(string $id): void
    {
        $path = $this->path($id);

        if (is_file($path) && !@unlink($path) && is_file($path)) {
            throw new RuntimeException('Unable to remove the session file.');
        }
    }

    public function garbageCollect(int $now): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
            $id = basename($file, '.json');
            $record = $this->readRecord($id);

            if ($record === null || $record['expires_at'] <= $now) {
                $this->destroy($id);
            }
        }
    }

    /** @return array{expires_at: int, payload: array{data?: array<string, mixed>, flash?: list<string>}}|null */
    private function readRecord(string $id): ?array
    {
        $path = $this->path($id);

        if (!is_file($path)) {
            return null;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                return null;
            }

            $contents = stream_get_contents($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        try {
            $record = json_decode((string) $contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($record)
            || !isset($record['expires_at'], $record['payload'])
            || !is_int($record['expires_at'])
            || !is_array($record['payload'])
        ) {
            return null;
        }

        $data = $record['payload']['data'] ?? [];
        $flash = $record['payload']['flash'] ?? [];

        if (!is_array($data) || !is_array($flash) || array_filter($flash, 'is_string') !== $flash) {
            return null;
        }

        return [
            'expires_at' => $record['expires_at'],
            'payload' => ['data' => $data, 'flash' => array_values($flash)],
        ];
    }

    private function path(string $id): string
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $id) !== 1) {
            throw new RuntimeException('Invalid session identifier.');
        }

        return rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $id . '.json';
    }
}
