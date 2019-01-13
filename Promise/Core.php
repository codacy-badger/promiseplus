<?php
/**
 * 
 */
namespace Async\Promise;

trait Core
{
	/**
     * Adds a resolving reaction (for both fulfillment and rejection). 
	 * The returned promise will be fullfiled with the same value or 
	 * rejected with the same reason as the original promise. 
	 * 
	 * Returns a new promise chained from this promise. 
	 *
	 * @see http://bluebirdjs.com/docs/api/finally.html
     *
     * @param callable $successOrfailure
     * @return PromiseInterface
     */
	public function finally(callable $successOrfailure = null)
    {		
		return $this->anyway($successOrfailure);
    }
	
	/**
     * Pass a handler that will be called regardless of this promises fate.
	 *
     * @param callable $anyway
     * @return PromiseInterface
     */
    public function anyway(callable $anyway = null)
    {		
        return $this->then(function ($value) use ($anyway) {
            return Promise::resolver($anyway())->then(function () use ($value) {
                return $value;
            });
        }, function ($reason) use ($anyway) {
            return Promise::resolver($anyway())->then(function () use ($reason) {
                return new Rejected($reason);
            });
        });
    }
	
	public function ensure(callable $successOrfailure = null)
    {		
		return $this->always($successOrfailure);
    }
			
	/**
     * Like calling .then, but the fulfillment value must be an array, which is 
	 * flattened to the formal parameters of the fulfillment handler.
	 * 
     * Apply then transformation callbacks that automatically spreads received array into separate arguments.
     * Returns a new promise for the transformed result.
     *
     * @see http://bluebirdjs.com/docs/api/spread.html
     *
     * @param callable|null $success
     * @param callable|null $failure
     * @return PromiseInterface
     * @resolves mixed
     * @rejects Error|Exception|string|null
     */
    public function spread(callable $success = null, callable $failure = null)
    {
        return $this->then(
            function($values) use($success) {
				return $success(...((array) $values));
			},
            function($rejections) use($failure) {		
				return $failure(...((array) $rejections));
            }
        );
    }
			
    /**
     * Usage is identical to otherwise().
     *
     * @see http://bluebirdjs.com/docs/api/catch.html
     *
     * @param callable $failure
     * @return new PromiseInterface
     */
    public function catch(callable $failure = null)
    {				
        return $this->then(null, $failure);
    }	
	
    public function error(callable $failure = null)
    {				
        return $this->otherwise($failure);
    }	
	
	/**
     * Returns a new promise that will be fulfilled in `delay` milliseconds if the promise is fulfilled,
     * or immediately rejected if the promise is rejected.
     *
     * @param int $delay
     * @return PromiseInterface
     */
	public function delay($delay)
	{
        $timer = null;
		$promise = $this->then(function($val) use (&$timer, $delay) {
			$defer = new Promise(self::$loop);
			$timer = self::$loop->setTimeout(function() use($defer, $val) { 
				$defer->resolve($val); 
			}, $delay);

			return $defer;
		})->finally(function() use($timer) {
            self::$loop->clearTimeout($timer);
        });

        return $promise;
    }

	/**
     * Returns a new promise that will be rejected in `timeout` milliseconds
     * if the promise is not resolved beforehand.
	 *
     * @example
     * ```js
     * var defer = vow.defer(),
     *     promiseWithTimeout1 = defer.promise().timeout(50),
     *     promiseWithTimeout2 = defer.promise().timeout(200);
     *
     * setTimeout(
     *     function() {
     *         defer.resolve('ok');
     *     },
     *     100);
     *
     * promiseWithTimeout1.fail(function(reason) {
     *     // promiseWithTimeout to be rejected in 50ms
     * });
     *
     * promiseWithTimeout2.then(function(value) {
     *     // promiseWithTimeout to be fulfilled with "'ok'" value
     * });
     * ```
     *
     * @param int $timeout timeout
     * @param string $message custom error message
     * @return PromiseInterface
     */
    public function timeout(int $timeout, $message = null) {
        $defer = new Promise(self::$loop);
		$timer = self::$loop->setTimeout(function() use($defer, $message) {
			$error = new \OutOfBoundsException($message || "Timed out after $timeout"."ms");
			$defer->reject($error);
		}, $timeout);

        $this->then(function($val) use($defer) {
			$defer->resolve($val);
		}, function($reason) {
			$defer->reject($reason);
		});

        $defer->finally(function() use ($timer) {
            self::$loop->clearTimeout($timer);
        });

        return $defer;
    }
	
	/**
     * Make function asyncronous and fulfill/reject promise on execution.
	 * The function to run in the task queue.
     *
     * Example: make readFile async
     *      fs = require('fs');
     *      var asyncReadFile = Promise.async(fs.readFile);
     *      asyncReadFile('package.json','utf8').then(function(data){
     *          console.log(data);
     *      },function(error){
     *          console.log("Read error:", error);
     *      });
     *
     * @param callable $func - function to make async
     * @param mixed $arguments - optional arguments to pass
     * @return PromiseInterface
     */
    public function async(string $func, ...$arguments)
	{
		if (!function_exists($func))
			throw new \InvalidArgumentException("$func is not a function");

		$promise = new Promise(self::$loop);		
		return $this->implement(function($data) use ($func, $promise, $arguments) {
			$argument = $arguments;
			$argument[] = $data;
			$result = Promise::tryCatch($func, $argument);
			if ($result['status'])
				$promise->resolve($result['value']);
			else
				$promise->reject($result['value']);
				
			return $promise;
		}, $promise);
		
    }
}
