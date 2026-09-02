<?php

declare(strict_types=1);

namespace Ract\Console\Commands;

use InvalidArgumentException;
use Ract\Console\CodeGenerator;
use Ract\Console\Command;
use Ract\Console\Input;
use Ract\Console\Output;

final class MakeCrudCommand extends Command
{
    public const NAME = 'make:crud';

    public const DESCRIPTION = 'Create a model, resource controller, migration, and routes';

    public function __construct(private readonly CodeGenerator $generator)
    {
    }

    public function handle(Input $input, Output $output): int
    {
        $name = $input->argument(0);

        if ($name === null) {
            throw new InvalidArgumentException('CRUD model name is required.');
        }

        $fields = (string) $input->option('fields', '');
        $definitions = $this->generator->parseFields($fields);

        if ($definitions === []) {
            throw new InvalidArgumentException('CRUD fields are required through --fields=name:type,...');
        }

        foreach ($this->generator->crud($name, $definitions, $input->hasOption('force')) as $path) {
            $output->writeln('CREATED: ' . $path);
        }

        return 0;
    }
}
