<?php
/**
 * This file is part of universalPHP Plugin Event System.
 */

namespace Async\Promise;

use Async\Promise\Promise;
use Async\Promise\PromiseInterface;
use Exception;
use Generator;
use Throwable;

/**
 * Creates a promise that is resolved using a generator that yields values or
 * promises (somewhat similar to C#'s async keyword).
 *
 * When called, the coroutine function will start an instance of the generator
 * and returns a promise that is fulfilled with its final yielded value.
 *
 * Control is returned back to the generator when the yielded promise settles.
 * This can lead to less verbose code when doing lots of sequential async calls
 * with minimal processing in between.
 *
 *     use GuzzleHttp\Promise;
 *
 *     function createPromise($value) {
 *         return new Promise\FulfilledPromise($value);
 *     }
 *
 *     $promise = Promise\coroutine(function () {
 *         $value = (yield createPromise('a'));
 *         try {
 *             $value = (yield createPromise($value . 'b'));
 *         } catch (\Exception $e) {
 *             // The promise was rejected.
 *         }
 *         yield $value . 'c';
 *     });
 *
 *     // Outputs "abc"
 *     $promise->then(function ($v) { echo $v; });
 *
 * @param callable $generatorFn Generator function to wrap into a promise.
 *
 * @return Promise
 * @link https://github.com/petkaantonov/bluebird/blob/master/API.md#generators inspiration
 */
final class Coroutine implements PromiseInterface
{
    /**
     * @var PromiseInterface|null
     */
    private $currentPromise;

    /**
     * @var Generator
     */
    private $generator;

    /**
     * @var Promise
     */
    private $result;

    public function __construct(callable $generatorFn)
    {
        $this->generator = $generatorFn();
		if (!$this->generator instanceof Generator) {
			throw new \InvalidArgumentException('You must pass a generator function');
		}
		
        $this->result = new Promise(function () {
            while (isset($this->currentPromise)) {
                $this->currentPromise->wait();
            }
        });
        $this->nextCoroutine($this->generator->current());
    }

    public function success($success = null) 
	{
        return $this->result->success($success);
    }
	
    public function then($success, $failure = null) 
	{
        return $this->result->then($success, $failure);
    }

    public function failure($failure = null)
    {
        return $this->result->failure($failure);
    }
	
    public function otherwise($failure = null)
    {
        return $this->result->otherwise($failure);
    }

    public function wait($unwrap = true)
    {
        return $this->result->wait($unwrap);
    }
	
    public function done(callable $success = null, callable $failure = null)
    {
        return $this->result->done($success, $failure);
    }

    public function always($always)
    {
        return $this->result->always($always);
    }

    public function getState()
    {
        return $this->result->getState();
    }

    public function resolve($result)
    {
        return $this->result->resolve($result);
    }

    public function reject($reason)
    {
        return $this->result->reject($reason);
    }

    public function cancel()
    {
        $this->currentPromise->cancel();
        $this->result->cancel();
    }
		
    private function nextCoroutine($yielded)
    {
        return $this->currentPromise = Promise::resolver($yielded)
            ->then([$this, '_handleSuccess'], [$this, '_handleFailure']);
    }

    /**
     * @internal
     */
    public function _handleSuccess($value)
    {
        unset($this->currentPromise);
        try {
            $next = $this->generator->send($value);
            if ($this->generator->valid()) {
                $this->nextCoroutine($next);
            } else {
                $this->result->resolve($value);
            }
        } catch (Exception $exception) {
            $this->result->reject($exception);
        } catch (Throwable $throwable) {
            $this->result->reject($throwable);
        }
    }

    /**
     * @internal
     */
    public function _handleFailure($reason)
    {
        unset($this->currentPromise);
        try {
            $nextYield = $this->generator->throw(Promise::rejection($reason));
            // The throw was caught, so keep iterating on the coroutine
            $this->nextCoroutine($nextYield);
        } catch (Exception $exception) {
            $this->result->reject($exception);
        } catch (Throwable $throwable) {
            $this->result->reject($throwable);
        }
    }	
}
