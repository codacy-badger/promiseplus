<?php
namespace Async\Tests;

use Async\Promise\Promise;
use Async\Promise\PromiseInterface;
use PHPUnit\Framework\TestCase;

class NotPromiseInstance extends Thennable implements PromiseInterface
{
    private $nextPromise = null;

    public function __construct()
    {
        $this->nextPromise = new Promise();
    }

    public function then($success = null, $failure = null)
    {
        return $this->nextPromise->then($success, $failure);
    }

    public function success($success = null)
    {
        return $this->nextPromise->success($success);
    }
	
    public function failure($failure = null)
    {
        return $this->nextPromise->failure($failure);
    }
	
    public function otherwise($failure = null)
    {
        return $this->then($failure);
    }

    public function resolve($value)
    {
        $this->nextPromise->resolve($value);
    }

    public function reject($reason)
    {
        $this->nextPromise->reject($reason);
    }

    public function wait($unwrap = true)
    {
    }
	
    public function always($always = null)
    {
        return $this->nextPromise->always($always);
    }
	
    public function cancel()
    {
    }

    public function getState()
    {
        return $this->nextPromise->getState();
    }
}
