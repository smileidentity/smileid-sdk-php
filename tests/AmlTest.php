<?php

declare(strict_types=1);

namespace SmileIdentity\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SmileIdentity\Consent;
use SmileIdentity\Errors\ValidationError;
use SmileIdentity\Tests\Support\MockClient;
use SmileIdentity\Tests\Support\MultipartParser;
use SmileIdentity\Version;

/** Golden requests and response parsing for the AML operations. */
final class AmlTest extends TestCase
{
    private const KEY = 'kuda-onboarding-8f14e45fceea167a';

    /** @return array<string, mixed> */
    private function userDetails(): array
    {
        return ['given_names' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com'];
    }

    private function consent(): Consent
    {
        return Consent::granted(
            grantedAt: '2026-03-06T12:00:00.000Z',
            noticeLanguage: 'EN',
            noticePrivacyPolicyUrl: 'https://example.com/privacy',
        );
    }

    public function testOneTimeGoldenRequest(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse('accepted')]);

        $accepted = $mock->client->aml->oneTime(
            countries: ['NG', 'KE'],
            fullName: 'John Doe',
            userDetails: $this->userDetails(),
            consent: $this->consent(),
            callbackUrl: 'https://example.com/callback',
            birthYear: '1990',
            strictMatch: false,
            aliases: ['Johnny Doe'],
            searchExistingUser: true,
            userId: 'user-1234',
            partnerParams: ['order_id' => 'ORD-12345'],
            metadata: [['name' => 'a', 'value' => 'b']],
        );

        $request = $mock->request(1);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://testapi.smileidentity.com/v3/aml', (string) $request->getUri());
        self::assertNotSame('', $request->getHeaderLine('SmileID-Token'));
        self::assertSame('php', $request->getHeaderLine('SmileID-Source-SDK'));
        self::assertSame(Version::VERSION, $request->getHeaderLine('SmileID-Source-SDK-Version'));
        self::assertFalse($request->hasHeader('SmileID-Idempotency-Key'));
        self::assertFalse($request->hasHeader('SmileID-Partner-ID'));

        $parts = MultipartParser::parse($request);
        $countries = MultipartParser::named($parts, 'countries')[0];
        self::assertSame('["NG","KE"]', $countries['body']);
        self::assertSame('application/json', $countries['contentType']);
        self::assertSame('John Doe', MultipartParser::named($parts, 'full_name')[0]['body']);
        self::assertSame('1990', MultipartParser::named($parts, 'birth_year')[0]['body']);
        self::assertSame('false', MultipartParser::named($parts, 'strict_match')[0]['body']);
        self::assertSame('true', MultipartParser::named($parts, 'search_existing_user')[0]['body']);
        self::assertSame('user-1234', MultipartParser::named($parts, 'user_id')[0]['body']);
        self::assertSame('https://example.com/callback', MultipartParser::named($parts, 'callback_url')[0]['body']);
        self::assertSame('["Johnny Doe"]', MultipartParser::named($parts, 'aliases')[0]['body']);
        self::assertSame(
            '{"given_names":"John","last_name":"Doe","email":"john@example.com"}',
            MultipartParser::named($parts, 'user_details')[0]['body'],
        );
        self::assertSame('{"order_id":"ORD-12345"}', MultipartParser::named($parts, 'partner_params')[0]['body']);
        self::assertSame('[{"name":"a","value":"b"}]', MultipartParser::named($parts, 'metadata')[0]['body']);
        self::assertSame('application/json', MultipartParser::named($parts, 'consent')[0]['contentType']);

