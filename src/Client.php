<?php

declare(strict_types=1);

namespace SmileIdentity;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use SmileIdentity\Client\Config;
use SmileIdentity\Client\Resources\BiometricKycResource;
use SmileIdentity\Client\Resources\BiometricResource;
use SmileIdentity\Client\Resources\DocumentsResource;
use SmileIdentity\Client\Resources\EnhancedKycResource;
use SmileIdentity\Client\Resources\ServicesResource;
use SmileIdentity\Client\Resources\UsersResource;
use SmileIdentity\Client\Resources\VerificationsResource;
use SmileIdentity\Client\Transport;

/**
 * The Smile ID V3 server-side client.
 *
 * Construct once with your partner credentials, then reach an operation through
 * its resource namespace, e.g. `$client->enhancedKyc->verify(...)`.
 */
final class Client
{
    public readonly Config $config;
    private readonly Transport $transport;

    public readonly EnhancedKycResource $enhancedKyc;
    public readonly DocumentsResource $documents;
    public readonly BiometricKycResource $biometricKyc;
    public readonly BiometricResource $biometric;
    public readonly VerificationsResource $verifications;
    public readonly UsersResource $users;
    public readonly ServicesResource $services;

    /**
     * @param 'sandbox'|'production'|string $environment
     * @param (callable(float): void)|null $sleeper injectable delay hook (tests)
     */
    public function __construct(
        string $partnerId,
        string $apiKey,
        string $environment = 'sandbox',
        ?string $partnerSecret = null,
        ?string $defaultCallbackUrl = null,
        ?string $baseUrl = null,
        bool $allowInsecureBaseUrl = false,
        float $timeout = 30.0,
        int $maxRetries = 2,
        ?ClientInterface $httpClient = null,
        ?callable $sleeper = null,
    ) {
        $this->config = new Config(
            partnerId: $partnerId,
            apiKey: $apiKey,
            environment: $environment,
            partnerSecret: $partnerSecret,
            defaultCallbackUrl: $defaultCallbackUrl,
            baseUrl: $baseUrl,
            allowInsecureBaseUrl: $allowInsecureBaseUrl,
            timeout: $timeout,
            maxRetries: $maxRetries,
        );

        $transportSleeper = $sleeper !== null ? \Closure::fromCallable($sleeper) : null;
        $this->transport = new Transport(
            $this->config,
            $httpClient ?? new GuzzleClient(),
            $transportSleeper,
        );

        $this->enhancedKyc = new EnhancedKycResource($this->transport, $this->config);
        $this->documents = new DocumentsResource($this->transport, $this->config);
        $this->biometricKyc = new BiometricKycResource($this->transport, $this->config);
        $this->biometric = new BiometricResource($this->transport, $this->config);
        $this->verifications = new VerificationsResource($this->transport, $sleeper);
        $this->users = new UsersResource($this->transport);
        $this->services = new ServicesResource($this->transport);
    }
}
