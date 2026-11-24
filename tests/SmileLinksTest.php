<?php

declare(strict_types=1);

namespace SmileIdentity\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SmileIdentity\Errors\NotFoundError;
use SmileIdentity\Errors\ValidationError;
use SmileIdentity\Tests\Support\MockClient;
use SmileIdentity\Tests\Support\MultipartParser;
use SmileIdentity\Version;

/** Golden requests and responses for the smileLinks resource. */
final class SmileLinksTest extends TestCase
{
    private const REF = '65983864-e8c6-4d09-890a-2e5f88fc50f6';
    private const ID_TYPES = [['country' => 'NG', 'id_type' => 'NIN', 'verification_method' => 'enhanced_kyc']];

    /** @return array<string, mixed> */
    private function linkBody(): array
    {
        return [
            'ref_id' => self::REF,
            'link' => 'https://links.usesmileid.com/v3/2/' . self::REF,
            'name' => 'Onboarding',
            'company_name' => 'Acme Pay',
            'description' => null,
            'id_types' => [...self::ID_TYPES, 'junk'],
            'is_single_use' => true,
            'callback_url' => 'https://partner.example/cb',
            'notice_privacy_policy_url' => 'https://partner.example/privacy',
            'logo_url' => 'https://partner.example/logo.png',
            'redirect_url' => null,
            'theme_color' => '#000',
            'expires_at' => '2027-01-01T00:00:00.000Z',
            'disabled_at' => '2026-12-01T00:00:00.000Z',
            'partner_params' => ['order_id' => 'ORD-1'],
            'user_id' => 'user-123',
            'token_provided_fields' => ['country', 'id_type'],
            'token_provided_values' => ['country' => 'NG', 'id_type' => 'NIN'],
            'created_at' => '2026-11-01T00:00:00.000Z',
            'updated_at' => '2026-11-02T00:00:00.000Z',
        ];
    }

    /** The token must carry no product claim at all. */
    private function assertUnscopedToken(MockClient $mock, int $index): void
    {
        $request = $mock->request($index);
        self::assertSame('https://testapi.smileidentity.com/v3/token', (string) $request->getUri());
        // No product and no user id: the token request has no body at all.
        self::assertSame('', (string) $request->getBody());
        self::assertFalse($request->hasHeader('Content-Type'));
    }

