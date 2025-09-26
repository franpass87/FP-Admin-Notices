<?php
/**
 * Main plugin bootstrap class.
 */
declare(strict_types=1);

namespace FP\AdminNotices;

use FP\AdminNotices\Admin\Panel;
use FP\AdminNotices\Admin\Settings;
use FP\AdminNotices\REST\Notice_State_Controller;
use FP\AdminNotices\Support\Screen_ID_Normalizer;
use FP\AdminNotices\Support\Settings_Repository;
use FP\AdminNotices\Support\User_Dismissed_Store;
use WP_Admin_Bar;
use WP_Site;
use function __;
use function add_action;
use function admin_url;
use function apply_filters;
use function array_filter;
use function array_intersect;
use function array_map;
use function array_values;
use function esc_attr__;
use function esc_html__;
use function esc_url;
use function esc_url_raw;
use function file_exists;
use function filemtime;
use function function_exists;
use function get_current_screen;
use function get_current_blog_id;
use function get_file_data;
use function get_sites;
use function in_array;
use function is_admin;
use function is_multisite;
use function is_user_logged_in;
use function load_plugin_textdomain;
use function plugin_basename;
use function plugin_dir_path;
use function plugin_dir_url;
use function rest_url;
use function restore_current_blog;
use function switch_to_blog;
use function trailingslashit;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_get_current_user;
use function wp_localize_script;
use function wp_set_script_translations;

/**
 * Main plugin orchestration.
 */
class Plugin
{
    private const VERSION_FALLBACK = '1.0.0';

    private string $plugin_file;

    private string $plugin_dir;

    private string $plugin_url;

    private string $version;

    private Settings $settings;

    private Settings_Repository $settings_repository;

    private Panel $panel;

    private User_Dismissed_Store $dismissed_store;

    public function __construct( string $plugin_file )
    {
        $this->plugin_file = $plugin_file;
        $this->plugin_dir  = trailingslashit( plugin_dir_path( $plugin_file ) );
        $this->plugin_url  = trailingslashit( plugin_dir_url( $plugin_file ) );
        $this->version     = $this->resolve_version();
        $this->settings_repository = new Settings_Repository();
        $this->settings    = new Settings( $this->settings_repository );
        $this->panel       = new Panel();
        $this->dismissed_store = new User_Dismissed_Store();
    }

    /**
     * Instantiate and register hooks.
     */
    public static function register( string $plugin_file ): self
    {
        $plugin = new self( $plugin_file );
        $plugin->hooks();

        return $plugin;
    }

