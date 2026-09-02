<?php

declare(strict_types=1);

namespace Ract\Console;

final class ConsoleOutput implements Output
{
    public function write(string $message): void
    {
        echo $message;
    }

    public function writeln(string $message = ''): void
    {
        echo $message . PHP_EOL;
    }
}
