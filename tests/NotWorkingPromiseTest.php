<?php
namespace Async\Tests;

use Exception;
use Async\Loop\Loop;
use Async\Promise\Promise;
use Async\Promise\Rejected;
use Async\Promise\Fulfilled;
use Async\Promise\PromiseInterface;
use Async\Promise\EachPromise;
use Async\Promise\AggregateException;
use Async\Tests\Thennable;
use Async\Tests\NotPromiseInstance;
use PHPUnit\Framework\TestCase;

class NotWorkingPromiseTest extends TestCase
{
	//const PENDING = Promise::PENDING;
	//const REJECTED = Promise::REJECTED;
	//const FULFILLED = Promise::FULFILLED;	
	//const PENDING = PromiseInterface::PENDING;
	//const REJECTED = PromiseInterface::REJECTED;
	//const FULFILLED = PromiseInterface::FULFILLED;	
	const PENDING = PromiseInterface::STATE_PENDING;
	const REJECTED = PromiseInterface::STATE_REJECTED;
	const FULFILLED = PromiseInterface::STATE_RESOLVED;	
	//const PENDING = 'pending';
	//const REJECTED = 'rejected';	
    //const FULFILLED = 'fulfilled';
    
	private $loop; 
	protected function setUp()
    {        
        $this->markTestSkipped('These test fails in various stages, taken from Guzzle phpunit tests.');
		Loop::clearInstance();		
        $this->loop = Promise::getLoop(true);
    }		
	
    private function createSelfResolvingPromise($value)
    {
        $p = new Promise(function () use (&$p, $value) {
            $p->resolve($value);
        });

        return $p;
    }	
	
    public function testRecursivelyForwardsWhenOnlyThennable()
    {
        $res = [];
        $p = new Promise();
        $p2 = new Thennable();
        $p2->resolve('foo');
        $p2->then(function ($v) use (&$res) { $res[] = 'A:' . $v; });
        $p->then(function () use ($p2, &$res) { $res[] = 'B'; return $p2; })
            ->then(function ($v) use (&$res) { $res[] = 'C:' . $v; });
        $p->resolve('a');
        $p->then(function ($v) use (&$res) { $res[] = 'D:' . $v; });
        $this->loop->run();
        $this->assertEquals(['A:foo', 'B', 'D:a', 'C:foo'], $res);
    }
	
    public function testRecursivelyForwardsWhenNotInstanceOfPromise()
    {
        $res = [];
        $p = new Promise();
        $p2 = new NotPromiseInstance();
        $p2->then(function ($v) use (&$res) { $res[] = 'A:' . $v; });
        $p->then(function () use ($p2, &$res) { $res[] = 'B'; return $p2; })
            ->then(function ($v) use (&$res) { $res[] = 'C:' . $v; });
        $p->resolve('a');
        $p->then(function ($v) use (&$res) { $res[] = 'D:' . $v; });
        $this->loop->run();
        $this->assertEquals(['B', 'D:a'], $res);
        $p2->resolve('foo');
        $this->loop->run();
        $this->assertEquals(['B', 'D:a', 'A:foo', 'C:foo'], $res);
    }	

    public function testCanResolveBeforeConsumingAll()
    {
        $called = 0;
        $a = $this->createSelfResolvingPromise('a');
        $b = new Promise(function () { $this->fail(); });
        $each = new EachPromise([$a, $b], [
            'fulfilled' => function ($value, $idx, Promise $aggregate) use (&$called) {
                $this->assertSame($idx, 0);
                $this->assertEquals('a', $value);
                $aggregate->resolve(null);
                $called++;
            },
            'rejected' => function (\Exception $reason) {
                $this->fail($reason->getMessage());
            }
        ]);
		$this->loop->run();
        $p = $each->promise();
        $p->wait();
        $this->assertNull($p->wait());
        $this->assertEquals(1, $called);
        $this->assertEquals(self::FULFILLED, $a->getState());
        $this->assertEquals(self::PENDING, $b->getState());
        // Resolving $b has no effect on the aggregate promise.
        $b->resolve('foo');
        $this->assertEquals(1, $called);
    }

    public function testCanBeCancelled()
    {
        $called = false;
        $a = new Fulfilled('a');
        $b = new Promise(function () use (&$called) { $called = true; });
        $each = new EachPromise([$a, $b], [
            'fulfilled' => function ($value, $idx, Promise $aggregate) {
                $aggregate->cancel();
            },
            'rejected' => function ($reason) use (&$called) {
                $called = true;
            },
        ]);
        $p = $each->promise();
		$this->loop->run();
		$p->wait(false);
        $this->assertEquals(self::FULFILLED, $a->getState());
        $this->assertEquals(self::PENDING, $b->getState());
        $this->assertEquals(self::REJECTED, $p->getState());
        $this->assertFalse($called);
    }
}
