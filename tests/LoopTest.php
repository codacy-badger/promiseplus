<?php

namespace Async\Tests;

use Async\Loop\Loop;
use PHPUnit\Framework\TestCase;

class LoopTest extends TestCase 
{
	protected function setUp()
    {
		Loop::clearInstance();
    }

    function testAddTick() 
	{
        $loop = new Loop();
        $check  = 0;
        $loop->addTick(function() use (&$check) {
            $check++;
        });
        $loop->run();
        $this->assertEquals(1, $check);
    }

    function testTimeout() 
	{
        $loop = new Loop();
        $check  = 0;
        $loop->addTimeout(function() use (&$check) {
            $check++;
        }, 0.02);
        $loop->run();
        $this->assertEquals(1, $check);
    }

    function testTimeoutOrder() 
	{
        $loop = new Loop();
        $check  = [];
        $loop->addTimeout(function() use (&$check) {
            $check[] = 'a';
        }, 0.2);
        $loop->addTimeout(function() use (&$check) {
            $check[] = 'b';
        }, 0.1);
        $loop->addTimeout(function() use (&$check) {
            $check[] = 'c';
        }, 0.3);
        $loop->run();
        $this->assertEquals(['b', 'a', 'c'], $check);
    }

    function testSetInterval() 
	{
        $loop = new Loop();
        $check = 0;
        $intervalId = null;
        $intervalId = $loop->setInterval(function() use (&$check, &$intervalId, $loop) {
            $check++;
            if ($check > 5) {
                $loop->clearInterval($intervalId);
            }
        }, 0.02);
        $loop->run();
        $this->assertEquals(6, $check);
    }

    function testAddWriteStream() 
	{
        $h = fopen('php://temp', 'r+');
        $loop = new Loop();
        $loop->addWriteStream($h, function() use ($h, $loop) {
            fwrite($h, 'hello world');
            $loop->removeWriteStream($h);
        });
        $loop->run();
        rewind($h);
        $this->assertEquals('hello world', stream_get_contents($h));
    }

    function testAddReadStream() 
	{
        $h = fopen('php://temp', 'r+');
        fwrite($h, 'hello world');
        rewind($h);
        $loop = new Loop();
        $result = null;
        $loop->addReadStream($h, function() use ($h, $loop, &$result) {
            $result = fgets($h);
            $loop->removeReadStream($h);
        });
        $loop->run();
        $this->assertEquals('hello world', $result);
    }

    function testStop() 
	{
        $check = 0;
        $loop = new Loop();
        $loop->addTimeout(function() use (&$check) {
            $check++;
        }, 200);
        $loop->addTick(function() use ($loop) {
            $loop->stop();
        });
        $loop->run();
        $this->assertEquals(0, $check);
    }

    function testTick() 
	{
        $check = 0;
        $loop = new Loop();
        $loop->addTimeout(function() use (&$check) {
            $check++;
        }, 1);
        $loop->addTick(function() use ($loop, &$check) {
            $check++;
        });
        $loop->tick();
        $this->assertEquals(1, $check);
    }

    /**
     * Here we add a new addTick function as we're in the middle of a current
     * add.
     */
    function testAddStacking() 
	{
        $loop = new Loop();
        $check  = 0;
        $loop->addTick(function() use (&$check, $loop) {
            $loop->addTick(function() use (&$check) {
                $check++;
            });
            $check++;
        });
        $loop->run();

        $this->assertEquals(2, $check);
    }
	
    public function testRemoveSignalNotRegisteredIsNoOp()
    {
        if (!function_exists('posix_kill') || !function_exists('posix_getpid')) {
            $this->markTestSkipped('Signal test skipped because functions "posix_kill" and "posix_getpid" are missing.');
        }
        $loop = new Loop();
        $loop->removeSignal(SIGINT, function () { });
        $this->assertTrue(true);
    }
	
    public function testSignal()
    {
        if (!function_exists('posix_kill') || !function_exists('posix_getpid')) {
            $this->markTestSkipped('Signal test skipped because functions "posix_kill" and "posix_getpid" are missing.');
        }
        $loop = new Loop();
        $called = false;
        $calledShouldNot = true;
        $timer = $loop->setInterval(function () {}, 1);
        $loop->addSignal(SIGUSR2, $func2 = function () use (&$calledShouldNot) {
            $calledShouldNot = false;
        });
        $loop->addSignal(SIGUSR1, $func1 = function () use (&$func1, &$func2, &$called, $timer, $loop) {
            $called = true;
            $loop->removeSignal(SIGUSR1, $func1);
            $loop->removeSignal(SIGUSR2, $func2);
            $loop->clearInterval($timer);
        });
        $loop->addTick(function () {
            posix_kill(posix_getpid(), SIGUSR1);
        });
        $loop->run();
        $this->assertTrue($called);
        $this->assertTrue($calledShouldNot);
    }
	
    public function testSignalMultipleUsagesForTheSameListener()
    {
        if (!function_exists('posix_kill') || !function_exists('posix_getpid')) {
            $this->markTestSkipped('Signal test skipped because functions "posix_kill" and "posix_getpid" are missing.');
        }
        $loop = new Loop();
        $funcCallCount = 0;
        $func = function () use (&$funcCallCount) {
            $funcCallCount++;
        };
        $loop->addTimeout(function () {}, 1);
        $loop->addSignal(SIGUSR1, $func);
        $loop->addSignal(SIGUSR1, $func);
        $loop->addTimeout(function () {
            posix_kill(posix_getpid(), SIGUSR1);
        }, 0.4);
        $loop->addTimeout(function () use (&$func, $loop) {
            $loop->removeSignal(SIGUSR1, $func);
        }, 0.9);
        $loop->run();
        $this->assertSame(1, $funcCallCount);
    }
	
    public function testSignalsKeepTheLoopRunning()
    {
        if (!function_exists('posix_kill') || !function_exists('posix_getpid')) {
            $this->markTestSkipped('Signal test skipped because functions "posix_kill" and "posix_getpid" are missing.');
        }
        $loop = new Loop();
        $function = function () {};
        $loop->addSignal(SIGUSR1, $function);
        $loop->addTimeout(function () use ($function, $loop) {
            $loop->removeSignal(SIGUSR1, $function);
            $loop->stop();
        }, 1.5);
        $loop->run();
        $this->assertRunSlowerThan(1.5);
    }
	
    public function testSignalsKeepTheLoopRunningAndRemovingItStopsTheLoop()
    {
        if (!function_exists('posix_kill') || !function_exists('posix_getpid')) {
            $this->markTestSkipped('Signal test skipped because functions "posix_kill" and "posix_getpid" are missing.');
        }
        $loop = new Loop();
        $function = function () {};
        $loop->addSignal(SIGUSR1, $function);
        $loop->addTimeout(function () use ($function, $loop) {
            $loop->removeSignal(SIGUSR1, $function);
        }, 1.5);
        $loop->run();
        $this->assertRunFasterThan(1.6);
    }
}
