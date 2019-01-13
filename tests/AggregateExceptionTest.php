<?php
namespace Async\Tests;

use Async\Promise\AggregateException;
use PHPUnit\Framework\TestCase;

class AggregateExceptionTest extends TestCase
{
    public function testHasReason()
    {
        $e = new AggregateException('foo', ['baz', 'bar']);
        $this->assertContains('foo', $e->getMessage());
        $this->assertEquals(['baz', 'bar'], $e->getReason());
    }
}
