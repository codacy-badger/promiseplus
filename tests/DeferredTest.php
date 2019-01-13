<?php
namespace Async\Tests;

use PHPUnit\Framework\TestCase;
use Async\Promise\Deferred;
use Async\Promise\UnexpectedTypeExceptionDeferred;

class DeferredTest extends TestCase
{
    private $defer;

    protected function setUp()
    {
        $this->defer = new Deferred();
    }

    protected function tearDown()
    {
        unset($this->defer);
    }

    /**
     * @dataProvider getMethodAndKey
     */
    public function testCallbackOrder($method, $expected)
    {
        $log = array();

        $this->defer->always(function() use(& $log) {
            $log[] = 'always';
            $log[] = func_get_args();
        });
		$this->defer->success(function() use(& $log) {
            $log[] = 'success';
            $log[] = func_get_args();
        });
		$this->defer->failure(function() use(& $log) {
            $log[] = 'failure';
            $log[] = func_get_args();
        });

        $this->defer->$method(1, 2, 3);

        $this->assertEquals(array(
            'always',
            array(1, 2, 3),
            $expected,
            array(1, 2, 3),
        ), $log);
    }

    /**
     * @dataProvider getMethodAndKey
     */
    public function testThen($method, $expected)
    {
        $log = array();

        $this->defer->then(function() use(& $log) {
            $log[] = 'success';
        }, function() use(& $log) {
            $log[] = 'failure';
        });

        $this->defer->$method();

        $this->assertEquals(array($expected), $log);
    }

    /**
     * @dataProvider getMethod
     */
    public function testMultipleResolve($method)
    {
        $log = array();

        $this->defer->always(function() use(& $log) {
            $log[] = 'always';
        });

        $this->defer->$method();
        $this->defer->$method();

        $this->assertEquals(array('always'), $log);
    }

    /**
     * @dataProvider getMethodAndInvalid
     */
    public function testInvalidResolve($method, $invalid)
    {
        $this->expectException('LogicException', 'that has already been');

        $this->defer->$method();
        $this->defer->$invalid();
    }

    /**
     * @dataProvider getMethodAndQueue
     */
    public function testAlreadyResolved($resolve, $queue, $expect = true)
    {
        // resolve the object
        $this->defer->$resolve();

        $log = array();
        $this->defer->$queue(function() use(& $log, $queue) {
            $log[] = $queue;
        });

        $this->assertEquals($expect ? array($queue) : array(), $log);
    }

    /**
     * @dataProvider getMethodAndInvalidCallback
     */
    public function testInvalidCallback($method, $invalid)
    {
        $this->expectException('Async\Promise\UnexpectedTypeException', 'callable');

        $this->defer->$method($invalid);
    }

    // providers

    public function getMethodAndKey()
    {
        return array(
            array('resolve', 'success'),
            array('reject', 'failure'),
        );
    }

    public function getMethodAndInvalid()
    {
        return array(
            array('resolve', 'reject'),
            array('reject', 'resolve'),
        );
    }

    public function getMethodAndQueue()
    {
        return array(
            array('resolve', 'always'),
            array('resolve', 'success'),
            array('resolve', 'failure', false),
            array('reject', 'always'),
            array('reject', 'success', false),
            array('reject', 'failure'),
        );
    }

    public function getMethodAndInvalidCallback()
    {
        return array(
            array('always', 'foo!'),
            array('always', array('foo!')),
            array('success', 'foo!'),
            array('success', array('foo!')),
            array('failure', 'foo!'),
            array('failure', array('foo!')),
        );
    }

    public function getMethod()
    {
        return array(
            array('resolve'),
            array('reject'),
        );
    }
}
