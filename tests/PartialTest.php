<?php
namespace Async\Tests;

use Exception;
use Async\Loop\Loop;
use Async\Promise\Promise;
use Async\Promise\Rejected;
use Async\Promise\PromiseInterface;
use PHPUnit\Framework\TestCase;

class PartialTest extends TestCase
{
	private $loop; 
	protected function setUp()
    {
        $this->markTestSkipped('Pause these tests for now.');
		Loop::clearInstance();
		Promise::clearLoop();				
        $this->loop = Promise::getLoop(true);
    }		
		
    public function testReturnsPromiseForPromise()
    {
        $p = new Promise();
        $this->assertSame($p, Promise::resolver($p));
    }

    /**
     * @expectedException InvalidArgumentException
     */	 
    public function testReturnsPromiseForThennable()
    {
        $p = new Thennable();
        $wrapped = Promise::resolver($p);
    }
	
    public function testReturnsRejection()
    {
        $p = Promise::rejecter('fail');
        $this->assertInstanceOf(Rejected::class, $p);
        $this->assertEquals('fail', $this->readAttribute($p, 'reason'));
    }
	
    public function testReturnsPromisesAsIsInRejectionFor()
    {
        $a = new Promise();
        $b = Promise::rejecter($a);
        $this->assertSame($a, $b);
	}
			
    public function testResolver()
    {
        $finalValue = 0;

        $promise = Promise::resolver(1);
        $promise->then(function ($value) use (&$finalValue) {
            $finalValue = $value;
        });

        $this->assertEquals(0, $finalValue);
        $this->loop->run();
        $this->assertEquals(1, $finalValue);
    }

    /**
     * @expectedException \Exception
     */
    public function testResolvePromise()
    {
        $finalValue = 0;

        $promise = new Promise();
        $promise->reject(new \Exception('uh oh'));

        $newPromise = $promise->resolve($promise);
        $newPromise->wait();
    }

    public function testRejecter()
    {
        $finalValue = 0;

        $promise = Promise::rejecter(new Exception('1'));
        $promise->then(function ($value) use (&$finalValue) {
            $finalValue = 'im broken';
        }, function ($reason) use (&$finalValue) {
            $finalValue = $reason;
        });

        $this->assertEquals(0, $finalValue);
        $this->loop->run();
        $this->assertEquals(1, $finalValue->getMessage());
    }
	
	public function testCoroutine()
    {
		$promise = Promise::coroutine(function () {
          $value = (yield  Promise::resolver('a'));
          try {
              $value = (yield  Promise::resolver($value . 'b'));
          } catch (\Exception $e) {
              // The promise was rejected.
          }
          yield $value . 'c';
		}); 
		
		$promise->then(function ($v) use(&$result) { $result = $v; });
		$this->loop->run();
		$this->assertSame('abc', $result);
	}
}
