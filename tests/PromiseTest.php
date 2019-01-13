<?php
namespace Async\Tests;

use Exception;
use Async\Loop\Loop;
use Async\Promise\Promise;
use Async\Promise\Rejected;
use Async\Promise\PromiseInterface;
use PHPUnit\Framework\TestCase;

class PromiseTest extends TestCase
{
	private $loop; 
	private $errorHandler;
	
	protected function setUp()
    {
		Loop::clearInstance();
		Promise::clearLoop();				
        $this->loop = Promise::getLoop(true);
    }	
	
    protected function tearDown()
    {
        restore_error_handler();
    }
	    	
    public function testSuccess()
    {		
        $finalValue = 0;
        $promise = new Promise();
        $promise->resolve(1);

        $promise->then(function ($value) use (&$finalValue) {
            $finalValue = $value + 2;
        });
        $this->loop->run();

        $this->assertEquals(3, $finalValue);
    }

    public function testPendingResult()
    {
        $finalValue = 0;
        $promise = new Promise();

        $promise->then(function ($value) use (&$finalValue) {
            $finalValue = $value + 2;
        });

        $promise->resolve(4);
        $this->loop->run();

        $this->assertEquals(6, $finalValue);
    }
	
    public function testForwardsReturnedRejectedPromisesDownChainBetweenGaps()
    {
        $p = new Promise();
        $rejected = new Rejected('bar');
        $r = $r2 = null;
        $p->then(null, null)
            ->then(null, function ($v) use (&$r, $rejected) {
                $r = $v;
                return $rejected;
            })
            ->then(null, function ($v) use (&$r2) { 
				$r2 = $v; }
            );
        $p->reject('foo');
        $this->loop->run();
        $this->assertEquals('foo', $r);
        $this->assertEquals('bar', $r2);
        try {
            $p->wait();
        } catch (\Exception $e) {
            $this->assertEquals('foo', $e->getMessage());
        }
    }	
	
    public function testCreatesPromiseWhenFulfilledAfterThen()
    {
        $p = new Promise();
        $carry = null;
        $p2 = $p->then(function ($v) use (&$carry) { $carry = $v; });
        $this->assertNotSame($p, $p2);
        $p->resolve('foo');
        $this->loop->run();
        $this->assertEquals('foo', $carry);
    }
	
    public function testCreatesPromiseWhenFulfilledBeforeThen()
    {
        $p = new Promise();
        $p->resolve('foo');
        $carry = null;
        $p2 = $p->then(function ($v) use (&$carry) { $carry = $v; });
        $this->assertNotSame($p, $p2);
        $this->assertNull($carry);
        $this->loop->run();
        $this->assertEquals('foo', $carry);
    }
	
    public function testCreatesPromiseWhenRejectedAfterThen()
    {
		$p = new Promise();
        $carry = null;
        $p2 = $p->then(null, function ($v) use (&$carry) { $carry = $v; });
        $this->assertNotSame($p, $p2);
        $p->reject('foo');
        $this->loop->run();
        $this->assertEquals('foo', $carry);
    }
	
    public function testCreatesPromiseWhenRejectedBeforeThen()
    {
        $p = new Promise();
        $p->reject('foo');
        $carry = null;
        $p2 = $p->then(null, function ($v) use (&$carry) { $carry = $v; });
        $this->assertNotSame($p, $p2);
        $this->assertNull($carry);
        $this->loop->run();
        $this->assertEquals('foo', $carry);
    }	
	
    public function testOtherwiseIsSugarForRejections()
    {
        $p = new Promise();
        $p->reject('foo');
        $p->otherwise(function ($v) use (&$c) { $c = $v; });
        $this->loop->run();
        $this->assertEquals($c, 'foo');
    }
	
    public function testCatchIsJustLikeOtherwise()
    {
        $p = new Promise();
        $p->reject('foo');
        $p->catch(function ($v) use (&$c) { $c = $v; });
        $this->loop->run();
        $this->assertEquals($c, 'foo');
    }
	
