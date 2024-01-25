<?php

declare(strict_types=1);

namespace Pest\Watch;

use Pest\Contracts\Plugins\HandlesOriginalArguments;
use Pest\Support\Str;
use React\ChildProcess\Process;
use React\EventLoop\Factory;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

/**
 * @internal
 */
final class Plugin implements HandlesOriginalArguments
{
    public const WATCHED_DIRECTORIES = ['app', 'src', 'tests'];

    private const WATCH_OPTION = 'watch';

    /**
     * @var OutputInterface
     */
    private $output;

    /** @var array<int, string> */
    private $watchedDirectories = self::WATCHED_DIRECTORIES;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function handleOriginalArguments(array $originalArguments): void
    {
        $arguments = array_merge([''], array_values(array_filter($originalArguments, function ($original): bool {
            return $original === sprintf('--%s', self::WATCH_OPTION) || Str::startsWith($original, sprintf('--%s=', self::WATCH_OPTION));
        })));

        $originalArguments = array_flip($originalArguments);
        foreach ($arguments as $argument) {
            unset($originalArguments[$argument]);
        }
        $originalArguments = array_flip($originalArguments);

        $inputs = [];
        $inputs[] = new InputOption(self::WATCH_OPTION, null, InputOption::VALUE_OPTIONAL, '', true);

        $input = new ArgvInput($arguments, new InputDefinition($inputs));

        if (! $input->hasParameterOption(sprintf('--%s', self::WATCH_OPTION))) {
            return;
        }

        $this->checkFswatchIsAvailable();

        if ($input->getOption(self::WATCH_OPTION) !== null) {
            /* @phpstan-ignore-next-line */
            $this->watchedDirectories = explode(',', $input->getOption(self::WATCH_OPTION));
        }

        $loop = Factory::create();
        $watcher = new Watch($loop, $this->watchedDirectories);
        $watcher->run();

        $command = implode(' ', [...$originalArguments, '--colors=always']);

        $output = $this->output;

        $watcher->on('change', static function () use ($command, $output): void {
            $loop = Factory::create();

            $terminal = new Terminal;

            $process = new Process($command, null, [
                'COLUMNS' => $terminal->getWidth(),
                'LINES' => $terminal->getHeight(),
                ...getenv(),
            ]);

            $process->start($loop);

            $output->write("\033\143");

            // @phpstan-ignore-next-line
            $process->stdout->on('data', function ($line) use ($output): void {
                $output->write($line);
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
