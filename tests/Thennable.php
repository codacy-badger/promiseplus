<?php
namespace Async\Tests;

use Async\Promise\Promise;

class Thennable
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

    public function resolve($value)
    {
        $this->nextPromise->resolve($value);
    }
	
    public function reject($reason)
    {
        $this->nextPromise->reject($reason);
    }
}
