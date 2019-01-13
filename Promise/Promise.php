<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Async\Promise;

use Async\Promise\PromiseInterface;
use Async\Promise\Deferred;
use Async\Promise\Fulfilled;
use Async\Promise\Rejected;
use Async\Promise\UnexpectedTypeException;

class Promise implements PromiseInterface
{
	use Core, Partial, Collections, Inspection, Interoperability;

    private static $loop = null;
	
	/**
     * The result of the promise.
     *
     * If the promise was fulfilled, this will be the result value. If the
     * promise was rejected, this property hold the rejection reason.
     *
     * @var mixed
     */
    private $result;
	
	/**
     * A list of sub promise chaining. Chaining are the callbacks that want us to let
     * them know if the callback was resolved or rejected. 
     *
     * @var array
     */
    private $chaining = [];
	
    private $childPromise = [];
    private $delegate;	
	private $cancelFunction; 
    private $isCancelled = false;	
    private $isWaitRequired = false;

	/**
     * Create a new promise. The passed in function will receive functions resolve and reject 
	 * as its arguments which can be called to seal the fate of the created promise. In addition 
	 * an canceler, an event loop instance.
	 *
	 * @see https://www.promisejs.org/api/
	 * @see http://bluebirdjs.com/docs/anti-patterns.html#the-explicit-construction-anti-pattern
	 * 
     * @param mixed|null $childPromise
     * @throws UnexpectedTypeException
     */
    public function __construct( ...$childPromise)
    {
        $this->delegate = new Deferred($this);
		
		$callResolver = isset($childPromise[0]) ? $childPromise[0] : null;		
		$childLoop = $this->isLoopAvailable($callResolver) ? $callResolver : null;
		$callResolver = $this->isLoopAvailable($callResolver) ? null : $callResolver;	
		
		$callCanceller = isset($childPromise[1]) ? $childPromise[1] : null;
		$childLoop = $this->isLoopAvailable($callCanceller) ? $callCanceller : $childLoop;
		$callCanceller = $this->isLoopAvailable($callCanceller) ? null : $callCanceller;	
				
		$loop = isset($childPromise[2]) ? $childPromise[2] : self::$loop;		
		$childLoop = $this->isLoopAvailable($loop) ? $loop : $childLoop;
		self::$loop = $this->isLoopAvailable($childLoop) ? $childLoop : Promise::getLoop();
		
		if ($callResolver instanceof PromiseInterface) {	
			// validate childPromises
			foreach ($childPromise as $child) {
				if (!$child instanceof PromiseInterface && !$child instanceof LoopInterface) {
					throw new UnexpectedTypeException($child, 'PromiseInterface');
				}
				
				if ($child instanceof LoopInterface) {
					self::$loop = $child;
				} else {				
					// connect to each child  
					$child->always(array($this, 'tick'));
				}
			}					
			$this->childPromise += $childPromise;
		} else {
			$this->cancelFunction = is_callable($callCanceller) ? $callCanceller : null;		
		
			$promiseFunction = function () use($callResolver) { 
				if (is_callable($callResolver)) {
					$callResolver([$this, 'resolve'], [$this, 'reject']);
				}			
			};
			
			try {
				$promiseFunction();
			} catch (\Throwable $e) {
				$this->isWaitRequired = true;
				$this->implement($promiseFunction);
			} catch (\Exception $exception) {
				$this->isWaitRequired = true;
				$this->implement($promiseFunction);
			}
		}		
	}	

    /**
     * Returns the deferred of the promise.
     *
     * @return DeferredInterface
     */
    public function deferred()
    {
        if (null === $this->delegate) {
            $this->delegate = new Deferred($this);
        }

        return $this->delegate;
    }
	
	/**
     * Adds a callback that will be invoked, after the promise has been resolved
	 * (for both fulfillment and rejection).
	 *
     * @param callable $always 
     * @returns PromiseInterface
     */	
	public function always($always = null)
    {
		if (!$always)
			return $this;
		
		return $this->implement(function () use ($always) { 
            $this->delegate->always($always); 
            return $this;
        }, $this);
    }
		
    /**
     * Marks this promise as resolved and sets its return value.
     * 
     * @param mixed $value
     * @return new PromiseInterface
	 * @throws LogicException
     */
    public function resolve($value = null)
	{	
        return $this->settle(PromiseInterface::STATE_RESOLVED, $value, 'success');
    }
	
    public function fulfill($value = null)
	{	
        return $this->resolve($value);
    }
	
	/**
     * Marks this promise as rejected, and set it's rejection reason.
     * 
     * @param mixed $reason
     * @return new PromiseInterface
	 * @throws LogicException
     */
    public function reject($reason = null)
    {
        return $this->settle(PromiseInterface::STATE_REJECTED, $reason, 'failure');
    }

