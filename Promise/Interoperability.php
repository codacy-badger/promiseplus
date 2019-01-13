<?php
/**
 * 
 */
namespace Async\Promise;

use Async\Loop\Loop;
use Async\Loop\LoopInterface;

trait Interoperability
{	
	public function isLoopAvailable($instance = null): bool
	{
		$isInstantiable = false;
		if ($instance instanceof \GuzzleHttp\Promise\TaskQueue)
			$isInstantiable = true;
		elseif ($instance instanceof \React\EventLoop\StreamSelectLoop)
			$isInstantiable = true;
		elseif ($instance instanceof \Amp\Loop\NativeDriver)
			$isInstantiable = true;
		elseif ($instance instanceof LoopInterface)
			$isInstantiable = true;
		elseif ($instance instanceof Loop)
			$isInstantiable = true;
			
		return $isInstantiable;
	}
		
    public function clearLoop()
    {		
		self::$loop = null;
    }
	
    public function getLoop($create = false, $autoRun = false)
    {		
		if (!self::$loop && $create) {
			self::$loop = Loop::getInstance($autoRun);
		}
		
        return self::$loop;
	}
	
	public function getImplementation(string $method = null, 
		callable $function = null, 
		int $timer = null)
	{
		$loop = self::$loop;
		$othersLoop = null;
        if ($loop) {
			if ($method == 'queue') {
				if (method_exists($loop, 'futureTick'))
					$othersLoop = [$loop, 'futureTick']; 
				elseif (method_exists($loop, 'nextTick'))
					$othersLoop = [$loop, 'nextTick'];
				elseif (method_exists($loop, 'defer'))
					$othersLoop = [$loop, 'defer'];
				elseif (method_exists($loop, 'add'))
					$othersLoop = [$loop, 'add'];
			} elseif ($method == 'settimer') {
				if (method_exists($loop, 'addTimer'))
					$othersLoop = [$loop, 'addTimer', $timer, $function]; 
				elseif (method_exists($loop, 'setInterval'))
					$othersLoop = [$loop, 'setInterval', $function, $timer];
				elseif (method_exists($loop, 'delay'))
					$othersLoop = [$loop, 'delay', $timer, $function];
			} elseif ($method == 'cleartimer') {
				if (method_exists($loop, 'cancelTimer'))
					$othersLoop = [$loop, 'cancelTimer']; 
				elseif (method_exists($loop, 'clearInterval'))
					$othersLoop = [$loop, 'clearInterval'];
				elseif (method_exists($loop, 'cancel'))
					$othersLoop = [$loop, 'cancel'];
			}
		}
		
		return $othersLoop;
	}
	
	public function implement(callable $function, PromiseInterface $promise = null)
	{		
        if (self::$loop) {
			$othersLoop = Promise::getImplementation('queue');
			if ($othersLoop)
				call_user_func($othersLoop, $function); 
			else 	
				self::$loop->addTick($function);
        } else {
            return $function();
        } 
		
		return $promise;
	}
	
	public function implementSet(callable $function = null, $timer = null)
	{	
		$othersLoop = Promise::getImplementation('settimer', $function, $timer);
		if ($othersLoop)
			$timerResult = call_user_func($othersLoop); 
		else 	
			$timerResult = self::$loop->addTimeout($function, $timer);
		
		return $timerResult;
	}
	
	public function implementClear($timer = null)
	{	
		$othersLoop = Promise::getImplementation('cleartimer');
		if ($othersLoop)
			call_user_func($othersLoop, $timer); 
	}
}
