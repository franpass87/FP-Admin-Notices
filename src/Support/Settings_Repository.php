<?php
/**
 * Provides a typed repository for plugin settings.
 */
declare(strict_types=1);

namespace FP\AdminNotices\Support;

use function add_option;
use function get_option;
use function is_array;
use function update_option;
use function wp_parse_args;

/**
 * Stores and retrieves plugin settings with caching.
 */
class Settings_Repository
{
    public const OPTION_NAME = 'fp_admin_notices_settings';

    private ?array $cache = null;

    /**
     * Retrieve the option name used to store settings.
     */
    public function option_name(): string
    {
        return self::OPTION_NAME;
    }

    /**
     * Retrieve default settings.
     */
    public function defaults(): array
    {
        return [
            'allowed_roles'      => ['administrator'],
            'include_update_nag' => false,
            'auto_open_critical' => true,
            'allowed_screens'    => [],
        ];
    }

    /**
     * Retrieve all settings merged with defaults.
     */
    public function all(): array
    {
        if ( null === $this->cache ) {
            $stored = get_option( self::OPTION_NAME, [] );
            if ( ! is_array( $stored ) ) {
                $stored = [];
            }

            $this->cache = wp_parse_args( $stored, $this->defaults() );
        }

        return $this->cache;
    }

    /**
     * Retrieve a single setting value.
     *
     * @param mixed $default Default value when the key is not set.
     * @return mixed
     */
    public function get( string $key, $default = null )
    {
        $settings = $this->all();

        return $settings[ $key ] ?? $default;
    }

    /**
     * Update all settings at once.
     */
    public function update( array $settings ): void
    {
        $merged       = wp_parse_args( $settings, $this->defaults() );
        $this->cache  = $merged;
        update_option( self::OPTION_NAME, $merged, false );
    }

    /**
     * Prime the in-memory cache with a known value.
     */
    public function prime( array $settings ): void
    {
        $this->cache = wp_parse_args( $settings, $this->defaults() );
    }

    /**
     * Ensure the option exists with default values.
     */
    public function ensure_defaults_exist(): void
    {
        $current = get_option( self::OPTION_NAME, null );
        if ( null === $current ) {
            add_option( self::OPTION_NAME, $this->defaults(), '', 'no' );
            $this->cache = $this->defaults();
            return;
        }

        if ( ! is_array( $current ) ) {
            $this->update( [] );
            return;
        }

        $merged = wp_parse_args( $current, $this->defaults() );

        if ( $merged !== $current ) {
            update_option( self::OPTION_NAME, $merged, false );
        }

        $this->cache = $merged;
    }
}
