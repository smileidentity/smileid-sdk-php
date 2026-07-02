<?php

declare(strict_types=1);

namespace SmileIdentity\Client;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\MultipartStream;
use Psr\Http\Message\ResponseInterface;
use SmileIdentity\Client\Auth\HmacSigner;
use SmileIdentity\Client\Auth\TokenManager;
use SmileIdentity\Errors\AuthenticationError;
use SmileIdentity\Errors\ConnectionError;
use SmileIdentity\Errors\ErrorFactory;
use SmileIdentity\Version;

/**
 * The single layer that touches HTTP (§2.2). Attaches auth + telemetry, signs
 * when enabled, serializes the body (§5.3), sends, retries idempotent ops
 * (§2.6), and turns failures into typed errors (§7).
 */
final class Transport
{
    private const RETRYABLE_STATUSES = [408, 429, 500, 502, 503, 504];

    private readonly TokenManager $tokenManager;
    private readonly ?HmacSigner $signer;
    /** @var \Closure(float): void */
    private readonly \Closure $sleeper;

    public function __construct(
        private readonly Config $config,
        private readonly ClientInterface $httpClient,
        ?\Closure $sleeper = null,
    ) {
        $this->signer = $config->hmacEnabled()
            ? new HmacSigner((string) $config->partnerSecret)
            : null;
        $this->sleeper = $sleeper ?? static function (float $seconds): void {
            if ($seconds > 0) {
                usleep((int) ($seconds * 1_000_000));
            }
        };
        $this->tokenManager = new TokenManager(fn (): string => $this->fetchToken());
    }

    public function tokenManager(): TokenManager
    {
        return $this->tokenManager;
    }

    /**
     * Send a request and return the decoded JSON body.
     *
     * @param list<int> $nonErrorStatuses HTTP statuses to return instead of raising
     *     (e.g. 404 on the status endpoint, which carries a JobStatus body)
     *
     * @return array<string, mixed>
     */
    public function send(ApiRequest $request, array $nonErrorStatuses = []): array
    {
        $refreshedOn401 = false;
        $attempt = 0;

        while (true) {
            [$headers, $body] = $this->prepare($request);

            try {
                $response = $this->dispatch($request, $headers, $body);
            } catch (ConnectException $e) {
                if ($request->idempotent && $attempt < $this->config->maxRetries) {
                    ++$attempt;
                    ($this->sleeper)($this->backoffSeconds($attempt, null));
                    continue;
                }
                throw new ConnectionError($e->getMessage(), previous: $e);
            } catch (GuzzleException $e) {
                throw new ConnectionError($e->getMessage(), previous: $e);
            }

            $status = $response->getStatusCode();
            $raw = (string) $response->getBody();

            if (($status >= 200 && $status < 300) || in_array($status, $nonErrorStatuses, true)) {
                return self::decode($raw);
            }

            if ($status === 401 && $request->authenticated && !$refreshedOn401) {
                $refreshedOn401 = true;
                $this->tokenManager->invalidate();
                continue;
            }

            if ($request->idempotent
                && $attempt < $this->config->maxRetries
                && in_array($status, self::RETRYABLE_STATUSES, true)
            ) {
                ++$attempt;
                ($this->sleeper)($this->backoffSeconds($attempt, $response));
                continue;
            }

            throw ErrorFactory::fromResponse(
                $status,
                $raw,
                $response->getReasonPhrase(),
                self::lowercaseHeaders($response),
            );
        }
    }

    /**
     * Build the final headers and serialized body for one attempt.
     *
     * @return array{0: array<string, string>, 1: ?string}
     */
    private function prepare(ApiRequest $request): array
    {
        $headers = $this->telemetryHeaders();

        if ($request->authenticated) {
            $headers['SmileID-Token'] = $this->tokenManager->ensureToken();
        }
        if ($request->needsPartnerIdHeader) {
            $headers['SmileID-Partner-ID'] = $this->config->partnerId;
        }
        if ($request->userIdHeader !== null && $request->userIdHeader !== '') {
            $headers['User-ID'] = $request->userIdHeader;
        }

        $body = null;
        if ($request->bodyKind === ApiRequest::BODY_MULTIPART) {
            $stream = new MultipartStream(self::rewindResources($request->multipart));
            $headers['Content-Type'] = 'multipart/form-data; boundary=' . $stream->getBoundary();
            $body = (string) $stream;
        } elseif ($request->bodyKind === ApiRequest::BODY_JSON) {
            $encoded = json_encode($request->jsonBody ?? [], JSON_UNESCAPED_SLASHES);
            $body = $encoded === false ? '{}' : $encoded;
            $headers['Content-Type'] = 'application/json';
        }

        if ($this->signer !== null) {
            $headers = array_merge($headers, $this->signer->headers($body ?? ''));
        }

        return [$headers, $body];
    }

