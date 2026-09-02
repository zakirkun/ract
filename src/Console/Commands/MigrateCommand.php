<?php

declare(strict_types=1);

namespace Ract\Console\Commands;

use Ract\Console\Command;
use Ract\Console\Input;
use Ract\Console\Output;
use Ract\Database\Migrations\Migrator;

final class MigrateCommand extends Command
{
    public const NAME = 'migrate';

    public const DESCRIPTION = 'Run pending database migrations';

    public function __construct(private readonly Migrator $migrator)
    {
    }

    public function handle(Input $input, Output $output): int
    {
        $migrations = $this->migrator->migrate();

        if ($migrations === []) {
            $output->writeln('Nothing to migrate.');

            return 0;
        }

        foreach ($migrations as $migration) {
            $output->writeln('MIGRATED: ' . $migration);
        }

        return 0;
    }
}
