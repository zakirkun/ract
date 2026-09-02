<?php

declare(strict_types=1);

namespace App\Console;

use Ract\Console\Kernel as ConsoleKernel;
use Ract\Console\Scheduling\Schedule;

final class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
    }
}
