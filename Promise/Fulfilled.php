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

class Fulfilled implements PromiseInterface
{
	use Core;
	
    private static $loop = null;
    private $value;

	/**
     * @param mixed|null $value
     * @param Event LoopInterface $loop
     * @throws InvalidArgumentException
     */
    public function __construct($value = null, LoopInterface $loop = null)
    {
        if (method_exists($value, 'then')) {
            throw new \InvalidArgumentException(
                'You cannot create a Fulfilled with a promise. Use Promise::resolve($value) instead.');
        } elseif ($value instanceof LoopInterface) {
            throw new \InvalidArgumentException('You cannot create a Fulfilled with a LoopInterface.');
        }
		
		self::$loop = empty($loop) ? Promise::getLoop() : $loop;
        $this->value = $value;
    }
	
    public function then($success = null, $failure = null)
    {
		if (null === $success)
			return $this; 
		
		$promise = new Promise(self::$loop);
        return Promise::implement(function () use($success, $promise) {  
            try {
                if (is_callable($success)) {
					$value = $success($this->value);
                } elseif (null !== $success) {
                    trigger_error('Invalid $success argument passed to then(), must be null or callable.', E_USER_NOTICE);
                }
				$promise = $promise->resolve($value);	
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
        if (null === $success) {
            return;
        }

        Promise::implement(function () use ($success, $failure) {
            try {
                $value = $success($this->value);
            } catch (\Throwable $exception) {
                return Promise::fatalError($exception);
            } catch (\Exception $exception) {
                return Promise::fatalError($exception);
            }

            if ($value instanceof PromiseInterface) {
                $value->done();
            }
        }, $this);
    }
	
    public function always($always = null)
    {
        if (!is_callable($always)) {
            throw new UnexpectedTypeException($always, 'callable');
        }
		
        return $this->then(function ($value) use ($always) {
            return Promise::resolve($always())->then(function () use ($value) {
                return $value;
            });
        });
    }
	
    public function getState()
    {
        return PromiseInterface::STATE_RESOLVED;
    }

    public function resolve($value)
    {
        if ($value !== $this->value) {
            throw new \LogicException("Cannot resolve a fulfilled promise");
        }
    }

    public function reject($reason)
    {
        throw new \LogicException("Cannot reject a fulfilled promise");
    }		

    public function wait($unwrap = true)
    {		
        return $unwrap ? $this->value : null;
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
