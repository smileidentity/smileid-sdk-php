<?php

declare(strict_types=1);

namespace SmileIdentity\Client\Resources;

use SmileIdentity\Client\Transport;
use SmileIdentity\Errors\ValidationError;
use SmileIdentity\Generated\Models\ReportUserFraudResponse;
use SmileIdentity\Generated\Operations\Operations;

/**
 * users.reportFraud → POST /v3/users/{user_id}/report_fraud
 * users.flagFraud / users.clearFraud → convenience wrappers over reportFraud.
 */
final class UsersResource
{
    public const REASONS = [
        'FIRST_PARTY_FRAUD',
        'SECOND_PARTY_FRAUD',
        'THIRD_PARTY_FRAUD',
        'SYNTHETIC_IDENTITY',
        'ACCOUNT_TAKEOVER',
        'DOCUMENT_FORGERY',
        'IDENTITY_FARMING',
        'MULE_ACCOUNT',
        'OTHER',
    ];

    public function __construct(
        private readonly Transport $transport,
    ) {
    }

    public function reportFraud(
        string $userId,
        bool $isFraud,
        string $reportedBy,
        ?string $reason = null,
        ?string $notes = null,
    ): ReportUserFraudResponse {
        $this->validate($isFraud, $reason, $notes);

        $data = Operations::reportFraud($this->transport, $userId, $isFraud, $reportedBy, $reason, $notes);

        return ReportUserFraudResponse::fromArray($data);
    }

    public function flagFraud(
        string $userId,
        string $reason,
        string $reportedBy,
        ?string $notes = null,
    ): ReportUserFraudResponse {
        return $this->reportFraud($userId, true, $reportedBy, $reason, $notes);
    }

    public function clearFraud(
        string $userId,
        string $notes,
        string $reportedBy,
    ): ReportUserFraudResponse {
        return $this->reportFraud($userId, false, $reportedBy, null, $notes);
    }

    private function validate(bool $isFraud, ?string $reason, ?string $notes): void
    {
        if ($isFraud) {
            if ($reason === null || $reason === '') {
                throw new ValidationError('reason is required when is_fraud is true.');
            }
            if (!in_array($reason, self::REASONS, true)) {
                throw new ValidationError('reason is not a recognised value.');
            }
        }

        $notesRequired = !$isFraud || $reason === 'OTHER';
        if ($notesRequired && ($notes === null || $notes === '')) {
            throw new ValidationError('notes is required when is_fraud is false or reason is OTHER.');
        }
        if ($notes !== null && mb_strlen($notes) > 500) {
            throw new ValidationError('notes must be 500 characters or fewer.');
        }
    }
}