    public function testForwardsFulfilledDownChainBetweenGaps()
    {
        $p = new Promise();
        $r = $r2 = null;
        $p->then(null, null)
            ->then(function ($v) use (&$r) {$r = $v; return $v . '2'; })
            ->then(function ($v) use (&$r2) { $r2 = $v; });
        $p->resolve('foo');
        $this->loop->run();
        $this->assertEquals('foo', $r);
        $this->assertEquals('foo2', $r2);
    }
	
    public function testForwardsRejectedPromisesDownChainBetweenGaps()
    {
        $p = new Promise();
        $r = $r2 = null;
        $p->then(null, null)
            ->then(null, function ($v) use (&$r) { $r = $v; return $v . '2'; })
            ->then(function ($v) use (&$r2) { $r2 = $v; });
        $p->reject('foo');
        $this->loop->run();
        $this->assertEquals('foo', $r);
        $this->assertEquals('foo2', $r2);
    }
	
    public function testForwardsThrownPromisesDownChainBetweenGaps()
    {
        $e = new \Exception();
        $p = new Promise();
        $r = $r2 = null;
        $p->then(null, null)
            ->then(null, function ($v) use (&$r, $e) { 
                $r = $v;
                throw $e;
            })
            ->then(
                null, function ($v) use (&$r2) { 
				$r2 = $v; }
            );
        $p->reject('foo');
        $this->loop->run();
        $this->assertEquals('foo', $r);
        $this->assertSame($e, $r2);
    }
	
    public function testForwardsHandlersToNextPromise()
    {
        $p = new Promise();
        $p2 = new Promise();
        $resolved = null;
        $p
            ->then(function ($v) use ($p2) { return $p2; })
            ->then(function ($value) use (&$resolved) { $resolved = $value; });
        $p->resolve('a');
        $p2->resolve('b');
        $this->loop->run();
        $this->assertEquals('b', $resolved);
    }
	
    public function testForwardsHandlersWhenFulfilledPromiseIsReturned()
    {
        $res = [];
        $p = new Promise();
        $p2 = new Promise();
        $p2->resolve('foo');
        $p2->then(function ($v) use (&$res) { $res[] = 'A:' . $v; });
        // $res is A:foo
        $p
            ->then(function () use ($p2, &$res) { $res[] = 'B'; return $p2; })
            ->then(function ($v) use (&$res) { $res[] = 'C:' . $v; });
        $p->resolve('a');
        $p->then(function ($v) use (&$res) { $res[] = 'D:' . $v; });
        $this->loop->run();
        $this->assertEquals(['A:foo', 'B', 'D:a', 'C:foo'], $res);
    }
	
    public function testForwardsHandlersWhenRejectedPromiseIsReturned()
    {
        $res = [];
        $p = new Promise();
        $p2 = new Promise();
        $p2->reject('foo');
        $p2->then(null, function ($v) use (&$res) { $res[] = 'A:' . $v; });
        $p->then(null, function () use ($p2, &$res) { $res[] = 'B'; return $p2; })
            ->then(null, function ($v) use (&$res) { $res[] = 'C:' . $v; });
        $p->reject('a');
        $p->then(null, function ($v) use (&$res) { $res[] = 'D:' . $v; });
        $this->loop->run();
        $this->assertEquals(['A:foo', 'B', 'D:a', 'C:foo'], $res);
    }
	
    public function testDoesNotForwardRejectedPromise()
    {
        $res = [];
        $p = new Promise();
        $p2 = new Promise();
        $p2->cancel();
        $p2->then(function ($v) use (&$res) { $res[] = "B:$v"; return $v; });
        $p->then(function ($v) use ($p2, &$res) { $res[] = "B:$v"; return $p2; })
            ->then(function ($v) use (&$res) { $res[] = 'C:' . $v; });
        $p->resolve('a');
        $p->then(function ($v) use (&$res) { $res[] = 'D:' . $v; });
        $this->loop->run();
        $this->assertEquals(['B:a', 'D:a'], $res);
    }
	
