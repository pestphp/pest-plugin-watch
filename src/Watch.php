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
     * @var Process|mixed
     */
    public $process;

    /**
     * @var string
     */
    public $command;

    /**
     * @var LoopInterface
     */
    public $loop;

    /**
     * Starts a new watch on ths given folder.
     *
     * @param  array<int, string>  $folders
     * @return void
     */
    public function __construct(LoopInterface $loop, array $folders)
    {
        $this->loop = $loop;
        $this->command = sprintf('fswatch --recursive --follow-links --event Updated --event Created --event Removed %s', implode(' ', $folders));
    }

    /**
     * Run the ReactPHP loop function with the change
     * event listener.
     */
    public function run(): void
    {
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
