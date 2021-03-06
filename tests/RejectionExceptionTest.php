<?php
namespace Async\Tests;

use Async\Promise\RejectionException;
use PHPUnit\Framework\TestCase;

class Thing1
{
    public function __construct($message)
    {
        $this->message = $message;
    }

    public function __toString()
    {
        return $this->message;
    }
}

class Thing2 implements \JsonSerializable
{
    public function jsonSerialize()
    {
        return '{}';
    }
}

/**
 * @covers Async\Promise\RejectionException
 */
class RejectionExceptionTest extends TestCase
{
    public function testCanGetReasonFromException()
    {
        $thing = new Thing1('foo');
        $e = new RejectionException($thing);

        $this->assertSame($thing, $e->getReason());
        $this->assertEquals('The promise was rejected with reason: foo', $e->getMessage());
    }

    public function testCanGetReasonMessageFromJson()
    {
        $reason = new Thing2();
        $e = new RejectionException($reason);
        $this->assertContains("{}", $e->getMessage());
    }
}
