<?php

declare(strict_types=1);

namespace SmileIdentity\Tests\E2E;

use PHPUnit\Framework\TestCase;
use SmileIdentity\Client;
use SmileIdentity\Consent;

/**
 * End-to-end sandbox test: submits an Enhanced KYC job and polls it to
 * completion. Requires SMILE_PARTNER_ID and SMILE_API_KEY in the environment;
 * skips cleanly when they are unset. Credential values are never printed.
 *
 * @group e2e
 */
final class EnhancedKycE2ETest extends TestCase
{
    public function testSandboxEnhancedKycCompletes(): void
    {
        $partnerId = getenv('SMILE_PARTNER_ID');
        $apiKey = getenv('SMILE_API_KEY');

        if ($partnerId === false || $partnerId === '' || $apiKey === false || $apiKey === '') {
            self::markTestSkipped('SMILE_PARTNER_ID and SMILE_API_KEY are not set; skipping sandbox E2E test.');
        }

        $client = new Client(
            partnerId: $partnerId,
            apiKey: $apiKey,
            environment: 'sandbox',
        );

        $accepted = $client->enhancedKyc->verify(
            country: 'NG',
            idType: 'NIN',
            idNumber: '12345678901',
            userDetails: ['given_names' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com'],
            consent: Consent::granted(
                grantedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                noticeLanguage: 'EN',
                noticePrivacyPolicyUrl: 'https://example.com/privacy',
            ),
        );

        self::assertTrue($accepted->isAccepted);
        self::assertNotNull($accepted->jobId);

        $status = $client->verifications->waitUntilComplete(
            (string) $accepted->jobId,
            interval: 2.0,
            timeout: 120.0,
        );

        self::assertTrue($status->isComplete);
    }
}