        self::assertTrue($accepted->isAccepted);
        self::assertSame('job_01h8x9y2z3a4b5c6d7e8f9g0h1', $accepted->jobId);
    }

    public function testOneTimeMinimalRequestUsesDefaultCallback(): void
    {
        $mock = new MockClient(
            [MockClient::tokenResponse(), MockClient::acceptedResponse('accepted')],
            ['defaultCallbackUrl' => 'https://example.com/default'],
        );

        $mock->client->aml->oneTime(['NG'], 'John Doe', $this->userDetails(), $this->consent());

        $parts = MultipartParser::parse($mock->request(1));
        self::assertSame('https://example.com/default', MultipartParser::named($parts, 'callback_url')[0]['body']);
        foreach (['birth_year', 'strict_match', 'aliases', 'search_existing_user', 'user_id', 'partner_params', 'metadata'] as $absent) {
            self::assertCount(0, MultipartParser::named($parts, $absent), $absent);
        }
    }

    public function testOneTimeSyncGoldenRequestAndResult(): void
    {
        $body = [
            'status' => 'attention',
            'message' => 'Screening completed: matches found.',
            'reason' => 'aml_match_found',
            'created_at' => '2026-07-20T12:00:00.000Z',
            'completed_at' => '2026-07-20T12:00:01.000Z',
            'product' => 'one_time_aml',
            'partner_params' => ['order_id' => 'ORD-12345'],
            'kyc_receipt' => 'https://receipts.smileidentity.com/abc',
            'strict_match' => true,
            'no_of_persons_found' => 1,
            'people' => [['name' => 'John Doe', 'pep' => ['pep_level' => 2]], 'junk'],
            'user_provided_info' => ['given_names' => 'John', 'last_name' => 'Doe', 'email' => null, 'phone_number' => '+2348012345678'],
            'consent' => ['granted' => true],
            'antifraud' => ['score' => 1],
            'device_signals' => null,
        ];
        $mock = new MockClient([MockClient::tokenResponse(), new Response(200, [], (string) json_encode($body))]);

        $result = $mock->client->aml->oneTimeSync(
            countries: ['NG'],
            fullName: 'John Doe',
            userDetails: $this->userDetails(),
            consent: $this->consent(),
            idempotencyKey: self::KEY,
        );

        $request = $mock->request(1);
        self::assertSame('https://testapi.smileidentity.com/v3/aml/sync', (string) $request->getUri());
        self::assertSame(self::KEY, $request->getHeaderLine('SmileID-Idempotency-Key'));
        $parts = MultipartParser::parse($request);
        self::assertCount(0, MultipartParser::named($parts, 'callback_url'));
        self::assertSame('["NG"]', MultipartParser::named($parts, 'countries')[0]['body']);

        self::assertSame('attention', $result->status);
        self::assertSame('Screening completed: matches found.', $result->message);
        self::assertSame('aml_match_found', $result->reason);
        self::assertSame('2026-07-20T12:00:00.000Z', $result->createdAt);
        self::assertSame('2026-07-20T12:00:01.000Z', $result->completedAt);
        self::assertSame('one_time_aml', $result->product);
        self::assertSame(['order_id' => 'ORD-12345'], $result->partnerParams);
        self::assertSame('https://receipts.smileidentity.com/abc', $result->kycReceipt);
        self::assertTrue($result->strictMatch);
        self::assertSame(1, $result->noOfPersonsFound);
        self::assertSame([['name' => 'John Doe', 'pep' => ['pep_level' => 2]]], $result->people);
        self::assertNull($result->userProvidedInfo['email']);
        self::assertSame(['granted' => true], $result->consent);
        self::assertSame(['score' => 1], $result->antifraud);
        self::assertNull($result->deviceSignals);
    }

    public function testOneTimeSyncParsesClearResultWithNulls(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), new Response(200, [], '{"status":"clear"}')]);

        $result = $mock->client->aml->oneTimeSync(['NG'], 'John Doe', $this->userDetails(), $this->consent());

        self::assertFalse($mock->request(1)->hasHeader('SmileID-Idempotency-Key'));
        self::assertSame('clear', $result->status);
        self::assertNull($result->reason);
        self::assertNull($result->completedAt);
        self::assertNull($result->partnerParams);
        self::assertNull($result->kycReceipt);
        self::assertSame([], $result->people);
        self::assertSame([], $result->userProvidedInfo);
        self::assertSame([], $result->consent);
        self::assertNull($result->antifraud);
    }

    public function testOneTimeSyncRetriesOnlyWithIdempotencyKey(): void
    {
        $timeout = fn (): Response => new Response(408, [], '{"status":"Request Timeout","message":"slow"}');

        $keyed = new MockClient([MockClient::tokenResponse(), $timeout(), new Response(200, [], '{"status":"clear"}')]);
        $result = $keyed->client->aml->oneTimeSync(['NG'], 'John Doe', $this->userDetails(), $this->consent(), idempotencyKey: self::KEY);
        self::assertSame('clear', $result->status);
        self::assertCount(3, $keyed->history);

        $unkeyed = new MockClient([MockClient::tokenResponse(), $timeout()]);
        try {
            $unkeyed->client->aml->oneTimeSync(['NG'], 'John Doe', $this->userDetails(), $this->consent());
            self::fail('Expected an error.');
        } catch (\SmileIdentity\Errors\SmileIDError) {
            self::assertCount(2, $unkeyed->history);
        }
    }

    public function testMonitoringGoldenRequest(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse('accepted')]);

        $accepted = $mock->client->aml->monitoring(
            countries: ['NG', 'GH'],
            userDetails: $this->userDetails(),
            consent: $this->consent(),
            fullName: 'Jane Doe',
            callbackUrl: 'https://example.com/callback',
            birthYear: '1990',
            strictMatch: true,
            aliases: ['J. Doe'],
            userId: 'user-1',
        );

        $request = $mock->request(1);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://testapi.smileidentity.com/v3/aml/monitoring', (string) $request->getUri());
        self::assertNotSame('', $request->getHeaderLine('SmileID-Token'));
        $parts = MultipartParser::parse($request);
        self::assertSame('["NG","GH"]', MultipartParser::named($parts, 'countries')[0]['body']);
        self::assertSame('Jane Doe', MultipartParser::named($parts, 'full_name')[0]['body']);
        self::assertSame('true', MultipartParser::named($parts, 'strict_match')[0]['body']);
        self::assertSame('["J. Doe"]', MultipartParser::named($parts, 'aliases')[0]['body']);
        self::assertSame('user-1', MultipartParser::named($parts, 'user_id')[0]['body']);
        self::assertTrue($accepted->isAccepted);
    }

    public function testMonitoringReusesExistingUserWithoutName(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse('accepted')]);

        $mock->client->aml->monitoring(
            countries: ['NG'],
            userDetails: $this->userDetails(),
            consent: $this->consent(),
            searchExistingUser: true,
            userId: 'user-1',
        );

        $parts = MultipartParser::parse($mock->request(1));
        self::assertCount(0, MultipartParser::named($parts, 'full_name'));
        self::assertSame('true', MultipartParser::named($parts, 'search_existing_user')[0]['body']);
    }

    public function testMonitoringNewsGoldenRequestAndResponse(): void
    {
        $body = [
            'job_id' => 'a1b2c3d4e5',
            'product' => 'aml_monitoring_news',
            'user_id' => 'user_123',
            'meta' => ['current_page' => 2, 'limit' => 10, 'total_count' => 11],
            'news' => [
                [
                    'body' => 'Sample adverse-media content.',
                    'published_date' => '2026-01-15T12:00:00.000Z',
                    'publisher' => 'Example News Publisher',
                    'publisher_country_code' => 'NG',
                    'title' => 'Sample AML Monitoring News Item',
                    'url' => 'https://example.com/news/sample',
                ],
                'junk',
            ],
        ];
        $mock = new MockClient([MockClient::tokenResponse(), new Response(200, [], (string) json_encode($body))]);

        $news = $mock->client->aml->monitoringNews('a1b2c3d4e5', 'ref/001', 'financial_crime', page: 2, limit: 10);

        $request = $mock->request(1);
        self::assertSame('GET', $request->getMethod());
        self::assertSame(
            'https://testapi.smileidentity.com/v3/aml/monitoring/a1b2c3d4e5/news/ref%2F001?news_category=financial_crime&page=2&limit=10',
            (string) $request->getUri(),
        );
        self::assertNotSame('', $request->getHeaderLine('SmileID-Token'));
        self::assertSame('', (string) $request->getBody());

        self::assertSame('a1b2c3d4e5', $news->jobId);
        self::assertSame('aml_monitoring_news', $news->product);
        self::assertSame('user_123', $news->userId);
        self::assertSame(2, $news->currentPage);
        self::assertSame(10, $news->limit);
        self::assertSame(11, $news->totalCount);
        self::assertCount(1, $news->news);
        self::assertSame('Sample adverse-media content.', $news->news[0]->body);
        self::assertSame('2026-01-15T12:00:00.000Z', $news->news[0]->publishedDate);
        self::assertSame('Example News Publisher', $news->news[0]->publisher);
        self::assertSame('NG', $news->news[0]->publisherCountryCode);
        self::assertSame('Sample AML Monitoring News Item', $news->news[0]->title);
        self::assertSame('https://example.com/news/sample', $news->news[0]->url);
    }

    public function testMonitoringNewsDefaultsAndEmptyBody(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), new Response(200, [], '{}')]);

        $news = $mock->client->aml->monitoringNews('job', 'ref', 'other');

        self::assertSame(
            'https://testapi.smileidentity.com/v3/aml/monitoring/job/news/ref?news_category=other',
            (string) $mock->request(1)->getUri(),
        );
        self::assertSame([], $news->news);
        self::assertSame(0, $news->totalCount);
    }

    public function testMonitoringNewsParsesEmptyItem(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), new Response(200, [], '{"news":[[]]}')]);

        $news = $mock->client->aml->monitoringNews('job', 'ref', 'other');

        self::assertSame('', $news->news[0]->title);
    }

    /**
     * @param \Closure(\SmileIdentity\Client\Resources\AmlResource): mixed $call
     *
     * @dataProvider invalidCalls
     */
    public function testValidationRejectsBeforeAnyRequest(\Closure $call, string $message): void
    {
        $mock = new MockClient([]);

        try {
            $call($mock->client->aml);
            self::fail('Expected a ValidationError.');
        } catch (ValidationError $e) {
            self::assertStringContainsString($message, $e->getMessage());
        }
        self::assertCount(0, $mock->history);
    }

    /** @return iterable<string, array{\Closure, string}> */
    public static function invalidCalls(): iterable
    {
        $details = ['given_names' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com'];
        $consent = Consent::granted('2026-03-06T12:00:00.000Z', 'EN', 'https://example.com/privacy');

        yield 'no countries' => [fn ($a) => $a->oneTime([], 'John', $details, $consent), 'at least one country'];
        yield 'lowercase country' => [fn ($a) => $a->oneTime(['ng'], 'John', $details, $consent), 'uppercase ISO'];
        yield 'blank name' => [fn ($a) => $a->oneTime(['NG'], ' ', $details, $consent), 'fullName must not be empty'];
        yield 'bad birth year' => [fn ($a) => $a->oneTime(['NG'], 'John', $details, $consent, birthYear: '90'), 'four digit'];
        yield 'too many aliases' => [
            fn ($a) => $a->oneTime(['NG'], 'John', $details, $consent, aliases: array_fill(0, 21, 'x')),
            'at most 20',
        ];
        yield 'bad user details' => [fn ($a) => $a->oneTime(['NG'], 'John', [], $consent), 'user_details'];
        yield 'http callback' => [
            fn ($a) => $a->oneTime(['NG'], 'John', $details, $consent, callbackUrl: 'http://example.com/cb'),
            'callback_url',
        ];
        yield 'sync http callback' => [
            fn ($a) => $a->oneTimeSync(['NG'], 'John', $details, $consent, callbackUrl: 'http://example.com/cb'),
            'callback_url',
        ];
        yield 'bad idempotency key' => [
            fn ($a) => $a->oneTimeSync(['NG'], 'John', $details, $consent, idempotencyKey: 'short'),
            'idempotencyKey',
        ];
        yield 'monitoring without name' => [fn ($a) => $a->monitoring(['NG'], $details, $consent), 'fullName is required'];
        yield 'monitoring reuse without user' => [
            fn ($a) => $a->monitoring(['NG'], $details, $consent, searchExistingUser: true),
            'userId is required',
        ];
        yield 'news bad category' => [fn ($a) => $a->monitoringNews('j', 'r', 'sports'), 'newsCategory'];
        yield 'news bad page' => [fn ($a) => $a->monitoringNews('j', 'r', 'other', page: 0), 'page'];
        yield 'news bad limit' => [fn ($a) => $a->monitoringNews('j', 'r', 'other', limit: 101), 'limit'];
    }
}