    public function testCreateGoldenRequestAndResult(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(201, [], (string) json_encode(['link' => 'https://links.usesmileid.com/v3/2/' . self::REF, 'message' => 'Smile Link created', 'ref_id' => self::REF, 'user_id' => 'user-123'])),
        ]);

        $created = $mock->client->smileLinks->create(
            idTypes: self::ID_TYPES,
            name: 'Onboarding',
            noticePrivacyPolicyUrl: 'https://partner.example/privacy',
            isSingleUse: true,
            companyName: 'Acme Pay',
            callbackUrl: 'https://partner.example/cb',
            description: 'For Ada',
            expiresAt: '2027-01-01T00:00:00.000Z',
            logoUrl: 'https://partner.example/logo.png',
            redirectUrl: 'https://partner.example/done',
            themeColor: '#0a0a0a',
            partnerParams: ['order_id' => 'ORD-1'],
            userId: 'user-123',
        );

        self::assertUnscopedToken($mock, 0);

        $request = $mock->request(1);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://testapi.smileidentity.com/v3/smile_links', (string) $request->getUri());
        self::assertNotSame('', $request->getHeaderLine('SmileID-Token'));
        self::assertSame('php', $request->getHeaderLine('SmileID-Source-SDK'));
        self::assertSame(Version::VERSION, $request->getHeaderLine('SmileID-Source-SDK-Version'));
        self::assertStringStartsWith('multipart/form-data; boundary=', $request->getHeaderLine('Content-Type'));
        self::assertFalse($request->hasHeader('User-ID'));

        $parts = MultipartParser::parse($request);
        $idTypes = MultipartParser::named($parts, 'id_types')[0];
        self::assertSame('application/json', $idTypes['contentType']);
        self::assertSame('[{"country":"NG","id_type":"NIN","verification_method":"enhanced_kyc"}]', $idTypes['body']);
        $partnerParams = MultipartParser::named($parts, 'partner_params')[0];
        self::assertSame('application/json', $partnerParams['contentType']);
        self::assertSame('{"order_id":"ORD-1"}', $partnerParams['body']);
        $expected = [
            'name' => 'Onboarding',
            'company_name' => 'Acme Pay',
            'notice_privacy_policy_url' => 'https://partner.example/privacy',
            'is_single_use' => 'true',
            'callback_url' => 'https://partner.example/cb',
            'description' => 'For Ada',
            'expires_at' => '2027-01-01T00:00:00.000Z',
            'logo_url' => 'https://partner.example/logo.png',
            'redirect_url' => 'https://partner.example/done',
            'theme_color' => '#0a0a0a',
            'user_id' => 'user-123',
        ];
        foreach ($expected as $name => $value) {
            self::assertSame($value, MultipartParser::named($parts, $name)[0]['body'], $name);
        }

        self::assertSame('https://links.usesmileid.com/v3/2/' . self::REF, $created->link);
        self::assertSame('Smile Link created', $created->message);
        self::assertSame(self::REF, $created->refId);
        self::assertSame('user-123', $created->userId);
    }

    public function testCreateMinimalRequestForAReusableLink(): void
    {
        $mock = new MockClient(
            [MockClient::tokenResponse(), new Response(201, [], '{"link":"https://l/x","message":"Smile Link created","ref_id":"r","user_id":null}')],
            ['defaultCallbackUrl' => 'https://app.example.com/cb'],
        );

        $created = $mock->client->smileLinks->create(self::ID_TYPES, 'Onboarding', 'https://partner.example/privacy', false);

        $parts = MultipartParser::parse($mock->request(1));
        self::assertSame('false', MultipartParser::named($parts, 'is_single_use')[0]['body']);
        self::assertSame('https://app.example.com/cb', MultipartParser::named($parts, 'callback_url')[0]['body']);
        foreach (['company_name', 'description', 'expires_at', 'logo_url', 'redirect_url', 'theme_color', 'partner_params', 'user_id'] as $absent) {
            self::assertCount(0, MultipartParser::named($parts, $absent), $absent);
        }
        self::assertNull($created->userId);
    }

    public function testGetGoldenRequestAndResult(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), new Response(200, [], (string) json_encode($this->linkBody()))]);

        $link = $mock->client->smileLinks->get(self::REF);

        self::assertUnscopedToken($mock, 0);
        $request = $mock->request(1);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://testapi.smileidentity.com/v3/smile_links/' . self::REF, (string) $request->getUri());
        self::assertNotSame('', $request->getHeaderLine('SmileID-Token'));
        self::assertSame('', (string) $request->getBody());

        self::assertSame(self::REF, $link->refId);
        self::assertSame('https://links.usesmileid.com/v3/2/' . self::REF, $link->link);
        self::assertSame('Onboarding', $link->name);
        self::assertSame('Acme Pay', $link->companyName);
        self::assertNull($link->description);
        self::assertSame(self::ID_TYPES, $link->idTypes);
        self::assertTrue($link->isSingleUse);
        self::assertSame('https://partner.example/cb', $link->callbackUrl);
        self::assertSame('https://partner.example/privacy', $link->noticePrivacyPolicyUrl);
        self::assertSame('https://partner.example/logo.png', $link->logoUrl);
        self::assertNull($link->redirectUrl);
        self::assertSame('#000', $link->themeColor);
        self::assertSame('2027-01-01T00:00:00.000Z', $link->expiresAt);
        self::assertSame('2026-12-01T00:00:00.000Z', $link->disabledAt);
        self::assertSame(['order_id' => 'ORD-1'], $link->partnerParams);
        self::assertSame('user-123', $link->userId);
        self::assertSame(['country', 'id_type'], $link->tokenProvidedFields);
        self::assertSame(['country' => 'NG', 'id_type' => 'NIN'], $link->tokenProvidedValues);
        self::assertSame('2026-11-01T00:00:00.000Z', $link->createdAt);
        self::assertSame('2026-11-02T00:00:00.000Z', $link->updatedAt);
        self::assertNull($link->jobStats);
        self::assertSame($this->linkBody(), $link->data);
    }

    public function testGetParsesASparseLink(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), new Response(200, [], '{"ref_id":"r","link":"l","name":"n"}')]);

        $link = $mock->client->smileLinks->get('r');

        self::assertSame([], $link->idTypes);
        self::assertNull($link->isSingleUse);
        self::assertNull($link->partnerParams);
        self::assertNull($link->userId);
        self::assertSame([], $link->tokenProvidedFields);
        self::assertSame([], $link->tokenProvidedValues);
        self::assertNull($link->createdAt);
    }

    public function testGetRaisesNotFound(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), new Response(404, [], '{"status":"Not Found","message":"No such link."}')]);

        $this->expectException(NotFoundError::class);
        $mock->client->smileLinks->get(self::REF);
    }

    public function testListGoldenRequestAndResult(): void
    {
        $item = $this->linkBody() + ['clear' => 3, 'block' => 1, 'attention' => 2, 'error' => 0, 'successful_jobs' => 5, 'failed_jobs' => 1, 'total_jobs' => 6];
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(200, [], (string) json_encode(['links' => [$item, 'junk'], 'meta' => ['current_page' => 2, 'limit' => 5, 'total_count' => 11, 'total_pages' => 3]])),
        ]);

        $list = $mock->client->smileLinks->list(limit: 5, page: 2, singleUse: false);

        self::assertUnscopedToken($mock, 0);
        $request = $mock->request(1);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://testapi.smileidentity.com/v3/smile_links', $request->getUri()->getScheme() . '://' . $request->getUri()->getHost() . $request->getUri()->getPath());
        self::assertSame('limit=5&page=2&single_use=false', $request->getUri()->getQuery());

        self::assertCount(1, $list->links);
        self::assertSame(self::REF, $list->links[0]->refId);
        $stats = $list->links[0]->jobStats;
        self::assertNotNull($stats);
        self::assertSame([3, 1, 2, 0, 5, 1, 6], [$stats->clear, $stats->block, $stats->attention, $stats->error, $stats->successfulJobs, $stats->failedJobs, $stats->totalJobs]);
        self::assertSame([2, 5, 11, 3], [$list->currentPage, $list->limit, $list->totalCount, $list->totalPages]);
    }

    public function testListSendsNoQueryByDefaultAndSingleUseTrue(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(200, [], '{"links":[],"meta":{"current_page":1,"limit":20,"total_count":0,"total_pages":0}}'),
            MockClient::tokenResponse(),
            new Response(200, [], '{}'),
        ]);

        $list = $mock->client->smileLinks->list();
        self::assertSame('', $mock->request(1)->getUri()->getQuery());
        self::assertSame([], $list->links);

        $empty = $mock->client->smileLinks->list(singleUse: true);
        self::assertSame('single_use=true', $mock->request(3)->getUri()->getQuery());
        self::assertSame([], $empty->links);
        self::assertSame(0, $empty->totalCount);
    }

    public function testUpdateGoldenRequestAndResult(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(200, [], (string) json_encode($this->linkBody())),
            MockClient::tokenResponse(),
            new Response(200, [], (string) json_encode($this->linkBody())),
        ]);

        $link = $mock->client->smileLinks->update(self::REF, idTypes: self::ID_TYPES, isDisabled: true);

        self::assertUnscopedToken($mock, 0);
        $request = $mock->request(1);
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('https://testapi.smileidentity.com/v3/smile_links/' . self::REF, (string) $request->getUri());
        self::assertNotSame('', $request->getHeaderLine('SmileID-Token'));
        self::assertStringStartsWith('multipart/form-data; boundary=', $request->getHeaderLine('Content-Type'));
        $parts = MultipartParser::parse($request);
        self::assertSame('application/json', MultipartParser::named($parts, 'id_types')[0]['contentType']);
        self::assertSame('[{"country":"NG","id_type":"NIN","verification_method":"enhanced_kyc"}]', MultipartParser::named($parts, 'id_types')[0]['body']);
        self::assertSame('true', MultipartParser::named($parts, 'is_disabled')[0]['body']);
        self::assertSame(self::REF, $link->refId);

        // Each field may be sent alone.
        $mock->client->smileLinks->update(self::REF, isDisabled: false);
        $parts = MultipartParser::parse($mock->request(3));
        self::assertSame('false', MultipartParser::named($parts, 'is_disabled')[0]['body']);
        self::assertCount(0, MultipartParser::named($parts, 'id_types'));
    }

    public function testUpdateWithIdTypesOnly(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), new Response(200, [], (string) json_encode($this->linkBody()))]);

        $mock->client->smileLinks->update(self::REF, idTypes: self::ID_TYPES);

        $parts = MultipartParser::parse($mock->request(1));
        self::assertCount(1, MultipartParser::named($parts, 'id_types'));
        self::assertCount(0, MultipartParser::named($parts, 'is_disabled'));
    }

    public function testValidationRejectsBeforeAnyRequest(): void
    {
        $mock = new MockClient([]);
        $links = $mock->client->smileLinks;
        $privacy = 'https://partner.example/privacy';

        foreach ([
            'no id types' => fn () => $links->create([], 'n', $privacy, true),
            'blank name' => fn () => $links->create(self::ID_TYPES, ' ', $privacy, true),
            'http privacy url' => fn () => $links->create(self::ID_TYPES, 'n', 'http://partner.example/p', true),
            'http callback' => fn () => $links->create(self::ID_TYPES, 'n', $privacy, true, callbackUrl: 'http://partner.example/cb'),
            'http logo' => fn () => $links->create(self::ID_TYPES, 'n', $privacy, true, logoUrl: 'http://partner.example/l.png'),
            'http redirect' => fn () => $links->create(self::ID_TYPES, 'n', $privacy, true, redirectUrl: 'http://partner.example/r'),
            'limit too low' => fn () => $links->list(limit: 0),
            'limit too high' => fn () => $links->list(limit: 101),
            'page too low' => fn () => $links->list(page: 0),
            'blank get ref' => fn () => $links->get(' '),
            'blank update ref' => fn () => $links->update('', isDisabled: true),
            'empty update' => fn () => $links->update(self::REF),
            'empty update id types' => fn () => $links->update(self::REF, idTypes: []),
            'too many id types' => fn () => $links->update(self::REF, idTypes: array_fill(0, 51, self::ID_TYPES[0])),
        ] as $label => $call) {
            try {
                $call();
                self::fail('Expected a ValidationError: ' . $label);
            } catch (ValidationError) {
                self::addToAssertionCount(1);
            }
        }
        self::assertCount(0, $mock->history);
    }
}
