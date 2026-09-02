<?php

declare(strict_types=1);

namespace Tests\Console;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ract\Application;
use Ract\Config\Config;
use Ract\Console\BufferedOutput;
use Ract\Console\Kernel;
use Ract\Console\Scheduling\CronExpression;
use Ract\Routing\Router;
use Ract\View\View;

final class ScheduleTest extends TestCase
{
    private Kernel $kernel;

    protected function setUp(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ract-schedule-' . bin2hex(random_bytes(6));
        mkdir($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views', 0775, true);
        $app = new Application(
            $root,
            new Config(['app' => ['debug' => false, 'timezone' => 'UTC']]),
            new Router(),
            new View($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views'),
        );
        $this->kernel = new Kernel($app);
    }

    public function testCronExpressionsMatchRangesStepsAndWeekdays(): void
    {
        $expression = new CronExpression('*/15 9-17 * * 1-5');

        self::assertTrue($expression->isDue(new DateTimeImmutable('2026-03-02 09:30:00')));
        self::assertFalse($expression->isDue(new DateTimeImmutable('2026-03-02 09:31:00')));
        self::assertFalse($expression->isDue(new DateTimeImmutable('2026-03-01 09:30:00')));
    }

    public function testRestrictedSteppedDaysUseDayOfMonthOrDayOfWeekSemantics(): void
    {
        $expression = new CronExpression('0 0 */2 * 1');

        self::assertTrue($expression->isDue(new DateTimeImmutable('2026-03-03 00:00:00')));
        self::assertTrue($expression->isDue(new DateTimeImmutable('2026-03-02 00:00:00')));
        self::assertFalse($expression->isDue(new DateTimeImmutable('2026-03-10 00:00:00')));
    }

    public function testInvalidCronExpressionsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('minute');

        new CronExpression('60 * * * *');
    }

    public function testScheduleRunExecutesOnlyDueEvents(): void
    {
        $runs = [];
        $schedule = $this->kernel->scheduler();
        $schedule->call(static function () use (&$runs): void {
            $runs[] = 'due';
        })->everyMinute()->name('due callback');
        $schedule->call(static function () use (&$runs): void {
            $runs[] = 'not due';
        })->cron('0 0 31 2 *')->name('impossible callback');
        $output = new BufferedOutput();

        self::assertSame(0, $this->kernel->handle(['ract', 'schedule:run'], $output));
        self::assertSame(['due'], $runs);
        self::assertStringContainsString('RUNNING: due callback', $output->contents());
        self::assertStringContainsString('1 scheduled event(s) ran', $output->contents());
    }

    public function testScheduledConsoleCommandsUseTheSameKernel(): void
    {
        $this->kernel->scheduler()->command('routes')->everyMinute();
        $output = new BufferedOutput();

        self::assertSame(0, $this->kernel->handle(['ract', 'schedule:run'], $output));
        self::assertStringContainsString('METHOD', $output->contents());
    }

    public function testScheduleWorkCanRunOneDaemonIteration(): void
    {
        $runs = 0;
        $this->kernel->scheduler()->call(static function () use (&$runs): void {
            $runs++;
        })->everyMinute();

        self::assertSame(0, $this->kernel->handle(['ract', 'schedule:work', '--once'], new BufferedOutput()));
        self::assertSame(1, $runs);
    }
}
