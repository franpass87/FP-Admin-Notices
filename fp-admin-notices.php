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
        const OPTION_NAME   = 'fp_admin_notices_settings';
        const USER_META_KEY = 'fp_admin_notices_dismissed';

        /**
         * Avvia gli hook principali del plugin.
         */
        public static function init() {
            add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
            add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
            add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );
            add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
            add_action( 'admin_footer', array( __CLASS__, 'render_panel_markup' ) );
            add_action( 'admin_bar_menu', array( __CLASS__, 'register_admin_bar_entry' ), 100 );
            add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
        }

        /**
         * Carica le traduzioni del plugin.
         */
        public static function load_textdomain() {
            load_plugin_textdomain( 'fp-admin-notices', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
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
            if ( ! self::current_user_can_view() || ! self::should_render_on_screen() ) {
                return;
            }

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
                array( 'wp-i18n' ),
                $version,
                true
            );

            wp_localize_script(
                $handle,
                'FPAdminNotices',
                array(
                    'i18n' => array(
                        'title'      => __( 'Notifiche', 'fp-admin-notices' ),
                        'noNotices'            => __( 'Nessuna notifica da mostrare.', 'fp-admin-notices' ),
                        'noMatches'            => __( 'Nessuna notifica corrisponde ai filtri applicati.', 'fp-admin-notices' ),
                        'noUnreadMatches'      => __( 'Tutte le notifiche corrispondenti sono archiviate. Mostrale per gestirle.', 'fp-admin-notices' ),
                        'openPanel'            => __( 'Apri il pannello notifiche', 'fp-admin-notices' ),
                        'closePanel'           => __( 'Chiudi il pannello notifiche', 'fp-admin-notices' ),
                        'markRead'             => __( 'Segna come letta', 'fp-admin-notices' ),
                        'markUnread'           => __( 'Segna come non letta', 'fp-admin-notices' ),
                        'markAllRead'          => __( 'Segna tutte come lette', 'fp-admin-notices' ),
                        'markAllUnread'        => __( 'Segna tutte come non lette', 'fp-admin-notices' ),
                        'showDismissed'        => __( 'Mostra archiviate', 'fp-admin-notices' ),
                        'hideDismissed'        => __( 'Nascondi archiviate', 'fp-admin-notices' ),
                        'showNotice'           => __( 'Mostra nella pagina', 'fp-admin-notices' ),
                        'filtersLabel'         => __( 'Filtra le notifiche', 'fp-admin-notices' ),
                        'filterAll'            => __( 'Tutte', 'fp-admin-notices' ),
                        'filterError'          => __( 'Errori', 'fp-admin-notices' ),
                        'filterWarning'        => __( 'Avvisi', 'fp-admin-notices' ),
                        'filterSuccess'        => __( 'Successi', 'fp-admin-notices' ),
                        'filterInfo'           => __( 'Informazioni', 'fp-admin-notices' ),
                        'searchPlaceholder'    => __( 'Cerca notifiche…', 'fp-admin-notices' ),
                        'emptyTitle'           => __( 'Tutto tranquillo qui!', 'fp-admin-notices' ),
                        'emptyAction'          => __( 'Personalizza le preferenze →', 'fp-admin-notices' ),
                        'newNoticeAnnouncement'=> __( 'Nuove notifiche disponibili', 'fp-admin-notices' ),
                        'badgeActive'          => __( 'Attiva', 'fp-admin-notices' ),
                        'badgeArchived'        => __( 'Archiviata', 'fp-admin-notices' ),
                        'announcementVisualTitle' => __( 'Aggiornamento notifiche', 'fp-admin-notices' ),
                        'toggleShortcut'       => __( 'Scorciatoia: premi Alt+Shift+N per aprire o chiudere il pannello notifiche.', 'fp-admin-notices' ),
                    ),
                    'rest'      => array(
                        'url'   => esc_url_raw( rest_url( 'fp-admin-notices/v1/notices' ) ),
                        'nonce' => wp_create_nonce( 'wp_rest' ),
                    ),
                    'settings'  => array(
                        'includeUpdateNag' => (bool) self::get_setting( 'include_update_nag', false ),
                        'autoOpenCritical' => (bool) self::get_setting( 'auto_open_critical', true ),
                        'filtersEnabled'   => true,
                        'allowedScreens'   => (array) self::get_setting( 'allowed_screens', array() ),
                        'emptyStateHelpUrl' => esc_url( admin_url( 'options-general.php?page=fp-admin-notices' ) ),
                    ),
                    'dismissed' => self::get_dismissed_notice_ids(),
                )
            );

            wp_set_script_translations( $handle, 'fp-admin-notices', plugin_dir_path( __FILE__ ) . 'languages' );
        }

        /**
         * Registra la voce nell'admin bar.
         *
         * @param WP_Admin_Bar $admin_bar Istanza della admin bar.
         */
        public static function register_admin_bar_entry( $admin_bar ) {
            if ( ! self::current_user_can_view() || ! self::should_render_on_screen() ) {
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
            if ( ! self::current_user_can_view() || ! self::should_render_on_screen() ) {
                return;
            }
            ?>
            <div id="fp-admin-notices-panel" class="fp-admin-notices-panel" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="fp-admin-notices-title">
                <div class="fp-admin-notices-panel__overlay" tabindex="-1"></div>
                <div class="fp-admin-notices-panel__content" role="document">
                    <header class="fp-admin-notices-panel__header">
                        <div class="fp-admin-notices-panel__title">
                            <span class="fp-admin-notices-panel__title-icon dashicons dashicons-megaphone" aria-hidden="true"></span>
                            <h2 id="fp-admin-notices-title"><?php esc_html_e( 'Notifiche amministratore', 'fp-admin-notices' ); ?></h2>
                        </div>
                        <button type="button" class="fp-admin-notices-panel__close" aria-label="<?php echo esc_attr_x( 'Chiudi', 'close panel button', 'fp-admin-notices' ); ?>">
                            <span class="screen-reader-text"><?php echo esc_html_x( 'Chiudi il pannello notifiche', 'close panel button description', 'fp-admin-notices' ); ?></span>
                            <span aria-hidden="true" class="fp-admin-notices-panel__close-icon"></span>
                        </button>
                    </header>
                    <div class="fp-admin-notices-panel__announcement-visual" aria-hidden="true"></div>
                    <div class="fp-admin-notices-panel__controls" role="search" aria-label="<?php esc_attr_e( 'Strumenti di ricerca e filtro per le notifiche', 'fp-admin-notices' ); ?>">
                        <div class="fp-admin-notices-panel__filters" role="group" aria-label="<?php esc_attr_e( 'Filtri per severità', 'fp-admin-notices' ); ?>">
                            <button type="button" class="fp-admin-notices-filter is-active" data-filter="all"><?php esc_html_e( 'Tutte', 'fp-admin-notices' ); ?></button>
                            <button type="button" class="fp-admin-notices-filter" data-filter="error"><?php esc_html_e( 'Errori', 'fp-admin-notices' ); ?></button>
                            <button type="button" class="fp-admin-notices-filter" data-filter="warning"><?php esc_html_e( 'Avvisi', 'fp-admin-notices' ); ?></button>
                            <button type="button" class="fp-admin-notices-filter" data-filter="success"><?php esc_html_e( 'Successi', 'fp-admin-notices' ); ?></button>
                            <button type="button" class="fp-admin-notices-filter" data-filter="info"><?php esc_html_e( 'Informazioni', 'fp-admin-notices' ); ?></button>
                        </div>
                        <label class="screen-reader-text" for="fp-admin-notices-search"><?php esc_html_e( 'Cerca notifiche', 'fp-admin-notices' ); ?></label>
                        <div class="fp-admin-notices-search__wrapper">
                            <span class="fp-admin-notices-search__icon dashicons dashicons-search" aria-hidden="true"></span>
                            <input type="search" id="fp-admin-notices-search" class="fp-admin-notices-search" placeholder="<?php esc_attr_e( 'Cerca notifiche…', 'fp-admin-notices' ); ?>" autocomplete="off" />
                        </div>
                    </div>
                    <div class="fp-admin-notices-panel__bulk-actions" role="group" aria-label="<?php esc_attr_e( 'Azioni rapide sulle notifiche', 'fp-admin-notices' ); ?>">
                        <button type="button" class="fp-admin-notices-panel__bulk-action fp-admin-notices-panel__bulk-action--primary fp-admin-notices-panel__bulk-action--read">
                            <span class="fp-admin-notices-panel__bulk-action-icon dashicons dashicons-yes-alt" aria-hidden="true"></span>
                            <span class="fp-admin-notices-panel__bulk-action-label"><?php esc_html_e( 'Segna tutte come lette', 'fp-admin-notices' ); ?></span>
                        </button>
                        <button type="button" class="fp-admin-notices-panel__bulk-action fp-admin-notices-panel__bulk-action--ghost fp-admin-notices-panel__bulk-action--unread">
                            <span class="fp-admin-notices-panel__bulk-action-icon dashicons dashicons-undo" aria-hidden="true"></span>
                            <span class="fp-admin-notices-panel__bulk-action-label"><?php esc_html_e( 'Segna tutte come non lette', 'fp-admin-notices' ); ?></span>
                        </button>
                        <button type="button" class="fp-admin-notices-panel__bulk-action fp-admin-notices-panel__bulk-action--ghost fp-admin-notices-panel__bulk-action--toggle-archived" aria-pressed="false">
                            <span class="fp-admin-notices-panel__bulk-action-icon dashicons dashicons-archive" aria-hidden="true"></span>
                            <span class="fp-admin-notices-panel__bulk-action-label"><?php esc_html_e( 'Mostra archiviate', 'fp-admin-notices' ); ?></span>
                        </button>
                    </div>
                    <div class="fp-admin-notices-panel__body" tabindex="0">
                        <?php do_action( 'fp_admin_notices_panel_before_list' ); ?>
                        <div class="fp-admin-notices-panel__list" role="region" aria-live="polite" aria-relevant="all"></div>
                        <?php do_action( 'fp_admin_notices_panel_after_list' ); ?>
                    </div>
                    <p class="fp-admin-notices-panel__announcement screen-reader-text" aria-live="assertive" aria-atomic="true"></p>
                </div>
            </div>
            <?php
        }

        /**
         * Registra le impostazioni del plugin.
         */
        public static function register_settings() {
            register_setting(
                'fp_admin_notices_settings',
                self::OPTION_NAME,
                array(
                    'type'              => 'array',
                    'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
                    'default'           => array(
                        'allowed_roles'      => array( 'administrator' ),
                        'include_update_nag' => false,
                        'auto_open_critical' => true,
                    ),
                )
            );

            add_settings_section(
                'fp_admin_notices_settings_section',
                __( 'Preferenze pannello notifiche', 'fp-admin-notices' ),
                '__return_false',
                'fp_admin_notices_settings'
            );

            add_settings_field(
                'fp_admin_notices_roles',
                __( 'Ruoli abilitati', 'fp-admin-notices' ),
                array( __CLASS__, 'render_roles_field' ),
                'fp_admin_notices_settings',
                'fp_admin_notices_settings_section'
            );

            add_settings_field(
                'fp_admin_notices_include_update_nag',
                __( 'Includi avvisi di aggiornamento', 'fp-admin-notices' ),
                array( __CLASS__, 'render_include_update_nag_field' ),
                'fp_admin_notices_settings',
                'fp_admin_notices_settings_section'
            );

            add_settings_field(
                'fp_admin_notices_auto_open_critical',
                __( 'Apri automaticamente per errori critici', 'fp-admin-notices' ),
                array( __CLASS__, 'render_auto_open_field' ),
                'fp_admin_notices_settings',
                'fp_admin_notices_settings_section'
            );

            add_settings_field(
                'fp_admin_notices_allowed_screens',
                __( 'Limita il caricamento alle schermate', 'fp-admin-notices' ),
                array( __CLASS__, 'render_allowed_screens_field' ),
                'fp_admin_notices_settings',
                'fp_admin_notices_settings_section'
            );
        }

        /**
         * Aggiunge la pagina delle impostazioni.
         */
        public static function register_settings_page() {
            add_options_page(
                __( 'Pannello notifiche', 'fp-admin-notices' ),
                __( 'Pannello notifiche', 'fp-admin-notices' ),
                'manage_options',
                'fp-admin-notices',
                array( __CLASS__, 'render_settings_page' )
            );
        }

        /**
         * Stampa la pagina delle impostazioni.
         */
        public static function render_settings_page() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Impostazioni pannello notifiche', 'fp-admin-notices' ); ?></h1>
                <form action="options.php" method="post">
                    <?php
                    settings_fields( 'fp_admin_notices_settings' );
                    do_settings_sections( 'fp_admin_notices_settings' );
                    submit_button();
                    ?>
                </form>
            </div>
            <?php
        }

        /**
         * Campo impostazioni per i ruoli.
         */
        public static function render_roles_field() {
            $settings = self::get_settings();
            $allowed  = isset( $settings['allowed_roles'] ) ? (array) $settings['allowed_roles'] : array( 'administrator' );
            $editable = get_editable_roles();
            ?>
            <fieldset>
                <legend class="screen-reader-text"><?php esc_html_e( 'Ruoli abilitati', 'fp-admin-notices' ); ?></legend>
                <?php foreach ( $editable as $role_key => $role ) :
                    $checked = in_array( $role_key, $allowed, true );
                    ?>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[allowed_roles][]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( $checked ); ?> />
                        <?php echo esc_html( translate_user_role( $role['name'] ) ); ?>
                    </label><br />
                <?php endforeach; ?>
                <p class="description"><?php esc_html_e( 'Gli utenti con uno dei ruoli selezionati potranno utilizzare il pannello notifiche.', 'fp-admin-notices' ); ?></p>
            </fieldset>
            <?php
        }

        /**
         * Campo impostazioni per includere gli update nag.
         */
        public static function render_include_update_nag_field() {
            $settings = self::get_settings();
            $value    = ! empty( $settings['include_update_nag'] );
            ?>
            <label>
                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[include_update_nag]" value="1" <?php checked( $value ); ?> />
                <?php esc_html_e( 'Mostra anche gli avvisi di aggiornamento (update nag).', 'fp-admin-notices' ); ?>
            </label>
            <?php
        }

        /**
         * Campo impostazioni per l'apertura automatica del pannello.
         */
        public static function render_auto_open_field() {
            $settings = self::get_settings();
            $value    = ! empty( $settings['auto_open_critical'] );
            ?>
            <label>
                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[auto_open_critical]" value="1" <?php checked( $value ); ?> />
                <?php esc_html_e( 'Apri automaticamente il pannello quando arrivano errori critici.', 'fp-admin-notices' ); ?>
            </label>
            <?php
        }

        /**
         * Campo impostazioni per limitare il caricamento alle schermate specificate.
         */
        public static function render_allowed_screens_field() {
            $settings = self::get_settings();
            $screens  = isset( $settings['allowed_screens'] ) && is_array( $settings['allowed_screens'] )
                ? implode( "\n", array_map( 'sanitize_text_field', $settings['allowed_screens'] ) )
                : '';
            ?>
            <textarea
                name="<?php echo esc_attr( self::OPTION_NAME ); ?>[allowed_screens]"
                rows="4"
                cols="40"
                class="large-text code"
                placeholder="dashboard\nplugins\nsettings_page_my-plugin"
            ><?php echo esc_textarea( $screens ); ?></textarea>
            <p class="description">
                <?php esc_html_e( 'Inserisci gli ID delle schermate (uno per riga) in cui caricare il pannello. Lascia vuoto per abilitarlo ovunque.', 'fp-admin-notices' ); ?>
            </p>
            <?php
        }

        /**
         * Sanitizza i valori delle impostazioni.
         *
         * @param array $settings Valori grezzi.
         * @return array
         */
        public static function sanitize_settings( $settings ) {
            $settings = is_array( $settings ) ? $settings : array();

            $allowed_roles = array();
            if ( ! empty( $settings['allowed_roles'] ) && is_array( $settings['allowed_roles'] ) ) {
                $editable = get_editable_roles();
                foreach ( $settings['allowed_roles'] as $role ) {
                    if ( isset( $editable[ $role ] ) ) {
                        $allowed_roles[] = sanitize_key( $role );
                    }
                }
            }

            if ( empty( $allowed_roles ) ) {
                $allowed_roles[] = 'administrator';
            }

            $allowed_screens = array();
            if ( ! empty( $settings['allowed_screens'] ) ) {
                $raw_screens = is_array( $settings['allowed_screens'] )
                    ? $settings['allowed_screens']
                    : preg_split( '/[\r\n]+/', (string) $settings['allowed_screens'] );

                foreach ( (array) $raw_screens as $screen_id ) {
                    $screen_id = sanitize_key( trim( (string) $screen_id ) );
                    if ( '' !== $screen_id ) {
                        $allowed_screens[] = $screen_id;
                    }
                }
            }

            $allowed_screens = array_values( array_unique( $allowed_screens ) );

            return array(
                'allowed_roles'      => $allowed_roles,
                'include_update_nag' => ! empty( $settings['include_update_nag'] ),
                'auto_open_critical' => ! empty( $settings['auto_open_critical'] ),
                'allowed_screens'    => $allowed_screens,
            );
        }

        /**
         * Restituisce le impostazioni memorizzate.
         *
         * @return array
         */
        protected static function get_settings() {
            $defaults = array(
                'allowed_roles'      => array( 'administrator' ),
                'include_update_nag' => false,
                'auto_open_critical' => true,
                'allowed_screens'    => array(),
            );

            $settings = get_option( self::OPTION_NAME, array() );

            if ( ! is_array( $settings ) ) {
                $settings = array();
            }

            return wp_parse_args( $settings, $defaults );
        }

        /**
         * Restituisce un valore specifico delle impostazioni.
         *
         * @param string $key     Nome dell'opzione.
         * @param mixed  $default Valore di fallback.
         * @return mixed
         */
        protected static function get_setting( $key, $default = null ) {
            $settings = self::get_settings();

            return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
        }

        /**
         * Verifica se l'utente corrente può usare il pannello.
         *
         * @return bool
         */
        protected static function current_user_can_view() {
            if ( ! is_user_logged_in() ) {
                return false;
            }

            $user  = wp_get_current_user();
            $roles = (array) self::get_setting( 'allowed_roles', array( 'administrator' ) );

            return (bool) array_intersect( $roles, (array) $user->roles );
        }

        /**
         * Verifica se il pannello deve essere caricato nella schermata corrente.
         *
         * @return bool
         */
        protected static function should_render_on_screen() {
            $allowed_screens = (array) self::get_setting( 'allowed_screens', array() );
            $allowed_screens = apply_filters( 'fp_admin_notices_allowed_screens', $allowed_screens );

            $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : false;

            if ( empty( $allowed_screens ) ) {
                /**
                 * Permette di forzare o impedire il rendering del pannello a livello globale.
                 *
                 * @param bool             $render Indica se il pannello deve essere mostrato.
                 * @param WP_Screen|false  $screen Schermata corrente se disponibile.
                 */
                return (bool) apply_filters( 'fp_admin_notices_should_render', true, $screen );
            }

            if ( ! $screen ) {
                return (bool) apply_filters( 'fp_admin_notices_should_render', false, $screen );
            }

            $screen_id   = sanitize_key( $screen->id );
            $screen_base = sanitize_key( $screen->base );

            $matches = in_array( $screen_id, $allowed_screens, true ) || in_array( $screen_base, $allowed_screens, true );

            return (bool) apply_filters( 'fp_admin_notices_should_render', $matches, $screen );
        }

        /**
         * Restituisce gli ID delle notifiche dismesse dall'utente.
         *
         * @return array
         */
        protected static function get_dismissed_notice_ids() {
            $user_id = get_current_user_id();

            if ( ! $user_id ) {
                return array();
            }

            $dismissed = get_user_meta( $user_id, self::USER_META_KEY, true );

            return is_array( $dismissed ) ? array_values( array_unique( array_map( 'sanitize_text_field', $dismissed ) ) ) : array();
        }

        /**
         * Aggiorna lo stato di una notifica per l'utente.
         *
         * @param string $notice_id ID della notifica.
         * @param bool   $dismissed Stato da applicare.
         */
        protected static function update_notice_state( $notice_id, $dismissed ) {
            $user_id = get_current_user_id();

            if ( ! $user_id || ! $notice_id ) {
                return;
            }

            $stored    = self::get_dismissed_notice_ids();
            $notice_id = sanitize_text_field( $notice_id );

            if ( $dismissed ) {
                if ( ! in_array( $notice_id, $stored, true ) ) {
                    $stored[] = $notice_id;
                }
            } else {
                $stored = array_values( array_diff( $stored, array( $notice_id ) ) );
            }

            update_user_meta( $user_id, self::USER_META_KEY, $stored );
        }

        /**
         * Registra le rotte REST utilizzate dal pannello.
         */
        public static function register_rest_routes() {
            register_rest_route(
                'fp-admin-notices/v1',
                '/notices',
                array(
                    array(
                        'methods'             => WP_REST_Server::CREATABLE,
                        'callback'            => array( __CLASS__, 'rest_update_notice_state' ),
                        'permission_callback' => array( __CLASS__, 'rest_permission_callback' ),
                        'args'                => array(
                            'notice_id' => array(
                                'type'     => 'string',
                                'required' => false,
                            ),
                            'notice_ids' => array(
                                'type'     => 'array',
                                'items'    => array(
                                    'type' => 'string',
                                ),
                                'required' => false,
                            ),
                            'dismissed' => array(
                                'type'    => 'boolean',
                                'default' => true,
                            ),
                        ),
                    ),
                )
            );
        }

        /**
         * Controlla i permessi per la rotta REST.
         *
         * @return bool
         */
        public static function rest_permission_callback() {
            return self::current_user_can_view();
        }

        /**
         * Aggiorna lo stato via REST.
         *
         * @param WP_REST_Request $request Richiesta REST.
         * @return WP_REST_Response
         */
        public static function rest_update_notice_state( WP_REST_Request $request ) {
            $dismissed = filter_var( $request->get_param( 'dismissed' ), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

            if ( null === $dismissed ) {
                $dismissed = true;
            }

            $notice_ids = array();

            $notice_ids_param = $request->get_param( 'notice_ids' );
            if ( is_array( $notice_ids_param ) ) {
                foreach ( $notice_ids_param as $id ) {
                    $id = sanitize_text_field( $id );
                    if ( '' !== $id ) {
                        $notice_ids[] = $id;
                    }
                }
            }

            $single_notice_id = $request->get_param( 'notice_id' );
            if ( is_string( $single_notice_id ) && '' !== $single_notice_id ) {
                $notice_ids[] = sanitize_text_field( $single_notice_id );
            }

            $notice_ids = array_values( array_unique( array_filter( $notice_ids ) ) );

            if ( empty( $notice_ids ) ) {
                return new WP_Error(
                    'fp_admin_notices_missing_notice',
                    __( 'Nessuna notifica specificata.', 'fp-admin-notices' ),
                    array( 'status' => 400 )
                );
            }

            foreach ( $notice_ids as $notice_id ) {
                self::update_notice_state( $notice_id, (bool) $dismissed );
            }

            $response = array(
                'notice_ids' => $notice_ids,
                'dismissed'  => (bool) $dismissed,
            );

            if ( 1 === count( $notice_ids ) ) {
                $response['notice_id'] = $notice_ids[0];
            }

            return rest_ensure_response( $response );
        }
    }
}

FP_Admin_Notices::init();
