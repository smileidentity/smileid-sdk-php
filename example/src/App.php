<?php

declare(strict_types=1);

namespace SmileIdentity\Example;

use GuzzleHttp\ClientInterface;
use SmileIdentity\Client;
use SmileIdentity\Consent;
use SmileIdentity\Errors\SmileIDError;

final class UsageError extends \RuntimeException
{
}

final class App
{
    /**
     * @param list<string> $argv
     * @param array<string, string> $env
     */
    public function run(array $argv, array $env, mixed $stdout = null, mixed $stderr = null, ?ClientInterface $httpClient = null): int
    {
        $stdout ??= fopen('php://stdout', 'w');
        $stderr ??= fopen('php://stderr', 'w');
        try {
            [$config, $command, $args] = $this->parseGlobalFlags($argv, $env);
            if (in_array($command, ['help', '-h', '--help'], true)) {
                fwrite($stdout, $this->usage());
                return 0;
            }
            if ($command === null) {
                throw new UsageError('missing command; run one of: services, enhanced-kyc, status, replay');
            }
            $this->validateConfig($config);
            $client = new Client(
                partnerId: $config['partnerId'],
                apiKey: $config['apiKey'],
                defaultCallbackUrl: $config['callbackUrl'],
                baseUrl: $config['baseUrl'],
                timeout: (float) $config['timeout'],
                httpClient: $httpClient,
            );

            match ($command) {
                'services' => $this->services($client, $args, $stdout),
                'enhanced-kyc' => $this->enhancedKyc($client, $args, $config, $stdout),
                'status' => $this->status($client, $args, $stdout),
                'replay' => $this->replay($client, $args, $stdout),
                default => throw new UsageError("unknown command {$command}"),
            };
            return 0;
        } catch (UsageError $e) {
            fwrite($stderr, $e->getMessage() . PHP_EOL);
            return 2;
        } catch (SmileIDError $e) {
            $suffix = $e->statusCode === null ? '' : " (HTTP {$e->statusCode})";
            fwrite($stderr, get_class($e) . ': ' . $e->getMessage() . $suffix . PHP_EOL);
            return 1;
        }
    }

    /**
     * @param list<string> $argv
     * @param array<string, string> $env
     * @return array{0: array<string, mixed>, 1: ?string, 2: list<string>}
     */
    private function parseGlobalFlags(array $argv, array $env): array
    {
        $config = [
            'partnerId' => $env['SMILE_PARTNER_ID'] ?? '',
            'apiKey' => $env['SMILE_API_KEY'] ?? '',
            'baseUrl' => $this->blankToNull($env['SMILE_BASE_URL'] ?? null),
            'callbackUrl' => $this->blankToNull($env['SMILE_CALLBACK_URL'] ?? null),
            'timeout' => $env['SMILE_TIMEOUT'] ?? '30',
        ];
        $rest = [];
        $i = 0;
        for (; $i < count($argv); $i++) {
            $arg = $argv[$i];
            if (!str_starts_with($arg, '--')) {
                break;
            }
            if ($i + 1 >= count($argv)) {
                throw new UsageError("{$arg} requires a value");
            }
            $value = $argv[++$i];
            match ($arg) {
                '--partner-id' => $config['partnerId'] = $value,
                '--api-key' => $config['apiKey'] = $value,
                '--base-url' => $config['baseUrl'] = $value,
                '--callback-url' => $config['callbackUrl'] = $value,
                '--timeout' => $config['timeout'] = $value,
                default => throw new UsageError("unknown global flag {$arg}"),
            };
        }
        $rest = array_slice($argv, $i);
        $command = array_shift($rest);
        // Global flags only bind before the command. Anything global that turns
        // up after it used to be dropped without a word, so `status --job-id X
        // --base-url https://your-environment.example.com` quietly talked to
        // the default host.
        // ponytail: --callback-url is left out on purpose — it is also a
        // per-command flag on enhanced-kyc and replay.
        foreach ($rest as $arg) {
            if (in_array($arg, ['--partner-id', '--api-key', '--base-url', '--timeout'], true)) {
                throw new UsageError("{$arg} is a global flag and must come before the command");
            }
        }
        return [$config, $command, $rest];
    }

    /** @param array<string, mixed> $config */
    private function validateConfig(array $config): void
    {
        $missing = [];
        if ($config['partnerId'] === '') {
            $missing[] = 'SMILE_PARTNER_ID or --partner-id';
        }
        if ($config['apiKey'] === '') {
            $missing[] = 'SMILE_API_KEY or --api-key';
        }
        if ($missing !== []) {
            throw new UsageError('missing ' . implode(' and ', $missing));
        }
    }

