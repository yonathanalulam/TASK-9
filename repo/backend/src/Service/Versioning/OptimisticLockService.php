<?php

declare(strict_types=1);

namespace App\Service\Versioning;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OptimisticLockService
{
    /**
     * Check that the client-supplied version matches the current entity version.
     *
     * Reads the expected version from the If-Match header first, then falls back
     * to X-Expected-Version. If neither is present, responds with 428 Precondition
     * Required. If present but mismatched, responds with 409 Conflict.
     */
    public static function checkVersion(Request $request, int $currentVersion): void
    {
        $expectedVersion = self::getExpectedVersion($request);

        if ($expectedVersion === null) {
            throw new HttpException(428, 'A version identifier is required. Provide an If-Match or X-Expected-Version header.');
        }

        if ($expectedVersion !== $currentVersion) {
            throw new ConflictHttpException(sprintf(
                'Version conflict: expected version %d but current version is %d. The resource has been modified by another request.',
                $expectedVersion,
                $currentVersion,
            ));
        }
    }

    /**
     * Extract the expected version from the request headers.
     *
     * Returns the version from If-Match (stripping ETag quotes) or
     * X-Expected-Version, or null if neither is present.
     */
    public static function getExpectedVersion(Request $request): ?int
    {
        $ifMatch = $request->headers->get('If-Match');

        if ($ifMatch !== null && $ifMatch !== '') {
            return (int) trim($ifMatch, '"');
        }

        $expectedVersion = $request->headers->get('X-Expected-Version');

        if ($expectedVersion !== null && $expectedVersion !== '') {
            return (int) $expectedVersion;
        }

        return null;
    }
}
