<?php

declare(strict_types=1);

namespace SmileIdentity\Client\Resources;

use SmileIdentity\Client\Transport;
use SmileIdentity\Errors\TimeoutError;
use SmileIdentity\Generated\Models\JobStatus;
use SmileIdentity\Generated\Models\ReplayCallbackResponse;
use SmileIdentity\Generated\Operations\Operations;
use SmileIdentity\Helpers\Url;

/**
 * verifications.retrieve → GET /v3/status/{jobId}
 * verifications.waitUntilComplete → poll helper
 * verifications.replay → POST /v3/replay/{job_id}
 */
final class VerificationsResource
{
    /** @var callable(float): void */
    private $sleeper;

    /**
     * @param (callable(float): void)|null $sleeper injectable for tests
     */
    public function __construct(
        private readonly Transport $transport,
        ?callable $sleeper = null,
    ) {
        $this->sleeper = $sleeper ?? static function (float $seconds): void {
            if ($seconds > 0) {
                usleep((int) ($seconds * 1_000_000));
            }
        };
    }

    /**
     * A 404 returns a JobStatus with status=not_found rather than raising, so
     * polling can tell "not found yet" from other failures.
     */
    public function retrieve(string $jobId): JobStatus
    {
        return JobStatus::fromArray(Operations::statusRetrieve($this->transport, $jobId));
    }

    /**
     * Poll until the job is complete.
     *
     * @throws TimeoutError when the deadline passes before completion
     */
    public function waitUntilComplete(
        string $jobId,
        float $interval = 2.0,
        float $timeout = 60.0,
        bool $treatNotFoundAsPending = true,
    ): JobStatus {
        $deadline = microtime(true) + $timeout;

        while (true) {
            $status = $this->retrieve($jobId);

            if ($status->isComplete) {
                return $status;
            }
            if ($status->isNotFound && !$treatNotFoundAsPending) {
                return $status;
            }
            if (microtime(true) >= $deadline) {
                throw new TimeoutError("Timed out waiting for job {$jobId} to complete.");
            }

            ($this->sleeper)($interval);
        }
    }

    public function replay(string $jobId, ?string $callbackUrl = null): ReplayCallbackResponse
    {
        Url::requireHttpsCallback($callbackUrl);

        return ReplayCallbackResponse::fromArray(Operations::replay($this->transport, $jobId, $callbackUrl));
    }
}