    /** @param list<string> $args */
    private function services(Client $client, array $args, mixed $stdout): void
    {
        $country = $this->flag($args, '--country') ?? 'NG';
        $banks = $client->services->bankCodes(country: $country);
        $idTypes = $client->services->supportedIdTypes(country: $country);
        $docs = $client->services->supportedDocuments(countryCode: $country);
        $this->writeJson($stdout, [
            'country' => $country,
            'bank_codes' => array_map(fn ($b) => [
                'code' => $b->code,
                'country' => $b->country,
                'name' => $b->name,
            ], $banks->bankCodes),
            'id_types' => array_map(fn ($i) => [
                'type' => $i->type,
                'country' => $i->country,
                'label' => $i->label,
                'regex' => $i->regex,
                'required_fields' => $i->requiredFields,
                'bank_code' => $i->bankCode,
            ], $idTypes->idTypes),
            'documents' => array_map(fn ($d) => [
                'country' => $d->country === null ? null : [
                    'code' => $d->country->code,
                    'name' => $d->country->name,
                    'continent' => $d->country->continent,
                ],
                'id_types' => array_map(fn ($idType) => [
                    'code' => $idType->code,
                    'name' => $idType->name,
                    'example' => $idType->example,
                    'has_back' => $idType->hasBack,
                ], $d->idTypes),
            ], $docs->validDocuments),
        ]);
    }

    /**
     * @param list<string> $args
     * @param array<string, mixed> $config
     */
    private function enhancedKyc(Client $client, array $args, array $config, mixed $stdout): void
    {
        $accepted = $client->enhancedKyc->verify(
            country: $this->flag($args, '--country') ?? 'NG',
            idType: $this->requiredFlag($args, '--id-type'),
            idNumber: $this->requiredFlag($args, '--id-number'),
            userDetails: array_filter([
                'given_names' => $this->requiredFlag($args, '--given-names'),
                'last_name' => $this->requiredFlag($args, '--last-name'),
                'email' => $this->flag($args, '--email'),
                'phone_number' => $this->flag($args, '--phone-number'),
            ]),
            consent: Consent::granted(
                grantedAt: new \DateTimeImmutable(),
                noticeLanguage: 'EN',
                noticePrivacyPolicyUrl: $this->flag($args, '--privacy-url') ?? 'https://example.com/privacy',
            ),
            callbackUrl: $this->flag($args, '--callback-url') ?? $config['callbackUrl'],
        );
        $this->writeJson($stdout, [
            'status' => $accepted->status,
            'message' => $accepted->message,
            'job_id' => $accepted->jobId,
            'user_id' => $accepted->userId,
            'accepted' => $accepted->isAccepted,
        ]);
    }

    /** @param list<string> $args */
    private function status(Client $client, array $args, mixed $stdout): void
    {
        $status = $client->verifications->retrieve($this->requiredFlag($args, '--job-id'));
        $this->writeJson($stdout, [
            'status' => $status->status,
            'message' => $status->message,
            'job_id' => $status->jobId,
            'user_id' => $status->userId,
        ]);
    }

    /** @param list<string> $args */
    private function replay(Client $client, array $args, mixed $stdout): void
    {
        $replay = $client->verifications->replay(
            $this->requiredFlag($args, '--job-id'),
            callbackUrl: $this->flag($args, '--callback-url'),
        );
        $this->writeJson($stdout, [
            'status' => $replay->status,
            'message' => $replay->message,
            'job_id' => $replay->jobId,
            'user_id' => $replay->userId,
        ]);
    }

    /** @param list<string> $args */
    private function requiredFlag(array $args, string $name): string
    {
        return $this->flag($args, $name) ?? throw new UsageError("{$name} is required");
    }

    /** @param list<string> $args */
    private function flag(array $args, string $name): ?string
    {
        $index = array_search($name, $args, true);
        if ($index === false) {
            return null;
        }
        if (!isset($args[$index + 1])) {
            throw new UsageError("{$name} requires a value");
        }
        return $args[$index + 1];
    }

    private function writeJson(mixed $stdout, mixed $value): void
    {
        fwrite($stdout, json_encode($value, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL);
    }

    private function blankToNull(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }

    private function usage(): string
    {
        return <<<'USAGE'
Usage:
  smileid-example-php [global flags] services --country NG
  smileid-example-php [global flags] enhanced-kyc --country NG --id-type NIN --id-number 12345678901 --given-names "Amina Fatou" --last-name Clearwater --email amina.clearwater@example.com --privacy-url https://example.com/privacy
  smileid-example-php [global flags] status --job-id job_...
  smileid-example-php [global flags] replay --job-id job_... --callback-url https://example.com/webhook

Global flags are --partner-id, --api-key, --base-url, --callback-url and --timeout. They go before the
command, and can also be set with SMILE_PARTNER_ID, SMILE_API_KEY, SMILE_BASE_URL, SMILE_CALLBACK_URL
and SMILE_TIMEOUT.

Partner ids are displayed zero-padded (for example 002) but must be passed without leading zeros (2).

Non-production environments match test identities on given names + last name + email. An unrecognised
identity resolves to block.
USAGE;
    }
}
