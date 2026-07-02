<?php

declare(strict_types=1);

namespace SmileIdentity\Tests;

use PHPUnit\Framework\TestCase;
use SmileIdentity\Generated\Models\AcceptedResponse;

/** AcceptedResponse status normalization: `Accepted` and `accepted` both accept. */
final class AcceptedResponseTest extends TestCase
{
    public function testUppercaseAcceptedNormalizes(): void
    {
        $response = AcceptedResponse::fromArray([
            'status' => 'Accepted',
            'message' => 'Request accepted and queued for processing.',
            'job_id' => 'job_01h8x9y2z3a4b5c6d7e8f9g0h1',
            'user_id' => 'user_01h8x9y2z3a4b5c6d7e8f9g0h1',
        ]);

        self::assertTrue($response->isAccepted);
        self::assertSame('Accepted', $response->status);
        self::assertSame('job_01h8x9y2z3a4b5c6d7e8f9g0h1', $response->jobId);
        self::assertSame('user_01h8x9y2z3a4b5c6d7e8f9g0h1', $response->userId);
        self::assertNull($response->createdAt);
    }

    public function testLowercaseAcceptedNormalizes(): void
    {
        $response = AcceptedResponse::fromArray([
            'status' => 'accepted',
            'message' => 'Request accepted and queued for processing.',
            'job_id' => 'job_01h8x9y2z3a4b5c6d7e8f9g0h1',
            'user_id' => 'user_01h8x9y2z3a4b5c6d7e8f9g0h1',
            'created_at' => '2026-03-10T12:00:00.000Z',
        ]);

        self::assertTrue($response->isAccepted);
        self::assertSame('2026-03-10T12:00:00.000Z', $response->createdAt);
    }

    public function testOtherStatusIsNotAccepted(): void
    {
        $response = AcceptedResponse::fromArray(['status' => 'rejected']);

        self::assertFalse($response->isAccepted);
    }
}