    /**
     * @param array<string, string> $headers
     */
    private function dispatch(ApiRequest $request, array $headers, ?string $body): ResponseInterface
    {
        $options = [
            'headers' => $headers,
            'http_errors' => false,
            'timeout' => $this->config->timeout,
        ];
        if ($request->query !== []) {
            $options['query'] = $request->query;
        }
        if ($body !== null) {
            $options['body'] = $body;
        }

        return $this->httpClient->request($request->method, $this->config->baseUrl . $request->path, $options);
    }

    /**
     * Fetch a fresh JWT from POST /v3/token (idempotent, retryable, no token).
     */
    private function fetchToken(): string
    {
        // The token endpoint documents lowercase header names; send verbatim.
        $attempt = 0;
        while (true) {
            $headers = array_merge($this->telemetryHeaders(), [
                'smileid-partner-id' => $this->config->partnerId,
                'smileid-api-key' => $this->config->apiKey,
            ]);

            try {
                $response = $this->httpClient->request('POST', $this->config->baseUrl . '/v3/token', [
                    'headers' => $headers,
                    'http_errors' => false,
                    'timeout' => $this->config->timeout,
                ]);
            } catch (ConnectException $e) {
                if ($attempt < $this->config->maxRetries) {
                    ++$attempt;
                    ($this->sleeper)($this->backoffSeconds($attempt, null));
                    continue;
                }
                throw new ConnectionError($e->getMessage(), previous: $e);
            } catch (GuzzleException $e) {
                throw new ConnectionError($e->getMessage(), previous: $e);
            }

            $status = $response->getStatusCode();
            $raw = (string) $response->getBody();

            if ($status >= 200 && $status < 300) {
                $data = self::decode($raw);
                $token = $data['token'] ?? null;
                if (!is_string($token) || $token === '') {
                    throw new AuthenticationError('Token endpoint returned no token.', statusCode: $status, rawBody: $raw);
                }

                return $token;
            }

            if ($attempt < $this->config->maxRetries && in_array($status, self::RETRYABLE_STATUSES, true)) {
                ++$attempt;
                ($this->sleeper)($this->backoffSeconds($attempt, $response));
                continue;
            }

            throw ErrorFactory::fromResponse($status, $raw, $response->getReasonPhrase(), self::lowercaseHeaders($response));
        }
    }

    /**
     * @return array<string, string>
     */
    private function telemetryHeaders(): array
    {
        return [
            'SmileID-Source-SDK' => 'php',
            'SmileID-Source-SDK-Version' => Version::VERSION,
            'User-Agent' => sprintf('smileid-sdk-php/%s (PHP/%s)', Version::VERSION, PHP_VERSION),
        ];
    }

    private const MAX_RETRY_AFTER_SECONDS = 60.0;

    private function backoffSeconds(int $attempt, ?ResponseInterface $response): float
    {
        if ($response !== null) {
            $retryAfter = self::retryAfterSeconds($response->getHeaderLine('Retry-After'));
            if ($retryAfter !== null) {
                return min($retryAfter, self::MAX_RETRY_AFTER_SECONDS);
            }
        }

        $base = 0.2 * (2 ** ($attempt - 1));
        $jitter = (mt_rand(0, 100) / 1000);

        return $base + $jitter;
    }

    /**
     * Parse a Retry-After header value: either delta-seconds or an RFC 7231
     * HTTP-date. Returns null when absent or unparseable; never negative.
     */
    private static function retryAfterSeconds(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return max(0.0, (float) $value);
        }

        // RFC 7231 IMF-fixdate; HTTP-dates are always GMT. The literal format
        // avoids DateTimeInterface::RFC7231, deprecated in PHP 8.5.
        $when = \DateTimeImmutable::createFromFormat('D, d M Y H:i:s \G\M\T', $value, new \DateTimeZone('UTC'));
        if ($when === false) {
            return null;
        }

        return max(0.0, (float) ($when->getTimestamp() - time()));
    }

    /**
     * Rewind any resource-backed multipart contents so retries re-read them.
     *
     * @param list<array<string, mixed>> $parts
     *
     * @return list<array<string, mixed>>
     */
    private static function rewindResources(array $parts): array
    {
        foreach ($parts as $part) {
            if (isset($part['contents']) && is_resource($part['contents'])) {
                @rewind($part['contents']);
            }
        }

        return $parts;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, string>
     */
    private static function lowercaseHeaders(ResponseInterface $response): array
    {
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[strtolower($name)] = implode(', ', $values);
        }

        return $headers;
    }
}