	/**
     * Promise Resolution Procedure.
     */
    private function settle($state, $value = null, $deferFunc = null)
    {
        if ($this->isSettled()) {
            // Ignore calls with the same resolution.
            if ($state === $this->getState() && $value === $this->result) {
                return;
            }
            throw $this->getState() === $state
                ? new \LogicException("The promise is already {$state}.")
                : new \LogicException("Cannot change a {$this->getState()} promise to {$state}");
        }

        if ($value === $this) {
            throw new \LogicException('Cannot fulfill or reject a promise with itself');
        }

        // Clear out the state of the promise and stash the promise chaining.
        $this->result = $value;
		$chaining = $this->chaining;
		$this->chaining = $this->childPromises = [];
		$this->cancelFunction = null;
        $this->setState($state);		

        if (!$chaining) {
            return new self(self::$loop);
		} 
				
		return $this->invokeSettler($chaining[0][0], $chaining, $deferFunc);
    }

    private function invokeSettler(PromiseInterface $subPromise, 
		array $chaining = null, 
		string $deferFunc = null)
    {
		foreach ($chaining as $promise) {
			$subPromise = $promise[0];
			$callAlwaysBacks = $promise[1]->delegate->getAlways();
			
			if ($deferFunc == 'success')				
				$callBacks = $promise[1]->delegate->getSuccess();	
			elseif ($deferFunc == 'failure')
				$callBacks = $promise[1]->delegate->getFailure();	
			else
				throw \LogicException('Unable to execute settler, bad arguments.');
						
			while ($func = array_shift($callAlwaysBacks)) {	
				//$this->invokeQueue($subPromise, $func);
				$this->implement(function () use ($func) {
					$func($this->result);
				});
			}
			
			while ($func = array_shift($callBacks)) {
				$this->invokeQueue($subPromise, $func);
			}
        }
		
		return $subPromise;
    }
	
	/** 
     * Cancel the promise.
     *
     * @return PromiseInterface
     * @cancels Error|Exception|string|null
	 *    
	 * @see http://bluebirdjs.com/docs/api/cancellation.html
	 */
    public function cancel()
    {
        if ($this->isSettled()) {
            return;
        }

		$this->isWaitRequired = false;
		$this->chaining = $this->childPromise = [];

        $this->isCancelled = true;
		
        if ($this->cancelFunction) {
            $func = $this->cancelFunction;
            $this->cancelFunction = null;
            try {
                $func();
            } catch (\Throwable $e) {
                $this->reject($e);
            } catch (\Exception $exception) {
                $this->reject($exception);
            }
        }
		
        // Reject the promise only if it wasn't rejected in a then callback.
        if ($this->isPending()) {
			$this->reject('Promise has been canceled.');
        }
		
        $status = $this->result instanceof PromiseInterface
            ? $this->result->cancel()
            : $this->result;
			
        if ($status instanceof PromiseInterface || $this->isRejected()) {
            return $status;
        }		
    }

    public function success($success = null)
    {	
		if (!$success)
			return $this;	
		
		return $this->implement(function () use ($success) { 
            $this->delegate->success($success); 
            return $this;
        }, $this);
    }

    public function failure($failure = null)
    {			
		if (!$failure)
			return $this;	
			
		return $this->implement(function () use ($failure) { 
            $this->delegate->failure($failure); 
            return $this;
        }, $this);	
    }
		
    /**
     * Transform Promise value by applying a callback function.
     *	 
     * This method allows you to specify the callback that will be called after
     * the promise has been resolved or rejected.
     *
     * This method returns a new promise, which can be used for chaining.
     * If either the success or failure callback is called, you may
     * return a result from this callback.
     *
     * If the result of this callback is yet another promise, the result of
     * _that_ promise will be used to set the result of the returned promise.
     *
     * If either of the callbacks return any other value, the returned promise
     * is automatically resolved with that value.
     *
     * If either of the callbacks throw an exception, the returned promise will
     * be rejected and the exception will be passed back.
     *	 
     * @param callable|null $success
     * @param callable|null $failure
     * @return new PromiseInterface
     * @throws UnexpectedTypeException
     */
    public function then($success = null, $failure = null)
    {		
		$promiseChain = new self(null, [$this, 'cancel'], self::$loop);	
		switch ($this->getState()) {
			case PromiseInterface::STATE_PENDING:
				if (!$success && !$failure)	
					return $this;
				
                // The operation hasn't been resolved and is pending, 
				// so we keep a reference to the chain handlers
				// so we can call them later.
				$this->delegate->then($success, $failure);		
				$this->chaining[] = [$promiseChain, $this];
				break;
			case PromiseInterface::STATE_RESOLVED:
				// The async operation is already resolved, so we trigger the
				// success callback asap.
				if ($success)
					$this->invokeQueue($promiseChain, $success);
				else
					$promiseChain = Promise::resolver($this->result);
				break;
			case PromiseInterface::STATE_REJECTED:		
				// The async operation rejected, so we call the failure
				// callback asap.
				if ($failure)
					$this->invokeQueue($promiseChain, $failure);
				else
					$promiseChain = Promise::rejecter($this->result);
				break;
		}
			
		return $promiseChain;	
	}
	
