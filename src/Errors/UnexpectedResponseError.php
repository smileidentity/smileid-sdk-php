<?php

declare(strict_types=1);

namespace SmileIdentity\Errors;

/**
 * A success (2xx) response body was not a JSON object — for example an HTML
 * gateway page or an empty body. Carries the status code, the raw body and
 * the request id when the server sent one.
 */
class UnexpectedResponseError extends SmileIDError
{
}
