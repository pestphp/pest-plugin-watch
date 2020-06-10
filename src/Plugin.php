<?php

declare(strict_types=1);

namespace Pest\Watch;

use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use React\ChildProcess\Process;
use React\EventLoop\Factory;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Plugin implements AddsOutput, HandlesArguments
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
        if (in_array('--watch', $originals)) {
            $loop    = Factory::create();
            $watcher = new Watch('tests', $loop);
            $watcher->run();

            unset($originals[array_search('--watch', $originals)]);
            $pest = implode(' ', $originals);

            $watcher->on('change', static function () use ($pest) {
                $loop = Factory::create();
                $process = new Process($pest);
                $process->start($loop);
                $process->stdout->on('data', function ($line) {
                    echo $line;
                });
                $process->on('exit', function ($exitCode, $termSignal) {
                    echo PHP_EOL;
                });
                $loop->run();
            });

            $loop->run();
        }

        return $originals;
    }

    public function addOutput(int $result): int
    {
        return 0;
    }
}
