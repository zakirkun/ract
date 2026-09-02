<?php

declare(strict_types=1);

namespace Ract\Console;

final class BufferedOutput implements Output
{
    private string $buffer = '';

    public function write(string $message): void
    {
        $this->buffer .= $message;
    }

    public function writeln(string $message = ''): void
    {
        $this->buffer .= $message . PHP_EOL;
    }

    public function contents(): string
    {
        return $this->buffer;
    }

    public function clear(): void
    {
        $this->buffer = '';
    }
}
