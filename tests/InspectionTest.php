<?php
namespace Async\Tests;

use Exception;
use Async\Loop\Loop;
use Async\Promise\Promise;
use Async\Promise\PromiseInterface;
use Async\Promise\RejectionException;
use PHPUnit\Framework\TestCase;

class InspectionTest extends TestCase
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
        Promise::getLoop(true);
    }
		
    public function testCanWaitOnErroredPromises()
    {
        $p1 = new Promise(function () use (&$p1) { $p1->reject('a'); });
        $p2 = new Promise(function () use (&$p2) { $p2->resolve('b'); });
        $p3 = new Promise(function () use (&$p3) { $p3->resolve('c'); });
        $p4 = new Promise(function () use (&$p4) { $p4->reject('d'); });
        $p5 = new Promise(function () use (&$p5) { $p5->resolve('e'); });
        $p6 = new Promise(function () use (&$p6) { $p6->reject('f'); });

        $co = Promise::coroutine(function() use ($p1, $p2, $p3, $p4, $p5, $p6) {
            try {
                yield $p1;
            } catch (\Exception $e) {
                yield $p2;
                try {
                    yield $p3;
                    yield $p4;
                } catch (\Exception $e) {
                    yield $p5;
                    yield $p6;
                }
            }
        });

        $res = $p1->inspect($co);
        $this->assertEquals('f', $res['reason']);
    }

    public function testCoroutineOtherwiseIntegrationTest()
    {
        $a = new Promise();
        $b = new Promise();
        $promise = Promise::coroutine(function () use ($a, $b) {
            // Execute the pool of commands concurrently, and process errors.
            yield $a;
            yield $b;
        })->otherwise(function (\Exception $e) {
            // Throw errors from the operations as a specific Multipart error.
            throw new \OutOfBoundsException('a', 0, $e);
        });
        $a->resolve('a');
        $b->reject('b');
        $reason = $a->inspect($promise)['reason'];
        $this->assertInstanceOf(\OutOfBoundsException::class, $reason);
        $this->assertInstanceOf(RejectionException::class, $reason->getPrevious());
    }
}
