<?php
namespace Async\Tests;

use Exception;
use Async\Loop\Loop;
use Async\Promise\Promise;
use Async\Promise\Fulfilled;
use Async\Promise\PromiseInterface;
use Async\Promise\Rejected;
use Async\Promise\RejectionException;
use PHPUnit\Framework\TestCase;

class CoroutineTest extends TestCase
{
	private $loop = null;
	
	protected function setUp()
    {
		Loop::clearInstance();
		$this->loop = Promise::getLoop(true);
    }
	
    public function testYieldsFromCoroutine()
    {
        $promise = Promise::coroutine(function () {
            $value = (yield new Fulfilled('a'));
            yield  $value . 'b';
        });
        $promise->then(function ($value) use (&$result) { $result = $value; });
        $this->loop->run();
        $this->assertEquals('ab', $result);
    }

    public function testCanCatchExceptionsInCoroutine()
    {
        $promise = Promise::coroutine(function () {
            try {
                yield new Rejected('a');
                $this->fail('Should have thrown into the coroutine!');
            } catch (RejectionException $e) {
                $value = (yield new Fulfilled($e->getReason()));
                yield  $value . 'b';
            }
        });
        $promise->then(function ($value) use (&$result) { $result = $value; });
        $this->loop->run();
        $this->assertEquals(PromiseInterface::STATE_RESOLVED, $promise->getState());
        $this->assertEquals('ab', $result);
    }

    public function testRejectsParentExceptionWhenException()
    {
        $promise = Promise::coroutine(function () {
            yield new Fulfilled(0);
            throw new \Exception('a');
        });
        $promise->then(
            function () { $this->fail(); },
            function ($reason) use (&$result) { $result = $reason; }
        );
        $this->loop->run();
        $this->assertInstanceOf(\Exception::class, $result);
        $this->assertEquals('a', $result->getMessage());
    }

    public function testCanRejectFromRejectionCallback()
    {
        $promise = Promise::coroutine(function () {
            yield new Fulfilled(0);
            yield new Rejected('no!');
        });
        $promise->then(
            function () { $this->fail(); },
            function ($reason) use (&$result) { $result = $reason; }
        );
        $this->loop->run();
        $this->assertInstanceOf(RejectionException::class, $result);
        $this->assertEquals('no!', $result->getReason());
    }

    public function testCanAsyncReject()
    {
        $rej = new Promise();
        $promise = Promise::coroutine(function () use ($rej) {
            yield new Fulfilled(0);
            yield $rej;
        });
        $promise->then(
            function () { $this->fail(); },
            function ($reason) use (&$result) { $result = $reason; }
        );
        $rej->reject('no!');
        $this->loop->run();
        $this->assertInstanceOf(RejectionException::class, $result);
        $this->assertEquals('no!', $result->getReason());
    }

    public function testCanCatchAndThrowOtherException()
    {
        $promise = Promise::coroutine(function () {
            try {
                yield new Rejected('a');
                $this->fail('Should have thrown into the coroutine!');
            } catch (RejectionException $e) {
                throw new \Exception('foo');
            }
        });
        $promise->otherwise(function ($value) use (&$result) { $result = $value; });
        $this->loop->run();
        $this->assertEquals(PromiseInterface::STATE_REJECTED, $promise->getState());
        $this->assertContains('foo', $result->getMessage());
    }

    public function testCanCatchAndYieldOtherException()
    {
        $promise = Promise::coroutine(function () {
            try {
                yield new Rejected('a');
                $this->fail('Should have thrown into the coroutine!');
            } catch (RejectionException $e) {
                yield new Rejected('foo');
            }
        });
        $promise->otherwise(function ($value) use (&$result) { $result = $value; });
        $this->loop->run();
        $this->assertEquals(PromiseInterface::STATE_REJECTED, $promise->getState());
        $this->assertContains('foo', $result->getMessage());
    }

    public function createLotsOfSynchronousPromise()
    {
        return Promise::coroutine(function () {
            $value = 0;
            for ($i = 0; $i < 1000; $i++) {
                $value = (yield new Fulfilled($i));
            }
            yield $value;
        });
    }

    public function testLotsOfSynchronousDoesNotBlowStack()
    {
        $promise = $this->createLotsOfSynchronousPromise();
        $promise->then(function ($v) use (&$r) { $r = $v; });
        $this->loop->run();
        $this->assertEquals(999, $r);
    }