    /**
     * Register WordPress hooks.
     */
    private function hooks(): void
    {
        add_action( 'init', [ $this, 'load_textdomain' ] );
        add_action( 'admin_init', [ $this->settings, 'register' ] );
        add_action( 'admin_menu', [ $this->settings, 'register_settings_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_footer', [ $this, 'render_panel_markup' ] );
        add_action( 'admin_bar_menu', [ $this, 'register_admin_bar_entry' ], 100 );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

        if ( is_multisite() ) {
            add_action( 'wp_initialize_site', [ $this, 'handle_new_site_initialization' ], 10, 1 );
            add_action( 'activate_blog', [ $this, 'handle_blog_activation' ], 10, 1 );
        }
    }

    /**
     * Load plugin translations.
     */
    public function load_textdomain(): void
    {
        load_plugin_textdomain( 'fp-admin-notices', false, dirname( plugin_basename( $this->plugin_file ) ) . '/languages' );
    }

    /**
     * Enqueue admin assets when required.
     */
    public function enqueue_assets(): void
    {
        if ( ! $this->current_user_can_view() || ! $this->should_render_on_screen() ) {
            return;
        }

        $style_handle  = 'fp-admin-notices';
        $style_path    = $this->plugin_dir . 'assets/css/admin-notices.css';
        $script_path   = $this->plugin_dir . 'assets/js/admin-notices.js';
        $style_version = $this->get_asset_version( $style_path );
        $script_version = $this->get_asset_version( $script_path );

        wp_enqueue_style(
            $style_handle,
            $this->plugin_url . 'assets/css/admin-notices.css',
            [],
            $style_version
        );

        wp_enqueue_script(
            $style_handle,
            $this->plugin_url . 'assets/js/admin-notices.js',
            [ 'wp-i18n' ],
            $script_version,
            true
        );

        wp_localize_script(
            $style_handle,
            'FPAdminNotices',
            [
                'i18n'      => $this->get_i18n_strings(),
                'rest'      => [
                    'url'   => esc_url_raw( rest_url( 'fp-admin-notices/v1/notices' ) ),
                    'nonce' => wp_create_nonce( 'wp_rest' ),
                ],
                'settings'  => [
                    'includeUpdateNag' => (bool) $this->settings_repository->get( 'include_update_nag', false ),
                    'autoOpenCritical' => (bool) $this->settings_repository->get( 'auto_open_critical', true ),
                    'filtersEnabled'   => true,
                    'allowedScreens'   => (array) $this->settings_repository->get( 'allowed_screens', [] ),
                    'emptyStateHelpUrl' => esc_url_raw( admin_url( 'options-general.php?page=fp-admin-notices' ) ),
                ],
                'dismissed' => $this->dismissed_store->get(),
            ]
        );

        wp_set_script_translations( $style_handle, 'fp-admin-notices', $this->plugin_dir . 'languages' );
    }

    /**
     * Register the admin bar entry used to toggle the panel.
     */
    public function register_admin_bar_entry( WP_Admin_Bar $admin_bar ): void
    {
        if ( ! $this->current_user_can_view() || ! $this->should_render_on_screen() ) {
            return;
        }

        $title = sprintf(
            '<span class="ab-icon dashicons dashicons-megaphone" aria-hidden="true"></span><span class="ab-label">%s</span><span class="fp-admin-notices-count" aria-hidden="true"></span>',
            esc_html__( 'Notifiche', 'fp-admin-notices' )
        );

        $admin_bar->add_node(
            [
                'id'     => 'fp-admin-notices-toggle',
                'parent' => 'top-secondary',
                'title'  => $title,
                'href'   => '#',
                'meta'   => [
                    'title' => esc_attr__( 'Apri il pannello delle notifiche', 'fp-admin-notices' ),
                    'class' => 'fp-admin-notices-toggle',
                ],
            ]
        );
    }

    /**
     * Render the footer panel markup.
     */
    public function render_panel_markup(): void
    {
        if ( ! $this->current_user_can_view() || ! $this->should_render_on_screen() ) {
            return;
        }

        $this->panel->render();
    }

    /**
     * Register REST API routes.
     */
    public function register_rest_routes(): void
    {
        $controller = new Notice_State_Controller( [ $this, 'current_user_can_view' ], $this->dismissed_store );
        $controller->register_routes();
    }

    /**
     * Determine whether the current user can see the panel.
     */
    public function current_user_can_view(): bool
    {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $user  = wp_get_current_user();
        $roles = (array) $this->settings_repository->get( 'allowed_roles', [ 'administrator' ] );

        $can_view = (bool) array_intersect( $roles, (array) $user->roles );

        return (bool) apply_filters( 'fp_admin_notices_user_can_view', $can_view, $user );
    }

    /**
     * Check whether assets and markup should be rendered on the current screen.
     */
    private function should_render_on_screen(): bool
    {
        if ( ! is_admin() ) {
            return (bool) apply_filters( 'fp_admin_notices_should_render', false, null );
        }

        $allowed_screens = (array) $this->settings_repository->get( 'allowed_screens', [] );
        $allowed_screens = array_values(
            array_filter(
                array_map(
                    static fn( $screen ): string => Screen_ID_Normalizer::normalize( (string) $screen ),
                    $allowed_screens
                )
            )
        );
        $allowed_screens = apply_filters( 'fp_admin_notices_allowed_screens', $allowed_screens );

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if ( null === $screen ) {
            $default = empty( $allowed_screens );

            return (bool) apply_filters( 'fp_admin_notices_should_render', $default, $screen );
        }

        if ( empty( $allowed_screens ) ) {
            return (bool) apply_filters( 'fp_admin_notices_should_render', true, $screen );
        }

        $screen_id   = Screen_ID_Normalizer::normalize( (string) $screen->id );
        $screen_base = Screen_ID_Normalizer::normalize( (string) $screen->base );

        $matches = in_array( $screen_id, $allowed_screens, true ) || in_array( $screen_base, $allowed_screens, true );

        return (bool) apply_filters( 'fp_admin_notices_should_render', $matches, $screen );
    }

    /**
     * Provide localized strings for the UI.
     *
     * @return array<string, string>
     */
    private function get_i18n_strings(): array
    {
        return [
            'title'                 => __( 'Notifiche', 'fp-admin-notices' ),
            'noNotices'             => __( 'Nessuna notifica da mostrare.', 'fp-admin-notices' ),
            'noMatches'             => __( 'Nessuna notifica corrisponde ai filtri applicati.', 'fp-admin-notices' ),
            'noUnreadMatches'       => __( 'Tutte le notifiche corrispondenti sono archiviate. Mostrale per gestirle.', 'fp-admin-notices' ),
            'openPanel'             => __( 'Apri il pannello notifiche', 'fp-admin-notices' ),
            'closePanel'            => __( 'Chiudi il pannello notifiche', 'fp-admin-notices' ),
            'markRead'              => __( 'Segna come letta', 'fp-admin-notices' ),
            'markUnread'            => __( 'Segna come non letta', 'fp-admin-notices' ),
            'markAllRead'           => __( 'Segna tutte come lette', 'fp-admin-notices' ),
            'markAllUnread'         => __( 'Segna tutte come non lette', 'fp-admin-notices' ),
            'showDismissed'         => __( 'Mostra archiviate', 'fp-admin-notices' ),
            'hideDismissed'         => __( 'Nascondi archiviate', 'fp-admin-notices' ),
            'showNotice'            => __( 'Mostra nella pagina', 'fp-admin-notices' ),
            'filtersLabel'          => __( 'Filtra le notifiche', 'fp-admin-notices' ),
            'filterAll'             => __( 'Tutte', 'fp-admin-notices' ),
            'filterError'           => __( 'Errori', 'fp-admin-notices' ),
            'filterWarning'         => __( 'Avvisi', 'fp-admin-notices' ),
            'filterSuccess'         => __( 'Successi', 'fp-admin-notices' ),
            'filterInfo'            => __( 'Informazioni', 'fp-admin-notices' ),
            'searchPlaceholder'     => __( 'Cerca notifiche…', 'fp-admin-notices' ),
            'emptyTitle'            => __( 'Tutto tranquillo qui!', 'fp-admin-notices' ),
            'emptyAction'           => __( 'Personalizza le preferenze →', 'fp-admin-notices' ),
            'newNoticeAnnouncement' => __( 'Nuove notifiche disponibili', 'fp-admin-notices' ),
            'badgeActive'           => __( 'Attiva', 'fp-admin-notices' ),
            'badgeArchived'         => __( 'Archiviata', 'fp-admin-notices' ),
            'announcementVisualTitle' => __( 'Aggiornamento notifiche', 'fp-admin-notices' ),
            'toggleShortcut'        => __( 'Scorciatoia: premi Alt+Shift+N per aprire o chiudere il pannello notifiche.', 'fp-admin-notices' ),
        ];
    }

    /**
     * Resolve plugin version for cache-busting and metadata.
     */
    private function resolve_version(): string
    {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            return (string) filemtime( $this->plugin_file );
        }

        if ( function_exists( 'get_file_data' ) ) {
            $data = get_file_data( $this->plugin_file, [ 'Version' => 'Version' ], 'plugin' );
            if ( ! empty( $data['Version'] ) ) {
                return (string) $data['Version'];
            }
        }

        return self::VERSION_FALLBACK;
    }

