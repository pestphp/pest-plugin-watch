<?php

declare(strict_types=1);

namespace Pest\Watch;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Support\Str;
use React\ChildProcess\Process;
use React\EventLoop\Factory;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Plugin implements HandlesArguments
{
    public const WATCHED_DIRECTORIES = ['app', 'src', 'tests'];

    private const WATCH_DIRS_OPTION = 'watch-dirs';

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

        $watchedDirectories = self::WATCHED_DIRECTORIES;

        if (($additionalWatchDirectories = $this->watchDirectories($originals)) !== []) {
            foreach ($additionalWatchDirectories as $key => $directories) {
                $watchedDirectories = array_merge(
                    $watchedDirectories,
                    explode(',', str_replace(sprintf('--%s=', self::WATCH_DIRS_OPTION), '', $directories)),
                );

                unset($originals[$key]);
            }
        }

        $loop    = Factory::create();
        $watcher = new Watch($loop, $watchedDirectories);
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

    /**
     * @param array<int, string> $originals
     *
     * @return array<int, string>
     */
    private function watchDirectories(array $originals): array
    {
        return array_filter($originals, static function ($value): bool {
            return Str::startsWith($value, sprintf('--%s', self::WATCH_DIRS_OPTION));
        });
    }
}
