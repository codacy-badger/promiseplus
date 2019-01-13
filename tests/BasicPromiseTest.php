<?php
namespace Async\Tests;

use Async\Promise\Deferred;
use Async\Promise\Promise;
use Async\Promise\Fulfilled;
use Async\Promise\Rejected;
use PHPUnit\Framework\TestCase;

class BasicPromiseTest extends TestCase
{		
    public function testNoChildren()
    {
        $defer = new Promise(array());

        $log = array();
        $defer->success(function() use(& $log) {
            $log[] = 'success';
        });
		$defer->tick();
        $this->assertEquals(array('success'), $log);
    }

    public function testResolvedChildren()
    {
        $child = new Deferred();
        $child->resolve();

        $defer = new Promise(array($child));

        $log = array();
        $defer->success(function() use(& $log) {
            $log[] = 'success';
        });
		$defer->tick();

        $this->assertEquals(array('success'), $log);
    }

    public function testResolution()
    {
        $child1 = new Deferred();
        $child2 = new Deferred();

        $defer = new Promise($child1, $child2);

        $log = array();
        $defer->success(function() use(& $log) {
            $log[] = 'success';
        });
		$defer->tick();

        $this->assertEquals(array(), $log);

        $child1->resolve();
        $this->assertEquals(array(), $log);

        $child2->resolve();
        $this->assertEquals(array('success'), $log);
    }

    public function testRejection()
    {
        $child1 = new Deferred();
        $child2 = new Deferred();
        $child3 = new Deferred();

        $defer = new Promise($child1, $child2, $child3);

        $log = array();
        $defer->then(function() use(& $log) {
            $log[] = 'success';
        }, function() use(& $log) {
            $log[] = 'failure';
        });
		$defer->tick();

        $this->assertEquals(array(), $log);

        $child1->resolve();
        $this->assertEquals(array(), $log);

        $child2->reject();
        $this->assertEquals(array('failure'), $log);

        $child3->resolve();
        $this->assertEquals(array('failure'), $log);
    }

    public function testNested()
    {
        $child1a = new Deferred();
        $child1b = new Deferred();
        $child1 = new Promise(array($child1a, $child1b));
        $child2 = new Deferred();

        $defer = new Promise(array($child1, $child2));
		$defer->tick();

        $child1a->resolve();
        $child1b->resolve();
        $child2->resolve();

        $this->assertEquals('resolved', $defer->getState());
    }

    public function testFail()
    {
        $child = new Deferred();
        $defer = new Promise($child);

        $log = array();
        $defer->failure(function() use(& $log) {
            $log[] = 'failure';
        });
		$defer->tick();

        $child->reject();

        $this->assertEquals(array('failure'), $log);
    }
}
