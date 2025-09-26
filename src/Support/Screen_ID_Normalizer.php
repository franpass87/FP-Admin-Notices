<?php
/**
 * Normalizes screen identifiers for consistent comparisons.
 */
declare(strict_types=1);

namespace FP\AdminNotices\Support;

use function preg_replace;
use function strtolower;
use function trim;

/**
 * Provides helpers to normalize admin screen identifiers.
 */
final class Screen_ID_Normalizer
{
    /**
     * Normalize a screen identifier keeping only safe characters.
     */
    public static function normalize( string $screen ): string
    {
        $screen = strtolower( trim( $screen ) );
        $screen = preg_replace( '/[^a-z0-9._-]/', '', $screen );

        return $screen ?? '';
    }
}
