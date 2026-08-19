<?php

declare(strict_types=1);

namespace SmileIdentity\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SmileIdentity\Errors\TimeoutError;
use SmileIdentity\Tests\Support\MockClient;

/**
 * verifications.waitUntilComplete: interval/timeout/not-found-as-pending
 * options and TimeoutError.
 */
final class WaitUntilCompleteTest extends TestCase
{
    private function jobStatusResponse(string $status): Response
    {
        $code = match ($status) {
            'not_found' => 404,
            'processing' => 202,
            default => 200,
        };

        return new Response($code, [], (string) json_encode([
            'status' => $status,
            'job_id' => 'job_1',
            'user_id' => 'user_1',
            'message' => $code === 200 ? 'Job completed' : "Verification is {$status}",
        ]));
    }

    public function testPollsUntilClear(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            $this->jobStatusResponse('processing'),
            $this->jobStatusResponse('processing'),
            $this->jobStatusResponse('clear'),
        ]);

        $status = $mock->client->verifications->waitUntilComplete('job_1', interval: 2.0, timeout: 60.0);

        self::assertTrue($status->isComplete);
        self::assertSame('clear', $status->status);
        // Two sleeps of the polling interval between the three polls.
        self::assertSame([2.0, 2.0], $mock->sleeps);
        self::assertCount(4, $mock->history);
    }

    public function testReturnsOnBlock(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            $this->jobStatusResponse('processing'),
            $this->jobStatusResponse('block'),
        ]);

        $status = $mock->client->verifications->waitUntilComplete('job_1', interval: 2.0, timeout: 60.0);

        self::assertTrue($status->isComplete);
        self::assertSame('block', $status->status);
        self::assertCount(3, $mock->history);
    }

    public function testNotFoundIsPendingByDefault(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            $this->jobStatusResponse('not_found'),
            $this->jobStatusResponse('clear'),
        ]);

        $status = $mock->client->verifications->waitUntilComplete('job_1');

        self::assertTrue($status->isComplete);
        self::assertCount(3, $mock->history);
    }

    public function testNotFoundReturnsImmediatelyWhenNotPending(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            $this->jobStatusResponse('not_found'),
        ]);

        $status = $mock->client->verifications->waitUntilComplete('job_1', treatNotFoundAsPending: false);

        self::assertTrue($status->isNotFound);
        self::assertCount(2, $mock->history);
        self::assertSame([], $mock->sleeps);
    }

    public function testTimesOutWithTimeoutError(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            $this->jobStatusResponse('processing'),
        ]);

        try {
            $mock->client->verifications->waitUntilComplete('job_1', timeout: 0.0);
            self::fail('Expected TimeoutError');
        } catch (TimeoutError $e) {
            self::assertStringContainsString('job_1', $e->getMessage());
            self::assertNull($e->statusCode);
        }
    }
}
