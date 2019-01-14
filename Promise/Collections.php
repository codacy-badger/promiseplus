<?php

/**
 * Methods of Promise instances Promise class to deal with 
 * collections of promises or mixed promises and values.
 */

namespace Async\Promise;

use Async\Promise\EachPromise;
use Async\Promise\AggregateException;

trait Collections
{
	
	/**
	 * This function takes an array of Promises, and returns a Promise that
	 * resolves when every given argument have resolved.
	 *
	 * The returned Promise will resolve with a value that's an array of all the
	 * values the given promises have been resolved with.
	 *
	 * This array will be in the exact same order as the array of input promises.
	 *
	 * If any of the given Promises fails, the returned promise will immediately
	 * fail with the first Promise that fails, and its reason.
	 * 
	 * @see http://bluebirdjs.com/docs/api/promise.all.html
	 * 
	 * @param array $promises Promises.
	 * @return PromiseInterface
	 */
	public static function every( ...$promisesOrValues)
	{
		return Promise::map($promisesOrValues, function ($val) {
			return $val;
		});
	}
	
	public static function all(PromiseInterface ...$promises): Promise
	{
		return new Promise(function ($success, $fail) use ($promises) {
			if (empty($promises)) {
				$success([]);

				return;
			}

			// Wire into each Promise, counting them as they complete.
			// We count manually to filter out any odd, null entries.
			$successCount = 0;
			// We also holdon to any results that we seso that we can fulfill the whole promise with the result set.
			$completeResult = [];

			foreach ($promises as $promiseIndex => $subPromise) {
				$subPromise->then(
					function ($result) use ($promiseIndex, &$completeResult, &$successCount, $success, $promises) {
						// Increment the total count and store the promise, then wire-up the promise.
						$completeResult[$promiseIndex] = $result;
						++$successCount;
						
                        // If this is the last promise, resolve it, passing the promises.
                        // If a failure occurred already, this will have no effect.
						if ($successCount === count($promises)) {
							$success($completeResult);
						}

						return $result;
					},
					// A failure occurred, so decrement the count and reject the Deferred, passing the error / data that caused the rejection.
					// A single failure will cause the whole set to fail.
					function ($reason) use ($fail) {
						$fail($reason);
					}
				);
			}
		});
	}

	/**
	 * The race function returns a promise that resolves or rejects as soon as
	 * one of the promises in the argument resolves or rejects.
	 *
	 * The returned promise will resolve or reject with the value or reason of
	 * that first promise.
	 *
	 * @see http://bluebirdjs.com/docs/api/promise.race.html
	 * 
	 * @param array $promises Promises.
	 * @return PromiseInterface
	 */
	public static function first( ...$promisesOrValues)
	{
		if (!$promisesOrValues) {
			return new Promise(function () {});
		}

		return new Promise(function ($resolve, $reject) use ($promisesOrValues) {
			foreach ($promisesOrValues as $promiseOrValue) {
				Promise::resolver($promiseOrValue)
					->then($resolve, $reject);
			}
		});
	}

	public static function race(PromiseInterface ...$promises)
	{
		return new Promise(function ($success, $fail) use ($promises) {
			$alreadyDone = false;
			foreach ($promises as $promise) {
				$promise->then(
					function ($result) use ($success, &$alreadyDone) {
						if ($alreadyDone) {
							return;
						}
						$alreadyDone = true;
						$success($result);
					},
					function ($reason) use ($fail, &$alreadyDone) {
						if ($alreadyDone) {
							return;
						}
						$alreadyDone = true;
						$fail($reason);
					}
				);
			}
		});
	}

	/**
	 * Like some(), with 1 as count. However, if the promise fulfills, the
	 * fulfillment value is not an array of 1 but the value directly.
	 *
	 * @see http://bluebirdjs.com/docs/api/promise.any.html
	 * 
	 * @param mixed $promises Promises or values.
	 *
	 * @return PromiseInterface
	 */		
	public static function any( ...$promises)
	{
		return Promise::some($promises, 1)
			->then(function ($val) {
				return array_shift($val);
			});
	}

