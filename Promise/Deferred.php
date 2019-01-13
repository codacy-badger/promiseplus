<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Async\Promise;

use Async\Promise\DeferredInterface;
use Async\Promise\PromiseInterface;
use Async\Promise\UnexpectedTypeException;

class Deferred implements DeferredInterface
{
    private $state;
    private $promise;
    private $progressCallbacks;
    private $alwaysCallbacks;
    private $successCallbacks;
    private $failureCallbacks;
    private $callbackArgs;

    public function __construct(PromiseInterface $promise = null)
    {
        $this->state = DeferredInterface::STATE_PENDING;
		$this->promise = empty($promise) ? $this->promise() : $promise;

        $this->alwaysCallbacks = array();
        $this->successCallbacks = array();
        $this->failureCallbacks = array();
    }

    /**
     * Returns the promise of the deferred.
     *
     * @return PromiseInterface
     */
    public function promise()
    {
        if (null === $this->promise) {
            $this->promise = new Promise();
        }

        return $this->promise;
    }
	
    public function getFailure()
    {
        return $this->failureCallbacks;
    }
	
    public function getSuccess()
    {
        return $this->successCallbacks;
    }
	
    public function getAlways()
    {
        return $this->alwaysCallbacks;
    }
	
    public function getState()
    {
        return $this->state;
    }
	
    public function setState($state = DeferredInterface::STATE_PENDING)
    {
        $this->state = $state;
    }
	
    public function always($always)
    {
        if (!is_callable($always)) {
            throw new UnexpectedTypeException($always, 'callable');
        }

        switch ($this->state) {
            case DeferredInterface::STATE_PENDING:
                $this->alwaysCallbacks[] = $always;
                break;
            default:
                call_user_func_array($always, $this->callbackArgs);
                break;
        }

        return $this;
    }

    public function success($success)
    {
        if (!is_callable($success)) {
            throw new UnexpectedTypeException($success, 'callable');
        }

        switch ($this->state) {
            case DeferredInterface::STATE_PENDING:
                $this->successCallbacks[] = $success;
                break;
            case DeferredInterface::STATE_RESOLVED:
                call_user_func_array($success, $this->callbackArgs);
        }

        return $this;
    }

    public function failure($failure)
    {
        if (!is_callable($failure)) {
            throw new UnexpectedTypeException($failure, 'callable');
        }

        switch ($this->state) {
            case DeferredInterface::STATE_PENDING:
                $this->failureCallbacks[] = $failure;
                break;
            case DeferredInterface::STATE_REJECTED:
                call_user_func_array($failure, $this->callbackArgs);
                break;
        }

        return $this;
    }
	
    public function then($success = null, $failure = null)
    {
        if ($success) {
            $this->success($success);
        }        

        if ($failure) {
            $this->failure($failure);
        }

        return $this;
    }

    public function resolve($value = null)
    {				
        if (DeferredInterface::STATE_REJECTED === $this->state) {
            throw new \LogicException('Cannot resolve a deferred object that has already been rejected');
        }

        if (DeferredInterface::STATE_RESOLVED === $this->state) {
            return $this;
        }

        $this->state = DeferredInterface::STATE_RESOLVED;
        $this->callbackArgs = func_get_args();

        while ($func = array_shift($this->alwaysCallbacks)) {
            call_user_func_array($func, $this->callbackArgs);
        }

        while ($func = array_shift($this->successCallbacks)) {
            call_user_func_array($func, $this->callbackArgs);
        }
		
        return $this;
    }

    public function reject($reason = null)
    {
        if (DeferredInterface::STATE_RESOLVED === $this->state) {
            throw new \LogicException('Cannot reject a deferred object that has already been resolved');
        }

        if (DeferredInterface::STATE_REJECTED === $this->state) {
            return $this;
        }

        $this->state = DeferredInterface::STATE_REJECTED;
        $this->callbackArgs = func_get_args();

        while ($func = array_shift($this->alwaysCallbacks)) {
            call_user_func_array($func, $this->callbackArgs);
        }

        while ($func = array_shift($this->failureCallbacks)) {
            call_user_func_array($func, $this->callbackArgs);
        }
		
        return $this;
    }
    
    public function otherwise($failure)
    {
        return $this->failure($failure);
    }

    public function cancel()
    {
    }
	
    public function wait($unwrap = true)
    {
        return $this->promise->wait($unwrap);
    }
}
