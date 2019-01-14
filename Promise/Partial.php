<?php
/**
 * 
 */
namespace Async\Promise;

use Async\Promise\Coroutine;
use Async\Promise\RejectionException;

trait Partial
{
    /**
     * @return new PromiseInterface
     */
    public static function deferred() : Promise
    {
        return new Promise();
    }
	
	/**
	 * Convert or Creates a promise for a value if the value is not a promise. If
	 * the provided is a promise, then it is returned as-is.
	 *
	 * This method will convert other promise implementations.
	 *
	 * @param mixed $value promise or value.
	 * @return PromiseInterface
	 */
	public static function resolver($value = null)
	{
		if ($value instanceof PromiseInterface || $value instanceof Promise) {
			if (method_exists($value, 'then') && !method_exists($value, 'done')) {
				$canceller = null;

				if (method_exists($value, 'cancel')) {
					$canceller = [$value, 'cancel'];
				}

				return new Promise(function ($resolve, $reject) use ($value) {
					$value->then($resolve, $reject);
				}, $canceller, self::$loop);
			}
			
			return $value;
		}
		
		return new Fulfilled($value, self::$loop);		
	}

	/**
	 * Creates a rejected promise for a reason if the reason is not a promise. If
	 * the provided reason is a promise, then it is returned as-is.
	 *
	 * @param mixed $reason Promise or reason.
	 *
	 * @return PromiseInterface
	 */
	public static function rejecter($reason = null)
	{
		if ($reason instanceof PromiseInterface) {
			return $reason;
		}
			
		return new Rejected($reason, self::$loop);
	}
	
	/**
	 * Create an exception for a rejected promise value.
	 *
	 * @param mixed $reason
	 *
	 * @return \Exception|\Throwable
	 */
	public static function rejection($reason)
	{				        
		return $reason instanceof \Exception || $reason instanceof \Throwable
			? $reason
			: new RejectionException($reason);
	}
	
    /**
     * Equivalent to `promise::delay`.
     * If `$value` is not a promise, then `$value` is treated as a fulfilled promise.
     *
     * @param mix $value
     * @param int $delay
     * @return PromiseInterface
     */
    public static function delayer($value, $delay) {
        return Promise::resolver($value)->delay($delay);
    }

    /**
     * Static equivalent to `promise::timeout`.
     * If `$value` is not a promise, then `$value` is treated as a fulfilled promise.
     *
     * @param mix $value
     * @param int $timeout
     * @return PromiseInterface
     */
	public static function timeouter($value, $timeout) {
        return Promise::resolver($value)->timeout($timeout);
    }

	/**
	 * Turn asynchronous promise-based code into something that looks synchronous
	 * again, through the use of generators.
	 *
	 * @see https://golangbot.com/concurrency/
	 * @see https://golangbot.com/goroutines/
	 *
	 * @param callable $generatorFunction
	 *
	 * @return PromiseInterface
	 */
	public static function coroutine(callable $generatorFunction)
	{
		return new Coroutine($generatorFunction);
	}
		
	public static function fatalError($error)
	{
		try {
			trigger_error($error, E_USER_ERROR);
		} catch (\Throwable $e) {
			set_error_handler(null);
			trigger_error($error, E_USER_ERROR);
		} catch (\Exception $e) {
			set_error_handler(null);
			trigger_error($error, E_USER_ERROR);
		}
	}
	
	public static function tryCatch($function, $value)
	{
		$output = [];
		try {
			$output['value'] = $function($value);
			$output['status'] = true;
		} catch (\Throwable $e) {
			$output['value'] = $e;
			$output['status'] = false;
		} catch (\Exception $exception) {
			$output['value'] = $exception;
			$output['status'] = false;
		}

		return $output;
	}
}
