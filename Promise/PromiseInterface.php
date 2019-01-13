<?php

/**
 * This file is part of universalPHP Plugin Event System.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Async\Promise;

/**
 * A promise represents the eventual result of an asynchronous operation.
 *
 * The primary way of interacting with a promise is through its then method,
 * which registers callbacks to receive either a promises eventual value or
 * the reason why the promise cannot be resolved.
 *
 * @link https://promisesaplus.com/
 */
interface PromiseInterface
{
    const STATE_PENDING  = 'pending';
    const STATE_RESOLVED = 'resolved';
    const STATE_REJECTED = 'rejected';

    /**
     * Returns the promise state.
     *
     *  * PromiseInterface::STATE_PENDING:  The promise is still open
     *  * PromiseInterface::STATE_RESOLVED: The promise completed successfully
     *  * PromiseInterface::STATE_REJECTED: The promise errored
     *
     * @return string A promise state constant
     */
    public function getState();

    /**
     * Adds a callback to be called whether the promise is resolved or rejected.
     *
     * The callback will be called immediately if the promise is no longer
     * pending.
     *
     * @param callable $always The callback
     *
     * @return PromiseInterface
     */
    public function always($always);

    /**
     * Adds a callback to be called when the promise completes successfully.
     *
     * The callback will be called immediately if the promise state is resolved.
     *
     * @param callable $success The callback
     *
     * @return PromiseInterface
     */
    public function success($success);

    /**
     * Adds a callback to be called when the promise errors.
     *
     * The callback will be called immediately if the promise state is rejected.
     *
     * @param callable $success The callback
     *
     * @return PromiseInterface
     */
    public function failure($failure);

    /**
     * Adds success and failure callbacks, and returns a new
     * promise resolving to the return value of the callback if it is called,
     * or to its original fulfillment value if the promise is instead
     * resolved.
     *
     * @param callable $success The success callback
     * @param callable $failure The failure callback
     *
     * @return PromiseInterface
     */
    public function then($success, $failure = null);
	
    /**
     * Resolve the promise with the given value.
     *
     * @param mixed $value
     * @return PromiseInterface
     * @throws \LogicException if the promise is already resolved.
     */
    public function resolve($value);

    /**
     * Reject the promise with the given reason.
     *
     * @param mixed $reason
     * @return PromiseInterface
     * @throws \LogicException if the promise is already resolved.
     */
    public function reject($reason);
    
    /**
     * Appends a reject callback to the promise.
     *
     * @param callable $failure Invoked when the promise is rejected.
     *
     * @return PromiseInterface
     */
    public function otherwise($failure);

    /**
     * Cancels the promise if possible.
     *
     * @link https://github.com/promises-aplus/cancellation-spec/issues/7
     */
    public function cancel();

    /**
     * Waits until the promise completes if possible.
     *
     * Pass $unwrap as true to unwrap the result of the promise, either
     * returning the resolved value or throwing the rejected exception.
     *
     * If the promise cannot be waited on, then the promise will be rejected.
     *
     * @param bool $unwrap
     *
     * @return mixed
     * @throws \LogicException if the promise does not settle after waiting.
     */
    public function wait($unwrap = true);
}