    public function testWaitBehaviorIsBasedOnLastPromiseInChain()
    {		
        $p3 = new Promise(function () use (&$p3) { $p3->resolve('Whoop'); });
        $p2 = new Promise(function () use (&$p2, $p3) { $p2->reject($p3); });
        $p = new Promise(function () use (&$p, $p2) { $p->reject($p2); });
        $this->assertEquals('Whoop', $p->wait());
    }	

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Cannot fulfill or reject a promise with itself
     */
    public function testCannotResolveWithSelf()
    {
        $p = new Promise();
        $p->resolve($p);
    }
	
    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Cannot fulfill or reject a promise with itself
     */
    public function testCannotRejectWithSelf()
    {
        $p = new Promise();
        $p->reject($p);
    }		
	
    public function testDone()
    {
        $finalValue = 0;
        $promise = new Promise();
        $promise->resolve(1);

        $promise->then(function ($value) use (&$finalValue) {
            $finalValue = $value + 2;

            return $finalValue;
        })->done(function ($value) use (&$finalValue) {
            $finalValue = $value + 5;
        });
        $this->loop->run();

        $this->assertEquals(8, $finalValue);
    }
	
    /**
     * @expectedException \Error
     * @exepctedExceptionMessage Call to a member function always() on null
     */	
    public function testDoneChainingError()
    {
        $finalValue = 0;
        $promise = new Promise();
        $promise->resolve(1);

        $promise->then(function ($value) use (&$finalValue) {
            $finalValue = $value + 2;

            return $finalValue;
        })->done(function ($value) use (&$finalValue) {
            $finalValue = $value + 5;

            return $finalValue;
        })->always(function ($value) use (&$finalValue) {
            $finalValue = $value + 4;
        });
        $this->loop->run();

        $this->assertEquals(8, $finalValue);
    }
	
	/**
     * This test should ignore the trigger_error() and check the return value
     */	
    public function testDoneFatalError()
    {
        set_error_handler(function () {});
		
        $promise = new Promise();
        $promise->resolve(1);

        $promise->then(function ($value) {
            throw new \Exception('Success');
        })->done(null, function ($value) use (&$fatalError){
			$fatalError = $value->getMessage().' has changed to an failure.';
            throw new \Exception('failure');
        });		
        $this->loop->run();

        $this->assertSame(1, $promise->getResult());
        $this->assertSame('Success has changed to an failure.', $fatalError);
    }
	
    public function testCatchPendingFail()
    {
        $finalValue = 0;
        $promise = new Promise();

        $promise->catch(function ($value) use (&$finalValue) {
            $finalValue = $value->getMessage() + 3;
        });

        $promise->reject(new Exception('5'));
        $this->loop->run();

        $this->assertEquals(8, $finalValue);
    }
	
    public function testAlways()
    {
        $finalValue = 0;
        $promise = new Promise();
        $promise->resolve(1);

        $subPromise = new Promise();

        $promise->then(function ($value) use ($subPromise) {
            return $subPromise;
        })->then(function ($value) use (&$finalValue) {
            $finalValue = $value + 4;

            return $finalValue;
        })->always(function ($value) use (&$finalValue) {
            $finalValue = $value + 4;
        });

        $subPromise->resolve(2);
        $this->loop->run();

        $this->assertEquals(10, $finalValue);
    }
	
    public function testSpread()
    {
        $promise = new Promise();

        $promise->then(function ($value) {
			$newvalue = $value;
			$newvalue[] = 4;
			$newvalue[] = 5;
			
            return $newvalue;
        })->spread(function ($v1, $v2, $v3, $v4, $v5) use (&$nv1, &$nv2, &$nv3, &$nv4, &$nv5) {
			$nv1 = $v1;
			$nv2 = $v2;
			$nv3 = $v3;
			$nv4 = $v4;
			$nv5 = $v5;
        });
		
        $promise->resolve([1, 2, 3]);
        $this->loop->run();

        $this->assertEquals([1, 2, 3], $promise->getResult());
		$this->assertSame(1, $nv1);
		$this->assertEquals(2, $nv2);
		$this->assertSame(3, $nv3);
		$this->assertEquals(4, $nv4);
		$this->assertSame(5, $nv5);
    }
}
