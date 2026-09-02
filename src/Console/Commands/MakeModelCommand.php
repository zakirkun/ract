<?php

declare(strict_types=1);

namespace Ract\Console\Commands;

use InvalidArgumentException;
use Ract\Console\CodeGenerator;
use Ract\Console\Command;
use Ract\Console\Input;
use Ract\Console\Output;

final class MakeModelCommand extends Command
{
    public const NAME = 'make:model';

    public const DESCRIPTION = 'Create an active-record model';

    public function __construct(private readonly CodeGenerator $generator)
    {
    }

    public function handle(Input $input, Output $output): int
    {
        $name = $input->argument(0);

        if ($name === null) {
            throw new InvalidArgumentException('Model name is required.');
        }

        $fields = (string) $input->option('fields', '');
        $path = $this->generator->model(
            $name,
            $this->generator->parseFields($fields),
            $input->hasOption('force'),
        );
        $output->writeln('CREATED: ' . $path);

        return 0;
    }
}
