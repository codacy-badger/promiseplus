<?php
namespace Async\Tests;

use Async\Loop\Loop;
use Async\Promise\Promise;
use Async\Promise\Coroutine;
use Async\Promise\PromiseInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

function createPromise($value) {
	return Promise::resolver($value);
}

class CoroutinePromiseTest extends TestCase
{
	private $loop; 
	protected function setUp()
    {        
		Loop::clearInstance();		
        $this->loop = Promise::getLoop(true);
    }		
		
    /**
     * @dataProvider promiseInterfaceMethodProvider
     *
     * @param string $method
     * @param array $args
     */
    public function testShouldProxyPromiseMethodsToResultPromise($method, $args = [])
    {
        $coroutine = Promise::coroutine(function () { yield 0; });
        $mockPromise = $this->getMockForAbstractClass(PromiseInterface::class);
        call_user_func_array([$mockPromise->expects($this->once())->method($method), 'with'], $args);

        $resultPromiseProp = (new ReflectionClass(Coroutine::class))->getProperty('result');
        $resultPromiseProp->setAccessible(true);
        $resultPromiseProp->setValue($coroutine, $mockPromise);

        call_user_func_array([$coroutine, $method], $args);
    }

    public function promiseInterfaceMethodProvider()
    {
        return [
            ['then', [null, null]],
            ['success', [function () {}]],
            ['failure', [function () {}]],
            ['otherwise', [function () {}]],
            ['wait', [true]],
            ['cancel', []],
            ['getState', []],
            ['resolve', [null]],
            ['reject', [null]],
        ];
    }
    
    public function testShouldCancelResultPromiseAndOutsideCurrentPromise()
    {
        $coroutine = Promise::coroutine(function () { yield 0; });

        $mockPromises = [
            'result' => $this->getMockForAbstractClass(PromiseInterface::class),
            'currentPromise' => $this->getMockForAbstractClass(PromiseInterface::class),
        ];
        foreach ($mockPromises as $propName => $mockPromise) {
            /**
             * @var $mockPromise \PHPUnit_Framework_MockObject_MockObject
             */
            $mockPromise->expects($this->once())
                ->method('cancel')
                ->with();

            $promiseProp = (new ReflectionClass(Coroutine::class))->getProperty($propName);
            $promiseProp->setAccessible(true);
            $promiseProp->setValue($coroutine, $mockPromise);
        }

        $coroutine->cancel();
    }

    public function testWaitShouldResolveChainedCoroutine()
    {
        $promisor = function () {
            return Promise::coroutine(function () {
                yield $promise = new Promise(function () use (&$promise) {
                    $promise->resolve(1);
                });
            });
        };

        $promise = $promisor()->then($promisor)->then($promisor);

        $this->assertSame(1, $promise->wait());
    }
		
    public function testWaitShouldHandleIntermediateErrors()
    {
        $promise = Promise::coroutine(function () {
            yield $promise = new Promise(function () use (&$promise) {
                $promise->resolve(1);
            });
        })
		->then(function () {
            return Promise::coroutine(function () {
                yield $promise = new Promise(function () use (&$promise) {
                    $promise->reject(new \Exception);
                });
            });
        })
		->catch(function (\Exception $error = null) {
            if (!$error) {
                self::fail(' Error did not propagate. ');
            }
            return 3;
        });

        $this->assertSame(3, $promise->wait());
    }
}
