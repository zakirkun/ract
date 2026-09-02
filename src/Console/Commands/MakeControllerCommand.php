<?php

declare(strict_types=1);

namespace Ract\Console\Commands;

use InvalidArgumentException;
use Ract\Console\CodeGenerator;
use Ract\Console\Command;
use Ract\Console\Input;
use Ract\Console\Output;

final class MakeControllerCommand extends Command
{
    public const NAME = 'make:controller';

    public const DESCRIPTION = 'Create an application controller';

    public function __construct(private readonly CodeGenerator $generator)
    {
    }

    public function handle(Input $input, Output $output): int
    {
        $name = $input->argument(0);

        if ($name === null) {
            throw new InvalidArgumentException('Controller name is required.');
        }

        $model = $input->option('model');
        $fields = (string) $input->option('fields', '');
        $path = $this->generator->controller(
            $name,
            $input->hasOption('resource'),
            is_string($model) ? $model : null,
            $this->generator->parseFields($fields),
            $input->hasOption('force'),
        );
        $output->writeln('CREATED: ' . $path);

        return 0;
    }
}
