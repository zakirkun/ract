<?php

declare(strict_types=1);

namespace Ract\Console;

interface Output
{
    public function write(string $message): void;

    public function writeln(string $message = ''): void;
}
