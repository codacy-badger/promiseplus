<?php

/**
 * This file is part of universalPHP Plugin Event System.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Async\Promise;

use Async\Promise\PromiseInterface;

interface DeferredInterface extends PromiseInterface
{
    /**
     * Marks the current promise as successful.
     *
     * Calls "always" callbacks first, followed by "success" callbacks.
     *
     * @param mixed $args Any arguments will be passed along to the callbacks
     *
     * @return DeferredInterface The current promise
     * @throws \LogicException   If the promise was previously rejected
     */
    public function resolve($value);

    /**
     * Marks the current promise as rejected.
     *
     * Calls "always" callbacks first, followed by "failure" callbacks.
     *
     * @param mixed $args Any arguments will be passed along to the callbacks
     *
     * @return DeferredInterface The current promise
     * @throws \LogicException   If the promise was previously resolved
     */
    public function reject($reason);
}
