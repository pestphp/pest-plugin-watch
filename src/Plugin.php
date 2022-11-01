<?php

declare(strict_types=1);

namespace Pest\Watch;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Support\Str;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use function Termwind\render;
use function Termwind\terminal;

/**
 * @internal
 */
final class Plugin implements HandlesArguments
{
    public const WATCHED_DIRECTORIES = ['app', 'src', 'tests'];

    private const WATCH_OPTION = 'watch';

    private string $command = 'vendor/bin/pest';

    public Process $pestProcess;

    /** @var array<int, string> */
    private array|string $watchedDirectories;

    public function __construct(
        private OutputInterface $output
    ) {
        // remove non-existing directories from watched directories
        $this->watchedDirectories = array_filter(self::WATCHED_DIRECTORIES, fn ($directory) => is_dir($directory));
    }

    public function handleArguments(array $originals): array
    {
        if (!$this->userWantsToWatch($originals)) {
            return $originals;
        }

        $this->info('Watching for changes...');

        // dd('end', $this->getCommand());
        $processStarted = $this->startProcess();

        // if the process failed to start, exit
        if (!$processStarted) {
            exit(1);
        }

        $this->listenForChanges();
    }

    private function userWantsToWatch(array $originals): bool
    {
        $arguments = array_merge([''], array_values(array_filter($originals, function ($original): bool {
            return $original === sprintf('--%s', self::WATCH_OPTION) || Str::startsWith($original, sprintf('--%s=', self::WATCH_OPTION));
        })));

        $originals = array_flip($originals);
        foreach ($arguments as $argument) {
            unset($originals[$argument]);
        }

        $inputs   = [];
        $inputs[] = new InputOption(self::WATCH_OPTION, null, InputOption::VALUE_OPTIONAL, '', true);

        $input = new ArgvInput($arguments, new InputDefinition($inputs));

        if (!$input->hasParameterOption(sprintf('--%s', self::WATCH_OPTION))) {
            return false;
        }

        // set the watched directories
        if ($input->getOption(self::WATCH_OPTION) !== null) {
            /* @phpstan-ignore-next-line */
            $this->watchedDirectories = explode(',', $input->getOption(self::WATCH_OPTION));
        }

        // set command to run
        $this->setCommand(implode(' ', array_flip($originals)));

        return true;
    }

    private function listenForChanges(): self
    {
        \Spatie\Watcher\Watch::paths(...$this->watchedDirectories)
            ->onAnyChange(function (string $event, string $path) {
                if ($this->changedPathShouldRestartPest($path)) {
                    $this->restartProcess();
                }
            })
            ->start();

        return $this;
    }

    private function startProcess(): bool
    {
        terminal()->clear();

        $this->pestProcess = Process::fromShellCommandline($this->getCommand());

        $this->pestProcess->setTty(true)->setTimeout(null);

        $this->pestProcess->start(fn ($type, $output) => $this->output->write($output));

        sleep(1);

        return !$this->pestProcess->isTerminated();
    }

    private function restartProcess(): self
    {
        $this->info('Change detected! Restarting Pest...');

        $this->pestProcess->stop(0);

        $this->startProcess();

        return $this;
    }

    private function changedPathShouldRestartPest(string $path): bool
    {
        if ($this->isPhpFile($path)) {
            return true;
        }

        foreach ($this->watchedDirectories as $configuredPath) {
            if ($path === $configuredPath) {
                return true;
            }
        }

        return false;
    }

    private function isPhpFile(string $path): bool
    {
        return str_ends_with(strtolower($path), '.php');
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function setCommand(string $command): void
    {
        $this->command = $command;
    }

    private function info(string $message): void
    {
        $html = "<div class='mx-2 mb-1 mt-1'><span class='px-1 bg-blue text-white uppercase'>info</span><span class='ml-1'>{$message}</span></div>";

        render($html);
    }
}
