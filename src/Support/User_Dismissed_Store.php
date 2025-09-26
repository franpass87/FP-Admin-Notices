<?php
/**
 * Handles dismissed notices per user.
 */
declare(strict_types=1);

namespace FP\AdminNotices\Support;

use function array_diff;
use function array_filter;
use function array_unique;
use function array_values;
use function get_current_user_id;
use function get_user_meta;
use function sanitize_text_field;
use function update_user_meta;
use function in_array;

/**
 * Provides utilities to persist dismissed notices state per user.
 */
class User_Dismissed_Store
{
    public const META_KEY = 'fp_admin_notices_dismissed';

    /**
     * Retrieve dismissed notice IDs for a user.
     *
     * @param int $user_id Optional user ID. Defaults to current user.
     * @return array<int, string>
     */
    public function get( int $user_id = 0 ): array
    {
        $user_id = $user_id > 0 ? $user_id : get_current_user_id();
        if ( $user_id <= 0 ) {
            return [];
        }

        $dismissed = get_user_meta( $user_id, self::META_KEY, true );
        if ( ! is_array( $dismissed ) ) {
            return [];
        }

        $dismissed = array_map( static function ( $notice_id ): string {
            return sanitize_text_field( (string) $notice_id );
        }, $dismissed );

        return array_values( array_unique( array_filter( $dismissed ) ) );
    }

    /**
     * Update the dismissed state for a notice.
     *
     * @param string $notice_id Notice identifier.
     * @param bool   $dismissed Whether the notice is dismissed.
     * @param int    $user_id   Optional user ID. Defaults to current user.
     */
    public function update( string $notice_id, bool $dismissed, int $user_id = 0 ): void
    {
        $user_id = $user_id > 0 ? $user_id : get_current_user_id();
        if ( $user_id <= 0 ) {
            return;
        }

        $notice_id = sanitize_text_field( $notice_id );
        if ( '' === $notice_id ) {
            return;
        }

        $stored = $this->get( $user_id );

        if ( $dismissed ) {
            if ( ! in_array( $notice_id, $stored, true ) ) {
                $stored[] = $notice_id;
            }
        } else {
            $stored = array_values( array_diff( $stored, [ $notice_id ] ) );
        }

        update_user_meta( $user_id, self::META_KEY, $stored );
    }
}
