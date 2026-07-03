<?php

declare(strict_types=1);

namespace SmileIdentity\Helpers;

use SmileIdentity\Client\Config;
use SmileIdentity\Errors\ValidationError;

final class Validation
{
    public static function callbackUrl(?string $url): void
    {
        if ($url === null || trim($url) === '') {
            return;
        }
        Config::validateCallbackUrl($url);
    }

    /**
     * @param array<int, mixed>|null $images
     */
    public static function livenessImages(?array $images): void
    {
        $count = count($images ?? []);
        if ($count < 6 || $count > 8) {
            throw new ValidationError('liveness_images must contain 6 to 8 images.');
        }
    }

    /**
     * @param array<int, mixed>|null $images
     */
    public static function optionalLivenessImages(?array $images): void
    {
        if ($images !== null) {
            self::livenessImages($images);
        }
    }
}
