<?php

declare(strict_types=1);

namespace Ract\Console;

abstract class Command
{
    public const NAME = '';

    public const DESCRIPTION = '';

    abstract public function handle(Input $input, Output $output): int;
}
