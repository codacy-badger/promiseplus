<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Async\Loop;

use Async\Loop\LoopInterface;
use Async\Task\Scheduler;
use Async\Loop\Signaler;

class Loop extends Scheduler implements LoopInterface
{
    /**
     * Is the main loop active.
     *
     * @var bool
     */
    protected $running = false;

    /**
     * A list of timers, added by addTimeout.
     *
     * @var array
     */
    protected $timers = [];

    /**
     * A list of 'addTick' callbacks.
     *
     * @var callable[]
     */
    protected $addTicks = [];

    /**
     * List of readable streams for stream_select, indexed by stream id.
     *
     * @var resource[]
     */
    protected $readStreams = [];

    /**
     * List of writable streams for stream_select, indexed by stream id.
     *
     * @var resource[]
     */
    protected $writeStreams = [];

    /**
     * List of read callbacks, indexed by stream id.
     *
     * @var callback[]
     */
    protected $readCallbacks = [];

    /**
     * List of write callbacks, indexed by stream id.
     *
     * @var callback[]
     */
    protected $writeCallbacks = [];	
	
    private	static $loop; 
    private $pcntl = false;
    private $signals;
	
    /**
	 * Enable to have event-loop addTick queue run and exhausted when the process
     * exits and the exit is not the result of a PHP E_ERROR error.
     *
     * If you don't enable the shutdown process, then you MUST either run the manually
     * event-loop queue using the run() method or wait on each outstanding promise.
     *
     * Note: This shutdown will occur before any destructor are triggered.
     */
	public function __construct()
    {
		parent::__construct();
        $this->pcntl = \extension_loaded('pcntl');
        $this->signals = new Signaler();				
		self::$loop = $this;
    }
	
	/**
	 * Retrieves current Loop object.
	 */
	public function getInstance(): Loop
	{		
		if (!self::$loop) {
			self::$loop = new self();
		}

		return self::$loop;
	}	
	
	/**
	 * Reset current Loop object.
	 */
	public function clearInstance()
	{	
		if (self::$loop) {
			self::$loop->stop();	
			self::$loop->addTicks = [];
			self::$loop->writeCallbacks = [];	
			self::$loop->readCallbacks = [];
			self::$loop->writeStreams = [];
			self::$loop->readStreams = [];
			self::$loop->timers = [];			
			self::$loop = null;
		}
	}
		
    /**
     * Executes a function after x seconds.
     */
    public function addTimeout(callable $task, float $timeout)
    {
        $triggerTime = microtime(true) + ($timeout);

        if (!$this->timers) {
            // Special case when the timers array was empty.
            $this->timers[] = [$triggerTime, $task];

            return;
        }

        // We need to insert these values in the timers array, but the timers
        // array must be in reverse-order of trigger times.
        //
        // So here we search the array for the insertion point.
        $index = count($this->timers) - 1;
        while (true) {
            if ($triggerTime < $this->timers[$index][0]) {
                array_splice(
                    $this->timers,
                    $index + 1,
                    0,
                    [[$triggerTime, $task]]
                );
                break;
            } elseif (0 === $index) {
                array_unshift($this->timers, [$triggerTime, $task]);
                break;
            }
            --$index;
        }
    }
	
    public function clearTimeout($timeout)
    {
    }

    /**
     * Executes a function every x seconds.
     */
    public function setInterval(callable $task, float $timeout): array
    {
        $keepGoing = true;
        $f = null;

        $f = function () use ($task, &$f, $timeout, &$keepGoing) {
            if ($keepGoing) {
                $task();
                $this->addTimeout($f, $timeout);
            }
        };
        $this->addTimeout($f, $timeout);
		
        return ['I\'m an implementation detail', &$keepGoing];
    }

    /**
     * Stops a running interval.
     */
    public function clearInterval(array $intervalId)
    {
        $intervalId[1] = false;
    }

    /**
     * Adds a read stream.
     */
    public function addReadStream($stream, callable $task)
    {
        $this->readStreams[(int) $stream] = $stream;
        $this->readCallbacks[(int) $stream] = $task;
    }

    /**
     * Adds a write stream.
     */
    public function addWriteStream($stream, callable $task)
    {
        $this->writeStreams[(int) $stream] = $stream;
        $this->writeCallbacks[(int) $stream] = $task;
    }

    /**
     * Stop watching a stream for reads.
     */
    public function removeReadStream($stream)
    {
        unset(
            $this->readStreams[(int) $stream],
            $this->readCallbacks[(int) $stream]
        );
    }

