<?php

declare(strict_types=1);

namespace SmileIdentity\Errors;

/** HTTP 404. Not raised by verifications.retrieve — see the JobStatus not_found path. */
class NotFoundError extends SmileIDError
{
}
