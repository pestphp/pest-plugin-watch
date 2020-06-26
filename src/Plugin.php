<?php

declare(strict_types=1);

namespace Pest\Watch;

use Pest\Contracts\Plugins\HandlesArguments;
use React\ChildProcess\Process;
use React\EventLoop\Factory;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Plugin implements HandlesArguments
{
    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function handleArguments(array $originals): array
    {
        if (!in_array('--watch', $originals, true)) {
            return $originals;
        }

        $this->checkFswatchIsAvailable();

        $loop    = Factory::create();
        $watcher = new Watch($loop, 'tests');
        $watcher->run();

        unset($originals[array_search('--watch', $originals, true)]);
        $command = implode(' ', $originals);

        $output  = $this->output;

        $watcher->on('change', static function () use ($command, $output): void {
            $loop = Factory::create();
            $process = new Process($command);
            $process->start($loop);
            // @phpstan-ignore-next-line
            $process->stdout->on('data', function ($line) use ($output): void {
                $output->write($line);
            });
            $process->on('exit', function () use ($output): void {
                $output->writeln('');
            });
            $loop->run();
        });

        $watcher->emit('change');

        $loop->run();

        exit(0);
    }

    private function checkFswatchIsAvailable(): void
    {
        exec('fswatch 2>&1', $output);

        if (strpos(implode(' ', $output), 'command not found') === false) {
            return;
        }

        $this->output->writeln(sprintf(
            "\n  <fg=white;bg=red;options=bold> ERROR </> fswatch was not found.</>",
        ));

        $this->output->writeln(sprintf(
            "\n  Install it from: %s",
            'https://github.com/emcrisostomo/fswatch#getting-fswatch',
        ));

        exit(1);
    }
}
