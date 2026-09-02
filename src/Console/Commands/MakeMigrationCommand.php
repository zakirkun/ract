<?php

declare(strict_types=1);

namespace Ract\Console\Commands;

use InvalidArgumentException;
use Ract\Console\CodeGenerator;
use Ract\Console\Command;
use Ract\Console\Input;
use Ract\Console\Output;

final class MakeMigrationCommand extends Command
{
    public const NAME = 'make:migration';

    public const DESCRIPTION = 'Create a database migration';

    public function __construct(private readonly CodeGenerator $generator)
    {
    }

    public function handle(Input $input, Output $output): int
    {
        $name = $input->argument(0);

        if ($name === null) {
            throw new InvalidArgumentException('Migration name is required.');
        }

        $table = $input->option('table');

        if (!is_string($table) && preg_match('/^create_(.+)_table$/', $name, $matches) === 1) {
            $table = $matches[1];
        }

        if (!is_string($table) || $table === '') {
            throw new InvalidArgumentException('Migration table is required through --table=<name>.');
        }

        $fields = (string) $input->option('fields', '');
        $path = $this->generator->migration(
            $name,
            $table,
            $this->generator->parseFields($fields),
            $input->hasOption('force'),
        );
        $output->writeln('CREATED: ' . $path);

        return 0;
    }
}
