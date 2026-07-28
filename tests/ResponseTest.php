<?php

declare(strict_types=1);

namespace Broadcast\Tests;

use Broadcast\Response;
use PHPUnit\Framework\TestCase as BaseTestCase;

final class ResponseTest extends BaseTestCase
{
    /**
     * A Response wraps a JSON *object*. Appending would create an integer key
     * alongside the string ones, leaving a body that is neither an object nor a
     * list — and json_encode would then silently change shape depending on
     * which keys survived. The property is declared array<string, mixed>, so
     * permitting it was also a type lie.
     */
    public function testAppendingIsRejectedRatherThanCorruptingTheBody(): void
    {
        $response = new Response(['id' => 1], 200);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/cannot be appended/');

        $response[] = 'orphan';
    }

    public function testSettingAKeyStillWorks(): void
    {
        $response = new Response(['id' => 1], 200);
        $response['name'] = 'Ada';

        self::assertSame(['id' => 1, 'name' => 'Ada'], $response->toArray());
    }
}