    private function createLotsOfFlappingPromise()
    {
        return Promise::coroutine(function () {
            $value = 0;
            for ($i = 0; $i < 1000; $i++) {
                try {
                    if ($i % 2) {
                        $value = (yield new Fulfilled($i));
                    } else {
                        $value = (yield new Rejected($i));
                    }
                } catch (\Exception $e) {
                    $value = (yield new Fulfilled($i));
                }
            }
            yield $value;
        });
    }

    public function testLotsOfTryCatchingDoesNotBlowStack()
    {
        $promise = $this->createLotsOfFlappingPromise();
        $promise->then(function ($v) use (&$r) { $r = $v; });
        $this->loop->run();
        $this->assertEquals(999, $r);
    }

    public function testAsyncPromisesWithCorrectlyYieldedValues()
    {
        $promises = [
            new Promise(),
            new Promise(),
            new Promise()
        ];

        $promise = Promise::coroutine(function () use ($promises) {
            $value = null;
            $this->assertEquals('skip', (yield new Fulfilled('skip')));
            foreach ($promises as $idx => $p) {
                $value = (yield $p);
                $this->assertEquals($value, $idx);
                $this->assertEquals('skip', (yield new Fulfilled('skip')));
            }
            $this->assertEquals('skip', (yield new Fulfilled('skip')));
            yield $value;
        });

        $promises[0]->resolve(0);
        $promises[1]->resolve(1);
        $promises[2]->resolve(2);

        $promise->then(function ($v) use (&$r) { $r = $v; });
        $this->loop->run();
        $this->assertEquals(2, $r);
    }

    public function testYieldFinalWaitablePromise()
    {
        $p1 = new Promise(function () use (&$p1) {
            $p1->resolve('skip me');
        });
        $p2 = new Promise(function () use (&$p2) {
            $p2->resolve('hello!');
        });
        $co = Promise::coroutine(function() use ($p1, $p2) {
            yield $p1;
            yield $p2;
        });
        $this->loop->run();
        $this->assertEquals('hello!', $co->wait());
    }

    public function testCanYieldFinalPendingPromise()
    {
        $p1 = new Promise();
        $p2 = new Promise();
        $co = Promise::coroutine(function() use ($p1, $p2) {
            yield $p1;
            yield $p2;
        });
        $p1->resolve('a');
        $p2->resolve('b');
        $co->then(function ($value) use (&$result) { $result = $value; });
        $this->loop->run();
        $this->assertEquals('b', $result);
    }

    public function testCanNestYieldsAndFailures()
    {
        $p1 = new Promise();
        $p2 = new Promise();
        $p3 = new Promise();
        $p4 = new Promise();
        $p5 = new Promise();
        $co = Promise::coroutine(function() use ($p1, $p2, $p3, $p4, $p5) {
            try {
                yield $p1;
            } catch (\Exception $e) {
                yield $p2;
                try {
                    yield $p3;
                    yield $p4;
                } catch (\Exception $e) {
                    yield $p5;
                }
            }
        });
        $p1->reject('a');
        $p2->resolve('b');
        $p3->resolve('c');
        $p4->reject('d');
        $p5->resolve('e');
        $co->then(function ($value) use (&$result) { $result = $value; });
        $this->loop->run();
        $this->assertEquals('e', $result);
    }

    public function testCanYieldErrorsAndSuccessesWithoutRecursion()
    {
        $promises = [];
        for ($i = 0; $i < 20; $i++) {
            $promises[] = new Promise();
        }

        $co = Promise::coroutine(function() use ($promises) {
            for ($i = 0; $i < 20; $i += 4) {
                try {
                    yield $promises[$i];
                    yield $promises[$i + 1];
                } catch (\Exception $e) {
                    yield $promises[$i + 2];
                    yield $promises[$i + 3];
                }
            }
        });

        for ($i = 0; $i < 20; $i += 4) {
            $promises[$i]->resolve($i);
            $promises[$i + 1]->reject($i + 1);
            $promises[$i + 2]->resolve($i + 2);
            $promises[$i + 3]->resolve($i + 3);
        }

        $co->then(function ($value) use (&$result) { $result = $value; });
        $this->loop->run();
        $this->assertEquals('19', $result);
    }

