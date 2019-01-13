<?php
/**
 * Synchronous inspection allows you to retrieve the fulfillment value 
 * of an already fulfilled promise or the rejection reason of an already
 * rejected promise synchronously.
 */

namespace Async\Promise;

use Async\Promise\RejectionException;

trait Inspection
{
    /**
     * Return fulfillment value associated with Promise.
     *
     * @return mixed|null
     */
    public function getValue(PromiseInterface $promise = null)
    {
		$promiser = empty($promise) ? $this : $promise;
        return $this->isFulfilled($promiser) ? $promiser->result : null;
    }
	
	/**
     * Return rejection or cancellation reason for Promise.
     *
     * @return Error|Exception|string|null
     */
    public function getReason(PromiseInterface $promise = null)
    {
		$promiser = empty($promise) ? $this : $promise;
        return ($this->isRejected($promiser) || $this->isCancelled($promiser)) ? $promiser->result : null;
    }
	
	/**
     * Returns the value of the fulfilled promise or the reason in case of rejection.
     */
    public function getResult()
    {		
        return $this->result;
    }
		
    public function getState()
    {
        return $this->delegate->getState();
    }	
	
    private function setState($state = null)
    {  
		if (!in_array($state, [PromiseInterface::STATE_PENDING, PromiseInterface::STATE_RESOLVED, PromiseInterface::STATE_REJECTED])
		) {
            throw new \InvalidArgumentException(
                sprintf("Parameter 1 of %s must be a valid promise state.", __METHOD__)
            );
        }
		
        $this->delegate->setState($state);
    }
	
	/**
	 * Synchronously waits on a promise to resolve and returns an inspection state
	 * array.
	 *
	 * Returns a state associative array containing a "state" key mapping to a
	 * valid promise state. If the state of the promise is "fulfilled", the array
	 * will contain a "value" key mapping to the fulfilled value of the promise. If
	 * the promise is rejected, the array will contain a "reason" key mapping to
	 * the rejection reason of the promise.
	 *
	 * @param PromiseInterface $promise Promise or value.
	 *
	 * @return array
	 */
	public function inspect(PromiseInterface $promise = null)
	{
		$promiser = empty($promise) ? $this : $promise;
		try {
			return [
				'state' => PromiseInterface::STATE_RESOLVED,
				'value' => $promiser->wait()
			];
		} catch (RejectionException $e) {
			return ['state' => PromiseInterface::STATE_REJECTED, 'reason' => $e->getReason()];
		} catch (\Throwable $e) {
			return ['state' => PromiseInterface::STATE_REJECTED, 'reason' => $e];
		} catch (\Exception $e) {
			return ['state' => PromiseInterface::STATE_REJECTED, 'reason' => $e];
		}
	}
	
    public function isCancelled(PromiseInterface $promise = null)
    {
        return empty($promise) ? $this->isCancelled : $promise->isCancelled;
    }
	
	/**
	 * Returns true if a promise is fulfilled.
	 *
	 * @param PromiseInterface $promise
	 *
	 * @return bool
	 */
	public function isFulfilled(PromiseInterface $promise = null)
	{
		$promiser = empty($promise) ? $this : $promise;
		return $promiser->getState() === PromiseInterface::STATE_RESOLVED;
	}

	/**
	 * Returns true if a promise is rejected.
	 *
	 * @param PromiseInterface $promise
	 *
	 * @return bool
	 */
	public function isRejected(PromiseInterface $promise = null)
	{
		$promiser = empty($promise) ? $this : $promise;
		return $promiser->getState() === PromiseInterface::STATE_REJECTED;
	}

	/**
	 * Returns true if a promise is fulfilled or rejected.
	 *
	 * @param PromiseInterface $promise
	 *
	 * @return bool
	 */
	public function isSettled(PromiseInterface $promise = null)
	{
		$promiser = empty($promise) ? $this : $promise;
		return $promiser->getState() !== PromiseInterface::STATE_PENDING;
	}
	
	/**
	 * Returns true if a promise is pending.
	 *
	 * @param PromiseInterface $promise
	 *
	 * @return bool
	 */
	public function isPending(PromiseInterface $promise = null)
	{
		$promiser = empty($promise) ? $this : $promise;
		return $promiser->getState() === PromiseInterface::STATE_PENDING;
	}
	
	/**
	 * Returns true if a promise object.
	 *
	 * @param mixed $unknown
	 *
	 * @return bool
	 */
	public function isPromise($unknown = null)
	{
		return $unknown instanceof PromiseInterface;
	}
}