    /**
     * Retrieve an asset version based on file modification time.
     */
    private function get_asset_version( string $path ): string
    {
        if ( file_exists( $path ) ) {
            return (string) filemtime( $path );
        }

        return $this->version;
    }

    /**
     * Handle plugin activation to ensure defaults exist.
     */
    public static function activate( bool $network_wide ): void
    {
        if ( is_multisite() && $network_wide ) {
            $sites = get_sites( [ 'fields' => 'ids' ] );
            foreach ( $sites as $site_id ) {
                switch_to_blog( (int) $site_id );
                self::initialize_options();
                restore_current_blog();
            }

            return;
        }

        self::initialize_options();
    }

    /**
     * Ensure options exist with defaults on activation.
     */
    private static function initialize_options(): void
    {
        $repository = new Settings_Repository();
        $repository->ensure_defaults_exist();
    }

    /**
     * Initialize defaults for a newly created site on multisite.
     */
    public function handle_new_site_initialization( WP_Site $site ): void
    {
        $this->initialize_site_settings( (int) $site->blog_id );
    }

    /**
     * Initialize defaults when a blog is activated (legacy multisite hook).
     */
    public function handle_blog_activation( int $blog_id ): void
    {
        $this->initialize_site_settings( $blog_id );
    }

    /**
     * Ensure settings defaults are present on a specific blog.
     */
    private function initialize_site_settings( int $blog_id ): void
    {
        if ( $blog_id <= 0 ) {
            return;
        }

        $current_blog_id = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;

        if ( $current_blog_id === $blog_id ) {
            self::initialize_options();
            return;
        }

        switch_to_blog( $blog_id );

        try {
            self::initialize_options();
        } finally {
            restore_current_blog();
        }
    }
}