    /**
     * Stop watching a stream for writes.
     */
    public function removeWriteStream($stream)
    {
        unset(
            $this->writeStreams[(int) $stream],
            $this->writeCallbacks[(int) $stream]
        );
    }

    /**
     * Runs the loop.
     */
    public function run()
    {		
        $this->running = true;

        do {
            $hasEvents = $this->tick(true);
        } while ($this->running && $hasEvents);
        $this->running = false;
    }

    /**
     * Executes all pending events.
     */
    public function tick(bool $block = false): bool
    {
        $this->runTicks();
        $nextTimeout = $this->runTimers();
		
		while (!$this->taskQueue->isEmpty()) {
            $task = $this->taskQueue->dequeue();
            $retval = $task->run();

            if ($retval instanceof Syscall) {
                try {
                    $retval($task, $this);
                } catch (\Exception $e) {
                    $task->setException($e);
                    $this->schedule($task);
                }
                continue;
            }

            if ($task->isFinished()) {
                unset($this->taskMap[$task->getTaskId()]);
            } else {
                $this->schedule($task);
            }
        }
		
        // Calculating how long runStreams should at most wait.
        if (!$block) {
            // Don't wait
            $streamWait = 0;
        } elseif ($this->addTicks) {
            // There's a pending 'addTick'. Don't wait.
            $streamWait = 0;
        } elseif (is_numeric($nextTimeout)) {
            // Wait until the next Timeout should trigger.
            $streamWait = $nextTimeout;
        } else {
            // Wait indefinitely
            $streamWait = null;
        }

        $this->runStreams($streamWait);

        return $this->readStreams || $this->writeStreams || $this->addTicks || $this->timers || !$this->signals->isEmpty() || !$this->taskQueue->isEmpty();
    }

    /**
     * Stops a running event loop.
     */
    public function stop()
    {
        $this->running = false;
    }

    /**
     * Add an function to Run immediately at the next iteration of the loop.
     */
    public function addTick(callable $task)
    {
        $this->addTicks[] = $task;
    }
	    
    /**
     * Executes all 'addTick' queue callbacks.
     */
    protected function runTicks()
    {
        $addTicks = $this->addTicks;
        $this->addTicks = [];

        foreach ($addTicks as $task) {
             $task();
        }
    }
	
    /**
     * Runs all pending timers.
     */
    protected function runTimers()
    {
        $now = microtime(true);
        while (($timer = array_pop($this->timers)) && $timer[0] < $now) {
            $timer[1]();
        }
        // Add the last timer back to the array.
        if ($timer) {
            $this->timers[] = $timer;

            return max(0, $timer[0] - microtime(true));
        }
    }
	
    /**
     * Runs all pending stream events.
     */
    protected function runStreams($timeout)
    {
        if ($this->readStreams || $this->writeStreams) {
            $read = $this->readStreams;
            $write = $this->writeStreams;
            $except = null;
            if (stream_select($read, $write, $except, (null === $timeout) ? null : 0, $timeout ? (int) ($timeout * 1000000) : 0)) {
                // See PHP Bug https://bugs.php.net/bug.php?id=62452
                // Fixed in PHP7
                foreach ($read as $readStream) {
                    $readCb = $this->readCallbacks[(int) $readStream];
                    $readCb();
                }
                foreach ($write as $writeStream) {
                    $writeCb = $this->writeCallbacks[(int) $writeStream];
                    $writeCb();
                }
            }//
        } elseif ($this->running && ($this->addTicks || $this->timers || !$this->signals->isEmpty())) {
            usleep(null !== $timeout ? intval($timeout * 1000000) : 200000);
        }
    }
	
    /**
    * If you want to use signal handling (see also [`addSignal()`](#addSignal) below),
    * this event loop implementation requires `ext-pcntl`.
    *
    * This extension is only available for Unix-like platforms and does not support Windows.
    *
    * It is commonly installed as part of many PHP distributions.
    * If this extension is missing (or you're running on Windows), signal handling is
    * not supported and throws a `BadMethodCallException` instead.
    */
    public function addSignal($signal, callable $listener)
    {
        if ($this->pcntl === false) {
            throw new \BadMethodCallException('Event loop feature "signals" isn\'t supported by the "StreamSelectLoop"');
        }
        $first = $this->signals->count($signal) === 0;
        $this->signals->add($signal, $listener);
        if ($first) {
            \pcntl_signal($signal, array($this->signals, 'call'));
        }
    }

    public function removeSignal($signal, callable $listener)
    {
        if (!$this->signals->count($signal)) {
            return;
        }
        $this->signals->remove($signal, $listener);
        if ($this->signals->count($signal) === 0) {
            \pcntl_signal($signal, \SIG_DFL);
        }
    }
}
