<?php

namespace Async\Tests;

use Async\Loop\Loop;
use Async\Promise\Promise;
use Async\Promise\Rejected;
use PHPUnit\Framework\TestCase;

/**
 * @covers Async\Promise\Rejected
 */
class RejectedTest extends TestCase
{		
	protected function setUp()
    {
		Promise::clearLoop();
    }
	
    public function testCreatesPromiseWhenRejectedWithNoCallback()
    {
        $p = new Promise();
        $p->reject('foo');
        $p2 = $p->then();
        $this->assertNotSame($p, $p2);
        $this->assertInstanceOf(Rejected::class, $p2);
    }
	
    public function testThrowsReasonWhenWaitedUpon()
    {
        $p = new Rejected('foo');
        $this->assertEquals('rejected', $p->getState());
        try {
            $p->wait(true);
            $this->fail();
        } catch (\Exception $e) {
            $this->assertEquals('rejected', $p->getState());
            $this->assertContains('foo', $e->getMessage());
        }
    }

    public function testCannotCancel()
    {
        $p = new Rejected('foo');
        $p->cancel();
        $this->assertEquals('rejected', $p->getState());
    }

    /**
     * @expectedException \LogicException
     * @exepctedExceptionMessage Cannot resolve a rejected promise
     */
    public function testCannotResolve()
    {
        $p = new Rejected('foo');
        $p->resolve('bar');
    }

    /**
     * @expectedException \LogicException
     * @exepctedExceptionMessage Cannot reject a rejected promise
     */
    public function testCannotReject()
    {
        $p = new Rejected('foo');
        $p->reject('bar');
    }
	
    public function testCanRejectWithSameValue()
    {
        $p = new Rejected('foo');
        $this->assertNull($p->reject('foo'));
    }
	
    public function testThrowsSpecificException()
    {
        $e = new \Exception();
        $p = new Rejected($e);
        try {
            $p->wait(true);
            $this->fail();
        } catch (\Exception $e2) {
            $this->assertSame($e, $e2);
        }
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCannotResolveWithPromise()
    {
        new Rejected(new Promise());
    }

    public function testReturnsSelfWhenNoOnReject()
    {
        $p = new Rejected('a');
        $this->assertSame($p, $p->then());
    }

	public function testInvokesOnRejectedAsynchronously()
    {
		$loop = Loop::getInstance();
        $p = new Rejected('a', $loop);
        $r = null;
        $f = function ($reason) use (&$r) { $r = $reason; };
        $p->then(null, $f);
        $this->assertNull($r);
		$loop->run();
        $this->assertEquals('a', $r);
    }
	
    public function testReturnsNewRejectedWhenOnRejectedFails()
    {
        $p = new Rejected('a');
        $f = function () { 
			throw new \Exception('b'); 
		};
        $p2 = $p->then(null, $f);
        $this->assertNotSame($p, $p2);
        try {
            $p2->wait();
            $this->fail();
        } catch (\Exception $e) {
            $this->assertEquals('b', $e->getMessage());
        }
    }

    public function testWaitingIsNoOp()
    {
        $p = new Rejected('a');
        $this->assertNull($p->wait(false));		
    }
	
    public function testOtherwiseIsSugarForRejections()
    {
		$loop = Loop::getInstance();
        $p = new Rejected('foo', $loop);
        $p->otherwise(function ($v) use (&$c) { 
			$c = $v; 
		});
		$loop->run();
        $this->assertSame('foo', $c);
    }
	
    public function testCanResolveThenWithSuccess()
    {
		$loop = Loop::getInstance();
        $actual = null;
        $p = new Rejected('foo', $loop);
        $p->otherwise(function ($v) { 
			return $v . ' bar';
        })->then(function ($v) use (&$actual) { 
			$actual = $v;
        });
		$loop->run();
        $this->assertEquals('foo bar', $actual);
    }

    public function testDoesNotTryToRejectTwiceDuringTrampoline()
    {
        $fp = new Rejected('a');
        $t1 = $fp->then(null, function ($v) { 
			return $v . ' b'; 
		});
        $t1->resolve('why!');
        $this->assertEquals('why!', $t1->wait());
    }
}
