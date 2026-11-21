<?php

declare(strict_types=1);

namespace SmileIdentity\Helpers;

/** Reads the Smile job id the API returns in the SmileID-Job-ID response header. */
final class JobId
{
    /**
     * The job id from the SmileID-Job-ID header, or null when the header is
     * absent or empty. Header names are matched case-insensitively.
     *
     * @param array<string, string> $headers response headers
     */
    public static function fromHeaders(array $headers): ?string
    {
        $jobId = array_change_key_case($headers, CASE_LOWER)['smileid-job-id'] ?? null;

        return $jobId !== null && $jobId !== '' ? $jobId : null;
    }
}