	/**
	 * Initiate a competitive race between multiple promises or values (values will
	 * become immediately fulfilled promises).
	 *
	 * When amount of promises have been fulfilled, the returned promise is
	 * fulfilled with an array that contains the fulfillment values of the winners
	 *
	 * This promise is rejected with a \LengthException
	 * if the number of fulfilled promises is less than the desired $howMany.
	 * 
	 * @see http://bluebirdjs.com/docs/api/promise.some.html
	 * 
	 * @param int   $howMany    Total number of promises.
	 * @param mixed $promises Promises or values.
	 *
	 * @return PromiseInterface
	 */
	public static function some(array $promisesOrValues, $howMany)
	{	
		if ($howMany < 1) {
			return Promise::resolver([]);
		}

		$len = count($promisesOrValues);
		if ($len < $howMany) {
			return Promise::rejecter( new \LengthException('Not enough promises to fulfill count'));
		}

		return new Promise(function ($resolve, $reject) use ($len, $promisesOrValues, $howMany) {
			$toResolve = min($howMany, $len);
			$toReject  = ($len - $toResolve) + 1;
			$values    = [];
			$reasons   = [];					
					
			foreach ($promisesOrValues as $i => $promiseOrValue) {
				$fulfiller = function ($val) use ($i, &$values, &$toResolve, $toReject, $resolve) {
					if ($toResolve < 1 || $toReject < 1) {
						return;
					}
							
					$values[$i] = $val;
					if (0 === --$toResolve) {
						ksort($values);
						$resolve(array_values($values));
					}
				};
				
				$rejecter = function ($reason) use ($i, &$values, &$reasons, &$toReject, $toResolve, $reject) {										
					if ($toResolve < 1 || $toReject < 1) {
						return;
					}
							
					$reasons[$i] = $reason;					
					if (0 === --$toReject) {
						$reject(new \LengthException($reasons[$i]));
					} 					
				};

				Promise::resolver($promiseOrValue)
					->then($fulfiller, $rejecter);						
			}
		});	
	}

	/**
     * Map promises and/or values using specified $mapFunc.
	 *
	 * Take a collection of parallel promises, return a promise for an array of
	 * corresponding values mapped through a user-provided function. Promises can
	 * fulfill in any order, yet the output array will match the original
	 * collection. The input collection can be an iterable (arrays are iterables),
	 * or a promise for an iterable. The values of the input collection can be any
	 * mix of promises and /or normal values. If any input promise rejects, the
	 * output promise immediately rejects.
     *
     * @see http://bluebirdjs.com/docs/api/promise.map.html
     *
     * @param PromiseInterface[]|mixed[] $promisesOrValues
     * @param callable $mapFunc
     * @return PromiseInterface
     * @Throws Error|Exception|string|null
     */
    public static function map(array $promisesOrValues, callable $mapFunc = null)
    {
		if (!$mapFunc) {
			throw new \InvalidArgumentException('`Promise::map` needs a mapper function');
		}
		
		if (!$promisesOrValues) {
			return Promise::resolver([]);
		}
		
		return new Promise(function ($resolve, $reject) use ($promisesOrValues, $mapFunc) {
			$toResolve = count($promisesOrValues);
            $values    = [];
			
            foreach ($promisesOrValues as $i => $promiseOrValue) {
				$values[$i] = null;
				
				Promise::resolver($promiseOrValue)
					->then($mapFunc)
                    ->then(function($mapped) use($i, &$values, &$toResolve, $resolve) {
							$values[$i] = $mapped;
							
							if (0 === --$toResolve) {		
								$resolve($values);
							}
						}, 
						$reject
					);
           }
        });
	}
	
