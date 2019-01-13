<?php
namespace Async\Promise;

/**
 * The exception reason value is available via the getReason() method.
 */
class RejectionException extends \RuntimeException
{
    /** @var mixed Rejection reason. */
    private $reason;

    /**
     * @param mixed $reason       Rejection reason.
     * @param string $description Optional description
     */
    public function __construct($reason, $description = null)
    {
        $this->reason = $reason;

        $message = 'The promise was rejected';

        if ($description) {
            $message .= ' with reason: ' . $description;
        } elseif (is_string($reason)
            || (is_object($reason) && method_exists($reason, '__toString'))
        ) {
            $message .= ' with reason: ' . $this->reason;
        } elseif ($reason instanceof \JsonSerializable) {
            $message .= ' with reason: '
                . json_encode($this->reason, JSON_PRETTY_PRINT);
        } else {
			$message .= ' as unhandled: ' . json_encode($reason);
		}

        parent::__construct($message);
    }

    public function resolve($reason)
    {
        if ($reason instanceof \Exception || $reason instanceof \Throwable) {
            return $reason;
        }

        return new self($reason);
    }
	
    /**
     * Returns the rejection reason.
     *
     * @return mixed
     */
    public function getReason()
    {
        return $this->reason;
    }
	
    public function wait($unwrap = true)
    {
        return $unwrap ? $this->reason : null;
    }
}
