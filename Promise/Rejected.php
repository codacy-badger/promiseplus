<?php
/**
 * This file is part of universalPHP Plugin Event System.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Async\Promise;

use Async\Loop\LoopInterface;
use Async\Promise\Promise;
use Async\Promise\PromiseInterface;
use Async\Promise\RejectionException;

class Rejected implements PromiseInterface
{
	use Core;
	
    private static $loop = null;
    private $reason;
	
	/**
     * @param mixed|null $reason
     * @param Event LoopInterface $loop
     * @throws InvalidArgumentException
     */
    public function __construct($reason = null, LoopInterface $loop = null)
    {
        if (method_exists($reason, 'then')) {
            throw new \InvalidArgumentException(
                'You cannot create a Rejected with a promise. Use Promise::reject($reason) instead.');
        } elseif ($reason instanceof LoopInterface) {
            throw new \InvalidArgumentException('You cannot create a Rejected with a LoopInterface.');
        }

		self::$loop = empty($loop) ? Promise::getLoop() : $loop;
        $this->reason = $reason;
    }

    public function then($success = null, $failure = null)
    {
        // If there's no failure callback then just return self.        
		if (null === $failure)
			return $this; 
		
		$promise = new Promise(self::$loop);
        return Promise::implement(function () use($failure, $promise) { 
			try {
				if (is_callable($failure)) {
					$reason = $failure($this->reason);
				} elseif (null !== $failure) {
					trigger_error('Invalid $failure argument passed to then(), must be null or callable.', E_USER_NOTICE);
				} 
				$promise = $promise->resolve($reason);
			} catch (\Throwable $e) {
                $promise = new Rejected($e);
			} catch (\Exception $exception) {  
				$promise = new Rejected($exception);
			}
			
			return $promise;
        }, $promise);
    }	

    public function done(callable $success = null, callable $failure = null)
    {
        Promise::implement(function () use ($success, $failure) {
			if (null === $failure) {
					return Promise::fatalError(
						RejectionException::resolve($this->reason)
					);
			}
			
            try {
                $result = $failure($this->reason);
            } catch (\Throwable $exception) {
                return Promise::fatalError($exception);
            } catch (\Exception $exception) {
                return Promise::fatalError($exception);
            }

            if ($result instanceof self) {
                return Promise::fatalError(
                    RejectionException::resolve($result->reason)
                );
			}
			
            if ($result instanceof PromiseInterface) {
                $result->done();
            }
        }, $this);
    }
	
    public function always($always = null)
    {
        if (!is_callable($always)) {
            throw new UnexpectedTypeException($always, 'callable');
        }
		
        return $this->then(null, function ($reason) use ($always) {
            return Promise::resolve($always())->then(function () use ($reason) {
                return new Rejected($reason, self::$loop);
            });
        });
    }
	
    public function getState()
    {
        return PromiseInterface::STATE_REJECTED;
    }

    public function resolve($value)
    {
        throw new \LogicException("Cannot resolve a rejected promise");
    }

    public function reject($reason)
    {
        if ($reason !== $this->reason) {
            throw new \LogicException("Cannot reject a rejected promise");
        }
    }

    public function wait($unwrap = true)
    {		
        if ($unwrap) {
            throw Promise::rejection($this->reason);
        }
    }
	
    public function success($success = null) 
	{		
		return $this->then($success);
	}

    public function cancel()
    {        
    }	

    public function otherwise($failure = null)
    {		
        return $this->failure($failure);
    }
			
    public function failure($failure = null) 
	{		
		return $this->then(null, $failure);
	}
}
