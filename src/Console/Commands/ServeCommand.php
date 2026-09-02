<?php

declare(strict_types=1);

namespace Ract\Console\Commands;

use InvalidArgumentException;
use Ract\Application;
use Ract\Console\Command;
use Ract\Console\Input;
use Ract\Console\Output;

final class ServeCommand extends Command
{
    public const NAME = 'serve';

    public const DESCRIPTION = 'Start the PHP development server';

    public function __construct(private readonly Application $app)
    {
    }

    public function handle(Input $input, Output $output): int
    {
        $host = $input->argument(0, 'localhost') ?? 'localhost';
        $portValue = $input->argument(1, '8080') ?? '8080';
        $port = filter_var($portValue, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);
        $hasOpeningBracket = str_starts_with($host, '[');
        $hasClosingBracket = str_ends_with($host, ']');

        if ($hasOpeningBracket && $hasClosingBracket) {
            $host = substr($host, 1, -1);
        }

        $isIpv6 = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        $isValidHost = $isIpv6
            || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;

        if ($port === false || $host === '' || $hasOpeningBracket !== $hasClosingBracket || !$isValidHost) {
            throw new InvalidArgumentException('Invalid host or port.');
        }

        $displayHost = $isIpv6 ? '[' . $host . ']' : $host;
        $address = $displayHost . ':' . $port;
        $publicPath = $this->app->rootPath() . DIRECTORY_SEPARATOR . 'public';
        $routerPath = $publicPath . DIRECTORY_SEPARATOR . 'router.php';
        $output->writeln(sprintf('Ract development server: http://%s', $address));
        $output->writeln('Press Ctrl+C to stop.');
        $serverCommand = sprintf(
            '%s -S %s -t %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($address),
            escapeshellarg($publicPath),
            escapeshellarg($routerPath),
        );
        passthru($serverCommand, $exitCode);

        return $exitCode;
    }
}
