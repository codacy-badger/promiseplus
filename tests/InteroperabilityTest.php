<?php
namespace Async\Tests;

use Exception;
use Async\Promise\Promise;
use Amp\Loop\DriverFactory;
use React\EventLoop\Factory;
use GuzzleHttp\Promise\TaskQueue;
use PHPUnit\Framework\TestCase;

class InteroperabilityTest extends TestCase
{			
	private $loop = null;
	
	protected function setUp()
    {
        $this->markTestSkipped('These tests require `react/event-loop`, `guzzlehttp/promises`, and `amphp/amp` to composer development environment.');
		Promise::clearLoop();
    }
	
    public function testGuzzleHttpExecutesTasksInOrder()
    {
		$this->loop = new TaskQueue(false);
		$this->assertNotNull($this->loop);
		$this->assertInstanceof(\GuzzleHttp\Promise\TaskQueue::class, $this->loop);
		$promise = new Promise($this->loop);
		$this->assertTrue($promise->isLoopAvailable($this->loop));
        $called = [];
        $promise->implement(function () use (&$called) { $called[] = 'a'; });
        $promise->implement(function () use (&$called) { $called[] = 'b'; });
        $promise->implement(function () use (&$called) { $called[] = 'c'; });
        $this->loop->run();
        $this->assertEquals(['a', 'b', 'c'], $called);
    }	
	
    public function testGuzzleHttpSuccess()
    {
		$this->loop = new TaskQueue(false);
        $promise = new Promise($this->loop);
        $finalValue = 0;
        $promise->resolve(1);

        $promise->then(function ($value) use (&$finalValue) {
            $finalValue = $value + 2;
        });
        $this->loop->run();

        $this->assertEquals(3, $finalValue);
    }
	
    public function testReactExecutesTasksInOrder()
    {
		$this->loop = Factory::create();
		$this->assertNotNull($this->loop);
		$this->assertInstanceof(\React\EventLoop\StreamSelectLoop::class, $this->loop);
		$promise = new Promise($this->loop);
		$this->assertTrue($promise->isLoopAvailable($this->loop));
        $called = [];
        $promise->implement(function () use (&$called) { $called[] = 'a'; });
        $promise->implement(function () use (&$called) { $called[] = 'b'; });
        $promise->implement(function () use (&$called) { $called[] = 'c'; });
        $this->loop->run();
        $this->assertEquals(['a', 'b', 'c'], $called);
    }	
	
    public function testReactSuccess()
    {
		$this->loop = Factory::create();
        $promise = new Promise($this->loop);
        $finalValue = 0;
        $promise->resolve(1);

        $promise->then(function ($value) use (&$finalValue) {
            $finalValue = $value + 2;
        });
        $this->loop->run();

        $this->assertEquals(3, $finalValue);
    }
		
    public function testAmpExecutesTasksInOrder()
    {
		\Amp\Loop::set((new DriverFactory)->create());
		$this->loop = \Amp\Loop::get();
		$this->assertNotNull($this->loop);
		$this->assertInstanceof(\Amp\Loop\NativeDriver::class, $this->loop);
		$promise = new Promise($this->loop);
		$this->assertTrue($promise->isLoopAvailable($this->loop));
        $called = [];
        $promise->implement(function () use (&$called) { $called[] = 'a'; });
        $promise->implement(function () use (&$called) { $called[] = 'b'; });
        $promise->implement(function () use (&$called) { $called[] = 'c'; });
        $this->loop->run();
        $this->assertEquals(['a', 'b', 'c'], $called);
    }	
	
    public function testAmpSuccess()
    {
		\Amp\Loop::set((new DriverFactory)->create());
		$this->loop = \Amp\Loop::get();
        $promise = new Promise($this->loop);
        $finalValue = 0;
        $promise->resolve(1);

        $promise->then(function ($value) use (&$finalValue) {
            $finalValue = $value + 2;
        });
        $this->loop->run();

        $this->assertEquals(3, $finalValue);
    }
}
