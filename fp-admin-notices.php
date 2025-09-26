<?php
/**
 * Plugin Name: FP Admin Notices
 * Description: Centralizza le admin notices di WordPress in un pannello accessibile dall'admin bar.
 * Version: 1.0.0
 * Author: FP
 * Text Domain: fp-admin-notices
 * Domain Path: /languages
 */
declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require __DIR__ . '/vendor/autoload.php';
}

if ( class_exists( '\\FP\\AdminNotices\\Plugin' ) ) {
    register_activation_hook( __FILE__, [ '\\FP\\AdminNotices\\Plugin', 'activate' ] );
    \FP\AdminNotices\Plugin::register( __FILE__ );
}
