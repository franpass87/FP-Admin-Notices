<?php
/**
 * Plugin Name: FP Admin Notices
 * Description: Centralizza le admin notices di WordPress in un pannello accessibile dall'admin bar.
 * Version: 1.0.0
 * Author: FP
 * Text Domain: fp-admin-notices
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'FP_Admin_Notices' ) ) {
    /**
     * Gestisce il pannello delle admin notices.
     */
    class FP_Admin_Notices {
        /**
         * Avvia gli hook principali del plugin.
         */
        public static function init() {
            add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
            add_action( 'admin_footer', array( __CLASS__, 'render_panel_markup' ) );
            add_action( 'admin_bar_menu', array( __CLASS__, 'register_admin_bar_entry' ), 100 );
        }

        /**
         * Restituisce la versione del plugin.
         *
         * @return string
         */
        protected static function get_version() {
            static $version = null;

            if ( null === $version ) {
                $plugin_file = __FILE__;
                $version     = defined( 'WP_DEBUG' ) && WP_DEBUG
                    ? filemtime( $plugin_file )
                    : '1.0.0';
            }

            return (string) $version;
        }

        /**
         * Carica gli assets nel back-end.
         */
        public static function enqueue_assets() {
            $handle  = 'fp-admin-notices';
            $version = self::get_version();

            wp_enqueue_style(
                $handle,
                plugin_dir_url( __FILE__ ) . 'assets/css/admin-notices.css',
                array(),
                $version
            );

            wp_enqueue_script(
                $handle,
                plugin_dir_url( __FILE__ ) . 'assets/js/admin-notices.js',
                array(),
                $version,
                true
            );

            wp_localize_script(
                $handle,
                'FPAdminNotices',
                array(
                    'i18n' => array(
                        'title'      => __( 'Notifiche', 'fp-admin-notices' ),
                        'noNotices'  => __( 'Nessuna notifica da mostrare.', 'fp-admin-notices' ),
                        'openPanel'  => __( 'Apri il pannello notifiche', 'fp-admin-notices' ),
                        'closePanel' => __( 'Chiudi il pannello notifiche', 'fp-admin-notices' ),
                    ),
                )
            );
        }

        /**
         * Registra la voce nell'admin bar.
         *
         * @param WP_Admin_Bar $admin_bar Istanza della admin bar.
         */
        public static function register_admin_bar_entry( $admin_bar ) {
            if ( ! current_user_can( 'read' ) ) {
                return;
            }

            $title = sprintf(
                '<span class="ab-icon dashicons dashicons-megaphone" aria-hidden="true"></span><span class="ab-label">%s</span><span class="fp-admin-notices-count" aria-hidden="true"></span>',
                esc_html__( 'Notifiche', 'fp-admin-notices' )
            );

            $admin_bar->add_node(
                array(
                    'id'     => 'fp-admin-notices-toggle',
                    'parent' => 'top-secondary',
                    'title'  => $title,
                    'href'   => '#',
                    'meta'   => array(
                        'title' => esc_attr__( 'Apri il pannello delle notifiche', 'fp-admin-notices' ),
                        'class' => 'fp-admin-notices-toggle',
                    ),
                )
            );
        }

        /**
         * Stampa il markup del pannello nel footer amministrativo.
         */
        public static function render_panel_markup() {
            if ( ! current_user_can( 'read' ) ) {
                return;
            }
            ?>
            <div id="fp-admin-notices-panel" class="fp-admin-notices-panel" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="fp-admin-notices-title">
                <div class="fp-admin-notices-panel__overlay" tabindex="-1"></div>
                <div class="fp-admin-notices-panel__content" role="document">
                    <header class="fp-admin-notices-panel__header">
                        <h2 id="fp-admin-notices-title"><?php esc_html_e( 'Notifiche amministratore', 'fp-admin-notices' ); ?></h2>
                        <button type="button" class="fp-admin-notices-panel__close" aria-label="<?php echo esc_attr_x( 'Chiudi', 'close panel button', 'fp-admin-notices' ); ?>">&times;</button>
                    </header>
                    <div class="fp-admin-notices-panel__body" tabindex="0">
                        <div class="fp-admin-notices-panel__list" role="region" aria-live="polite" aria-relevant="all"></div>
                    </div>
                </div>
            </div>
            <?php
        }
    }
}

FP_Admin_Notices::init();
