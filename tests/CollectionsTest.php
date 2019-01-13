<?php
namespace Async\Tests;

use Exception;
use Async\Loop\Loop;
use Async\Promise\Promise;
use Async\Promise\PromiseInterface;
use PHPUnit\Framework\TestCase;

class CollectionsTest extends TestCase
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
		Loop::clearInstance();		
        $this->loop = Promise::getLoop(true);
    }
		
    public function testEvery()
    {
        $promise1 = new Promise();
        $promise2 = new Promise();

        $finalValue = 0;
        Promise::every($promise1, $promise2)->then(function ($value) use (&$finalValue) {
            $finalValue = $value;
        });

        $promise1->resolve(1);
        $this->loop->run();
        $this->assertEquals(0, $finalValue);

        $promise2->resolve(2);
        $this->loop->run();
        $this->assertEquals([1, 2], $finalValue);
    }
	
    public function testAll()
    {
        $promise1 = new Promise();
        $promise2 = new Promise();

        $finalValue = 0;
        Promise::all($promise1, $promise2)->then(function ($value) use (&$finalValue) {
            $finalValue = $value;
        });

        $promise1->resolve(1);
        $this->loop->run();
        $this->assertEquals(0, $finalValue);

        $promise2->resolve(2);
        $this->loop->run();
        $this->assertEquals([1, 2], $finalValue);
    }

    public function testAllEmptyArray()
    {
        $finalValue = Promise::all()->wait();

        $this->assertEquals([], $finalValue);
    }

    public function testAllAggregatesSortedArray()
    {
        $a = new Promise();
        $b = new Promise();
        $c = new Promise();
        $d = Promise::all($a, $b, $c);
        $b->resolve('b');
        $a->resolve('a');
        $c->resolve('c');
        $d->then(
            function ($value) use (&$result) { $result = $value; },
            function ($reason) use (&$result) { $result = $reason; }
        );
        $this->loop->run();
        $this->assertEquals(['a', 'b', 'c'], $result);
    }
	
    public function testEveryAggregatesSortedArray()
    {
        $a = new Promise();
        $b = new Promise();
        $c = new Promise();
        $d = Promise::every($a, $b, $c);
        $b->resolve('b');
        $a->resolve('a');
        $c->resolve('c');
        $d->then(
            function ($value) use (&$result) { $result = $value; },
            function ($reason) use (&$result) { $result = $reason; }
        );
        $this->loop->run();
        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    public function testAllReject()
    {
        $promise1 = new Promise();
        $promise2 = new Promise();

        $finalValue = 0;
        Promise::all($promise1, $promise2)->then(
            function ($value) use (&$finalValue) {
                $finalValue = 'foo';

                return 'test';
            },
            function ($value) use (&$finalValue) {
                $finalValue = $value;
            }
        );

        $promise1->reject(new Exception('1'));
        $this->loop->run();
        $this->assertEquals('1', $finalValue->getMessage());
        $promise2->reject(new Exception('2'));
        $this->loop->run();
        $this->assertEquals(1, $finalValue->getMessage());
    }

    public function testAllRejectThenResolve()
    {
        $promise1 = new Promise();
        $promise2 = new Promise();

        $finalValue = 0;
        Promise::all($promise1, $promise2)->then(
            function ($value) use (&$finalValue) {
                $finalValue = 'foo';

                return 'test';
            },
            function ($value) use (&$finalValue) {
                $finalValue = $value;
            }
        );

        $promise1->reject(new Exception('1'));
        $this->loop->run();
        $this->assertEquals(1, $finalValue->getMessage());
        $promise2->resolve(new Exception('2'));
        $this->loop->run();
        $this->assertEquals(1, $finalValue->getMessage());
    }
	
    public function testAllThrowsWhenAnyRejected()
    {
        $a = new Promise();
        $b = new Promise();
        $c = new Promise();
        $d = Promise::all($a, $b, $c);
        $a->resolve('c');
        $b->reject('fail');
        $c->resolve('b');
        $d->then(
            function ($value) use (&$result) { $result = $value; },
            function ($reason) use (&$result) { $result = $reason; }
        );
        $this->loop->run();
        $this->assertEquals('fail', $result);
    }
	
    public function testFirst()
    {
        $promise1 = new Promise();
        $promise2 = new Promise();

        $finalValue = 0;
        Promise::first($promise1, $promise2)->then(
            function ($value) use (&$finalValue) {
                $finalValue = $value;
            },
            function ($value) use (&$finalValue) {
                $finalValue = $value;
            }
        );

        $promise1->resolve(1);
        $this->loop->run();
        $this->assertEquals(1, $finalValue);
        $promise2->resolve(2);
        $this->loop->run();
        $this->assertEquals(1, $finalValue);
    }
	
    public function testRace()
    {
        $promise1 = new Promise();
        $promise2 = new Promise();

        $finalValue = 0;
        Promise::race($promise1, $promise2)->then(
            function ($value) use (&$finalValue) {
                $finalValue = $value;
            },
            function ($value) use (&$finalValue) {
                $finalValue = $value;
            }
        );

        $promise1->resolve(1);
        $this->loop->run();
        $this->assertEquals(1, $finalValue);
        $promise2->resolve(2);
        $this->loop->run();
        $this->assertEquals(1, $finalValue);
    }

    public function testRaceReject()
    {
        $promise1 = new Promise();
        $promise2 = new Promise();

        $finalValue = 0;
        Promise::race($promise1, $promise2)->then(
            function ($value) use (&$finalValue) {
                $finalValue = $value;
            },
            function ($value) use (&$finalValue) {
                $finalValue = $value;
            }
        );

        $promise1->reject(new Exception('1'));
        $this->loop->run();
        $this->assertEquals(1, $finalValue->getMessage());
        $promise2->reject(new Exception('2'));
        $this->loop->run();
        $this->assertEquals(1, $finalValue->getMessage());
    }
	
    public function testSomeAggregatesSortedArrayWithMax()
    {
        $a = new Promise();
        $b = new Promise();
        $c = new Promise();
        $d = Promise::some([$a, $b, $c], 2);
        $b->resolve('b');
        $c->resolve('c');
        $a->resolve('a');
        $d->then(function ($value) use (&$result) { $result = $value; });
        $this->loop->run();
        $this->assertEquals(['b', 'c'], $result);
    }
	
    public function testFewAggregatesSortedArrayWithMax()
    {
        $a = new Promise();
        $b = new Promise();
        $c = new Promise();
        $d = Promise::few(2, $a, $b, $c);
        $b->resolve('b');
        $c->resolve('c');
        $a->resolve('a');
        $d->then(function ($value) use (&$result) { $result = $value; });
        $this->loop->run();
        $this->assertEquals(['b', 'c'], $result);
    }

    public function testSomeRejectsWhenTooManyRejections()
    {
        $a = new Promise();
        $b = new Promise();
        $d = Promise::some([$a, $b], 2);
        $a->reject('bad');
        $b->resolve('good');
        $this->loop->run();
        $this->assertEquals($a::STATE_REJECTED, $d->getState());
        $d->then(null, function ($reason) use (&$called) {
            $called = $reason;
        });
        $this->loop->run();
        $this->assertInstanceOf(\LengthException::class, $called);
        $this->assertContains('bad', $called->getMessage());
    }
	
    public function testFewRejectsWhenTooManyRejections()
    {
        $a = new Promise();
        $b = new Promise();
        $d = Promise::few(2, $a, $b);
        $a->reject('bad');
        $b->resolve('good');
        $this->loop->run();
        $this->assertEquals($a::STATE_REJECTED, $d->getState());
        $d->then(null, function ($reason) use (&$called) {
            $called = $reason;
        });
        $this->loop->run();
        $this->assertInstanceOf(\InvalidArgumentException::class, $called);
        $this->assertContains('bad', $called->getMessage());
    }

    public function testCanWaitUntilSomeCountIsSatisfied()
    {
        $a = new Promise(function () use (&$a) { $a->resolve('a'); });
        $b = new Promise(function () use (&$b) { $b->resolve('b'); });
        $c = new Promise(function () use (&$c) { $c->resolve('c'); });
        $d = Promise::some([$a, $b, $c], 2);
        $this->assertEquals(['a', 'b'], $d->wait());
    }

    /**
     * @expectedException \LengthException
     * @expectedExceptionMessage Not enough promises to fulfill count
     */
    public function testThrowsIfImpossibleToWaitForSomeCount()
    {
        $a = new Promise(function () use (&$a) { $a->resolve('a'); });
        $d = Promise::some([$a], 2);
        $d->wait();
    }

    /**
     * @expectedException \LengthException
     * @expectedExceptionMessage Not enough promises to fulfill count
     */
    public function testThrowsIfResolvedWithoutCountTotalResults()
    {
        $a = new Promise();
        $b = new Promise();
        $d = Promise::some([$a, $b], 3);
        $a->resolve('a');
        $b->resolve('b');
        $d->wait();
    }
	
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Not enough promises to fulfill count
     */
    public function testFewThrowsIfResolvedWithoutCountTotalResults()
    {
        $a = new Promise();
        $b = new Promise();
        $d = Promise::few(3, $a, $b);
        $a->resolve('a');
        $b->resolve('b');
        $d->wait();
    }
		
    public function testAnyReturnsFirstMatch()
    {
        $a = new Promise();
        $b = new Promise();
        $c = Promise::any($a, $b);
        $b->resolve('b');
        $a->resolve('a');
        $this->loop->run();
        $this->assertEquals(self::FULFILLED, $c->getState());
        $c->then(function ($value) use (&$result) { $result = $value; });
        $this->loop->run();
        $this->assertEquals('b', $result);
    }
}