    public function testCanWaitOnPromiseAfterFulfilled()
    {
        $f = function () {
            static $i = 0;
            $i++;
            return $p = new Promise(function () use (&$p, $i) {
                $p->resolve($i . '-bar');
            });
        };

        $promises = [];
        for ($i = 0; $i < 20; $i++) {
            $promises[] = $f();
        }

        $p = Promise::coroutine(function () use ($promises) {
            yield new Fulfilled('foo!');
            foreach ($promises as $promise) {
                yield $promise;
            }
        });

        $this->assertEquals('20-bar', $p->wait());
    }
	
    public function testLotsOfSynchronousWaitDoesNotBlowStack()
    {
        $promise = $this->createLotsOfSynchronousPromise();
        $promise->then(function ($v) use (&$r) { $r = $v; });
        $this->assertEquals(999, $promise->wait());
        $this->assertEquals(999, $r);
    }
	
    public function testLotsOfTryCatchingWaitingDoesNotBlowStack()
    {
        $promise = $this->createLotsOfFlappingPromise();
        $promise->then(function ($v) use (&$r) { $r = $v; });
        $this->assertEquals(999, $promise->wait());
        $this->assertEquals(999, $r);
    }
    
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testNonGenerator()
    {
        Promise::coroutine(function () {});
    }

    public function testBasicCoroutine()
    {
        $start = 0;

        Promise::coroutine(function () use (&$start) {
            ++$start;
            yield;
        });

        $this->assertEquals(1, $start);
    }

    public function testFulfilledPromise()
    {
        $start = 0;
        $promise = new Promise(function ($resolve, $reject) {
            $resolve(2);
        });

        Promise::coroutine(function () use (&$start, $promise) {
            ++$start;
            $start += yield $promise;
        });

        $this->loop->run();
        $this->assertEquals(3, $start);
    }

    public function testRejectedPromise()
    {
        $start = 0;
        $promise = new Promise(function ($resolve, $reject) {
            $reject(new Exception('2'));
        });

        Promise::coroutine(function () use (&$start, $promise) {
            ++$start;
            try {
                $start += yield $promise;
                // This line is unreachable, but it's our control
                $start += 4;
            } catch (\Exception $e) {
                $start += $e->getMessage();
            }
        });

        $this->loop->run();
        $this->assertEquals(3, $start);
    }

    public function testRejectedPromiseException()
    {
        $start = 0;
        $promise = new Promise(function ($resolve, $reject) {
            $reject(new \LogicException('2'));
        });

        Promise::coroutine(function () use (&$start, $promise) {
            ++$start;
            try {
                $start += yield $promise;
                // This line is unreachable, but it's our control
                $start += 4;
            } catch (\LogicException $e) {
                $start += $e->getMessage();
            }
        });

        $this->loop->run();
        $this->assertEquals(3, $start);
    }

    public function testFulfilledPromiseAsync()
    {
        $start = 0;
        $promise = new Promise();
        Promise::coroutine(function () use (&$start, $promise) {
            ++$start;
            $start += yield $promise;
        });
        $this->loop->run();

        $this->assertEquals(1, $start);

        $promise->resolve(2);
        $this->loop->run();

        $this->assertEquals(3, $start);
    }

    public function testRejectedPromiseAsync()
    {
        $start = 0;
        $promise = new Promise();
        Promise::coroutine(function () use (&$start, $promise) {
            ++$start;
            try {
                $start += yield $promise;
                // This line is unreachable, but it's our control
                $start += 4;
            } catch (\Exception $e) {
                $start += $e->getMessage();
            }
        });

        $this->assertEquals(1, $start);

        $promise->reject(new \Exception((string) 2));
        $this->loop->run();

        $this->assertEquals(3, $start);
    }

    public function testCoroutineException()
    {
        $start = 0;
        Promise::coroutine(function () use (&$start) {
            ++$start;
            $start += yield 2;

            throw new \Exception('4');
        })->otherwise(function ($e) use (&$start) {
            $start += $e->getMessage();
        });
        $this->loop->run();

        $this->assertEquals(7, $start);
    }

    public function testDeepException()
    {
        $start = 0;
        $promise = new Promise();
        Promise::coroutine(function () use (&$start, $promise) {
            ++$start;
            $start += yield $promise;
        })->otherwise(function ($e) use (&$start) {
            $start += $e->getMessage();
        });

        $this->assertEquals(1, $start);

        $promise->reject(new \Exception((string) 2));
        $this->loop->run();

        $this->assertEquals(3, $start);
    }
}
