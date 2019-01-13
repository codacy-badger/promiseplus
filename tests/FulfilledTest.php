<?php

namespace Async\Tests;

use Async\Loop\Loop;
use Async\Promise\Promise;
use Async\Promise\Fulfilled;
use PHPUnit\Framework\TestCase;

/**
 * @covers Async\Promise\Fulfilled
 */
class FulfilledTest extends TestCase
{
	protected function setUp()
    {
		Promise::clearLoop();
    }
	
    public function testCreatePromiseWhenFulfilledWithNoCallback()
    {
        $p = new Promise();
        $p->resolve('foo');
        $p2 = $p->then();
        $this->assertNotSame($p, $p2);
        $this->assertInstanceOf(Fulfilled::class, $p2);
    }	
	
    public function testReturnsValueWhenWaitedUpon()
    {
        $p = new Fulfilled('foo');
        $this->assertEquals('resolved', $p->getState());
        $this->assertEquals('foo', $p->wait(true));
    }

    public function testCannotCancel()
    {
        $p = new Fulfilled('foo');
        $this->assertEquals('resolved', $p->getState());
        $p->cancel();
        $this->assertEquals('foo', $p->wait());
    }

    /**
     * @expectedException \LogicException
     * @exepctedExceptionMessage Cannot resolve a fulfilled promise
     */
    public function testCannotResolve()
    {
        $p = new Fulfilled('foo');
        $p->resolve('bar');
    }

    /**
     * @expectedException \LogicException
     * @exepctedExceptionMessage Cannot reject a fulfilled promise
     */
    public function testCannotReject()
    {
        $p = new Fulfilled('foo');
        $p->reject('bar');
    }

    public function testCanResolveWithSameValue()
    {
        $p = new Fulfilled('foo');
        $this->assertNull($p->resolve('foo'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCannotResolveWithPromise()
    {
        new Fulfilled(new Promise());
    }

    public function testReturnsSelfWhenNoOnFulfilled()
    {
        $p = new Fulfilled('a');
        $this->assertSame($p, $p->then());
    }
	
    public function testAsynchronouslyInvokesOnFulfilled()
    {
		$loop = Loop::getInstance();
        $p = new Fulfilled('a', $loop);
        $r = null;
        $f = function ($d) use (&$r) { $r = $d; };
        $p2 = $p->then($f);
        $this->assertNotSame($p, $p2);
        $this->assertNull($r);
        $loop->run();
        $this->assertEquals('a', $r);
    }
	
    public function testReturnsNewRejectedWhenOnFulfilledFails()
    {
        $p = new Fulfilled('a');
        $f = function () { throw new \Exception('b'); };
        $p2 = $p->then($f);
        $this->assertNotSame($p, $p2);
        try {
            $p2->wait();
			$this->fail();
        } catch (\Exception $e) {
            $this->assertEquals('b', $e->getMessage());
        }
    }

    public function testOtherwiseIsSugarForRejections()
    {
        $c = null;
        $p = new Fulfilled('foo');
        $p->otherwise(function ($v) use (&$c) { $c = $v; });
        $this->assertNull($c);
    }
 
    public function testDoesNotTryToFulfillTwiceDuringTrampoline()
    {		
        $fp = new Fulfilled('a');
        $t1 = $fp->then(function ($v) { return $v . ' b'; });
        $t1->resolve('why!');
        $this->assertEquals('why!', $t1->wait());
    }
}
