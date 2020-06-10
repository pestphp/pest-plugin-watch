<?php

declare(strict_types=1);

namespace Pest\Watch;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;

/**
 * @internal
 */
final class Watch implements EventEmitterInterface
{
    use EventEmitterTrait;

    /**
     * @var \React\ChildProcess\Process|mixed
     */
    public $process;

    /**
     * @var string
     */
    public $command;

    /**
     * @var \React\EventLoop\LoopInterface|mixed
     */
    public $loop;

    /**
     * Starts a new watch on ths given folder.
     *
     * @return void
     */
    public function __construct(LoopInterface $loop, string $folder)
    {
        $this->loop    = $loop;
        $this->command = sprintf('fswatch --recursive %s', $folder);
    }

    /**
     * Check if fswatch is available.
     */
    public function isAvailable(): bool
    {
        exec('fswatch 2>&1', $output);

        return strpos(implode(' ', $output), 'command not found') === false;
    }

    /**
     * Run the ReactPHP loop function with the change
     * event listener.
     */
    public function run(): void
    {
        if (!$this->isAvailable()) {
            throw new \LogicException('fswatch is required');
        }

        $this->process = new Process($this->command);

        $this->process->start($this->loop);

        // @phpstan-ignore-next-line
        $this->process->stderr->on('data', function ($data): void {
            $this->emit('error', [$data]);
        });

        // @phpstan-ignore-next-line
        $this->process->stdout->on('data', function ($data): void {
            $this->emit('change', [$data]);
        });
    }
}
