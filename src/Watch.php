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
    /**
     * @var \React\ChildProcess\Process|mixed
     */
    public $process;
    /**
     * @var \React\EventLoop\LoopInterface|mixed
     */
    public $loop;
    use EventEmitterTrait;

    /**
     * Starts a new watch on ths given folder.
     *
     * @return void
     */
    public function __construct(string $folder, LoopInterface $loop)
    {
        if (!self::isAvailable()) {
            throw new \LogicException('fswatch is required');
        }

        $this->process = new Process("fswatch --recursive {$folder}");
        $this->loop    = $loop;
    }

    /**
     * Check if fswatch is available.
     */
    public static function isAvailable(): bool
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
        $this->process->start($this->loop);

        $this->process->stderr->on('data', function ($data) {
            $this->emit('error', [$data]);
        });

        $this->process->stdout->on('data', function ($data) {
            $this->emit('change', [$data]);
        });
    }
}
