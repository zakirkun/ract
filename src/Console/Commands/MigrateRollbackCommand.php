<?php

declare(strict_types=1);

namespace Ract\Console\Commands;

use InvalidArgumentException;
use Ract\Console\Command;
use Ract\Console\Input;
use Ract\Console\Output;
use Ract\Database\Migrations\Migrator;

final class MigrateRollbackCommand extends Command
{
    public const NAME = 'migrate:rollback';

    public const DESCRIPTION = 'Roll back the latest migration batch';

    public function __construct(private readonly Migrator $migrator)
    {
    }

    public function handle(Input $input, Output $output): int
    {
        $stepsOption = $input->option('step');
        $steps = null;

        if ($stepsOption !== null) {
            if ($stepsOption === true || filter_var($stepsOption, FILTER_VALIDATE_INT) === false) {
                throw new InvalidArgumentException('The --step option must be a positive integer.');
            }

            $steps = (int) $stepsOption;
        }

        $migrations = $this->migrator->rollback($steps);

        if ($migrations === []) {
            $output->writeln('Nothing to roll back.');

            return 0;
        }

        foreach ($migrations as $migration) {
            $output->writeln('ROLLED BACK: ' . $migration);
        }

        return 0;
    }
}