    /**
     * Reduce Promises and/or values using $reduceFunc with $initialValue being Promise or primitive value.
     *
     * @see http://bluebirdjs.com/docs/api/promise.reduce.html
     *
     * @param PromiseInterface[]|mixed[] $promisesOrValues
     * @param callable $reduceFunc
     * @param PromiseInterface|mixed|null $initialValue
     * @return PromiseInterface
     * @resolves mixed
     * @rejects Error|Exception|string|null
     */
    public static function reduce(array $promisesOrValues, callable $reduceFunc, $initialValue = null)
	{
		return new Promise(function ($resolve, $reject) use ($promisesOrValues, $reduceFunc, $initialValue) {
			$total = count($promisesOrValues);
			$i = 0;

			// Wrap the supplied $reduceFunc with one that handles promises and then delegates to the supplied.
			$wrappedReduceFunc = function ($current, $val) use ($reduceFunc, $total, &$i) {
				return $current
					->then(function ($c) use ($reduceFunc, $total, &$i, $val) {
						return Promise::resolver($val)
							->then(function ($value) use ($reduceFunc, $total, &$i, $c) {
								return $reduceFunc($c, $value, $i++, $total);
							});
					});
			};

			array_reduce($promisesOrValues, $wrappedReduceFunc, Promise::resolver($initialValue))
				->then($resolve, $reject);
		});
	}
		
	/**
	 * Initiate a competitive race between multiple promises.
	 *
	 * When count amount of promises have been fulfilled, the returned promise is
	 * fulfilled with an array that contains the fulfillment values of the winners
	 * in order of resolution.
	 *
	 * This promise is rejected with a \InvalidArgumentException
	 * if the number of fulfilled promises is less than the desired $count.
	 *
	 * @param int   $count    Total number of promises.
	 * @param mixed $promises Promises.
	 *
	 * @return PromiseInterface
	 */
	public static function few($amount, PromiseInterface ...$promises){
		if(count($promises) < $amount){
			throw new \InvalidArgumentException("Not enough promises to fulfill count");
		}
		
		return new Promise(function ($fulfill, $reject) use ($promises, $amount) {
			$left_fulfills = $amount;
			$left_rejects = count($promises) - $amount + 1;
			$results = [];
			$reasons = [];
			
			foreach($promises as $i => $promise) {
				$promise->then(function($fulfilled_result) use ($i, &$results, &$left_fulfills, $fulfill){
					if($left_fulfills){
						$results[$i] = $fulfilled_result;
						$left_fulfills--;
						if(!$left_fulfills){
							ksort($results);
							$fulfill(array_values($results));
						}
					}
				}, function($reject_reason) use ($i, &$reasons, &$left_rejects, $reject){
					if($left_rejects){
						$reasons[$i] = $reject_reason;
						$left_rejects--;
						if(!$left_rejects){
							$reject(new \InvalidArgumentException($reasons[$i]));
						}
					}
				});
			}
		});
	}

	/**
	 * Given an iterator that yields promises or values, returns a promise that is
	 * fulfilled with a null value when the iterator has been consumed or the
	 * aggregate promise has been fulfilled or rejected.
	 *
	 * $onFulfilled is a function that accepts the fulfilled value, iterator
	 * index, and the aggregate promise. The callback can invoke any necessary side
	 * effects and choose to resolve or reject the aggregate promise if needed.
	 *
	 * $onRejected is a function that accepts the rejection reason, iterator
	 * index, and the aggregate promise. The callback can invoke any necessary side
	 * effects and choose to resolve or reject the aggregate promise if needed.
	 *
	 * @see http://bluebirdjs.com/docs/api/promise.each.html
	 *
	 * @param mixed    $iterable    Iterator or array to iterate over.
	 * @param callable $onFulfilled
	 * @param callable $onRejected
	 *
	 * @return PromiseInterface
	 */
	public static function each(
		$iterable,
		callable $onFulfilled = null,
		callable $onRejected = null
	) {
		return (new EachPromise($iterable, [
			'fulfilled' => $onFulfilled,
			'rejected'  => $onRejected
		]))->promise();
	}

	/**
	 * Returns an iterator for the given value.
	 *
	 * @param mixed $value
	 *
	 * @return \Iterator
	 */
	public static function iter_for($value)
	{
		if ($value instanceof \Iterator) {
			return $value;
		} elseif (is_array($value)) {
			return new \ArrayIterator($value);
		} else {
			return new \ArrayIterator([$value]);
		}
	}
}