	/**
	 * Like calling .then, but any unhandled rejection that ends up here will crash the process 
	 * or be thrown as an error for developer to assume responsibility for.
     *
	 * This method purpose is consumption rather than transformation, it terminates a chain of promises.
	 *
     * @see http://bluebirdjs.com/docs/api/done.html
     *
     * @param callable|null $success
     * @param callable|null $failure
     * @return void
     * @resolves mixed
     * @rejects Error|Exception|string|null
     */
    public function done(callable $success = null, callable $failure = null)
    {		
        $this->implement(function () use ($success, $failure) {
			try {
                $value = $this->then($success, $failure);
            } catch (\Throwable $e) {
                return Promise::fatalError($e);
            } catch (\Exception $exception) {
                return Promise::fatalError($exception);
            }

            if ($value instanceof PromiseInterface) {
                $value->done();
            }
        });
    }
	
    /**
     * Add a callback for when this promise is rejected.
     *
     * @param callable $failure
     * @return new PromiseInterface
     */
    public function otherwise($failure = null)
    {		
        return $this->then(null, $failure);
    }		
	
    public function tick()
    {
        $pending = count($this->childPromise);

        foreach ($this->childPromise as $child) {
			if ($child instanceof LoopInterface) {
				--$pending;
			} else {	
				switch ($child->getState()) {
					case PromiseInterface::STATE_REJECTED:
						$this->delegate->reject($this);

						return;
					case PromiseInterface::STATE_RESOLVED:
						--$pending;
						break;
				}	
			}			
        }

        if (!$pending) {
            $this->delegate->resolve($this);
        }
    }	
	
	/**
     * Stops execution until this promise is resolved.
     *
     * This method stops execution completely. If the promise is successful with
     * a value, this method will return this value. If the promise was
     * rejected, this method will throw an exception.
     *
     * This effectively turns the asynchronous operation into a synchronous
     * one. In PHP it might be useful to call this on the last promise in a
     * chain.
     *
     * @return mixed
     */
    public function wait($unwrap = true) 
    {
		try {
			$loop = self::$loop;
			if (method_exists($loop, 'tick')) {				
				$hasEvents = true;	
				while ($this->isPending()) {
					if (!$hasEvents) {
						if ($this->isCancelled() || empty($this->chaining))
							throw new \LogicException('Promise has been canceled.');
						else 
							throw new \LogicException('There were no more events in the loop. This promise will never be fulfilled.');
					}
					// As long as the promise is not resolved, we tell the event loop
					// to handle events, and to block.			
					$hasEvents = $loop->tick(true);
				}
			}
			
			if (method_exists($loop, 'run')) {
				$loop->run();
			}
        } catch (\Exception $reason) {
            if ($this->isPending()) {
                // The promise has not been resolved yet, so reject the promise
                // with the exception.
                $this->reject($reason);
            } else {
                // The promise was already resolved, so there's a problem in
                // the application.
                throw $reason;
            }
        }
		
		if ($this->result instanceof PromiseInterface) {
            return $this->result->wait($unwrap);
        }

		if ($this->isPending()) {
            $this->reject('Invoking the wait callback did not resolve the promise');
        } elseif ($unwrap) {
			if ($this->isFulfilled()) {
				// If the state of this promise is resolved, we can return the value.
				return $this->result;
			} 
			// If we got here, it means that the asynchronous operation
			// erred. Therefore it's rejected, so throw an exception.
			throw $this->result instanceof \Exception
				? $this->result
				: new \Exception($this->result);
		}
    }
	
	/**
     * This method is used to call either an success or failure callbacks.
     *
     * This method makes sure that the result of these callbacks are handled
     * correctly, and any chained promises are also correctly resolved or
     * rejected.
     *
     * @param PromiseInterface $promiser
     * @param Callable $callBack
     * @return void
     */
    private function invokeQueue(PromiseInterface $promiser, callable $callBack = null) 
	{
        // We use an Tick/Queue callable function to ensure that the event 
		// handlers are always triggered outside of the calling stack in which 
        // they were originally passed to 'then'. 
        // This makes the order of execution more predictable.
        $this->implement(function() use ($callBack, $promiser) {
            if (is_callable($callBack)) {
                try {
                    $result = $callBack($this->result);
                    if ($result instanceof PromiseInterface) {	
                        // If the callback (failure or success)
                        // returned a promise, we only resolve or reject the
                        // chained promise once that promise has also been
                        // resolved.
                        $result->then([$promiser, 'resolve'], [$promiser, 'reject']);
                    } else {
                        // If the callback returned any other value, we
                        // immediately resolve the chained promise.	
                        $promiser->resolve($result);
                    }
				// If the event handler threw an exception, we need to make sure that
				// the chained promise is rejected as well.
                } catch (\Throwable $e) {
                    $promiser->reject($e);
                } catch (\Exception $exception) {
                    $promiser->reject($exception);
                }
            }
        }, $promiser);
    }
}
