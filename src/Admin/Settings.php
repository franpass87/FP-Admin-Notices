<?php
/**
 * Settings management for FP Admin Notices.
 */
declare(strict_types=1);

namespace FP\AdminNotices\Admin;

use FP\AdminNotices\Support\Screen_ID_Normalizer;
use FP\AdminNotices\Support\Settings_Repository;

use function __;
use function add_options_page;
use function add_settings_field;
use function add_settings_section;
use function array_unique;
use function array_values;
use function checked;
use function current_user_can;
use function do_settings_sections;
use function esc_attr;
use function esc_html;
use function esc_html_e;
use function esc_textarea;
use function get_editable_roles;
use function in_array;
use function is_array;
use function preg_split;
use function register_setting;
use function sanitize_key;
use function sanitize_text_field;
use function settings_fields;
use function submit_button;
use function translate_user_role;

/**
 * Handles plugin settings registration and rendering.
 */
class Settings
{
    private Settings_Repository $repository;

    public function __construct( Settings_Repository $repository )
    {
        $this->repository = $repository;
    }

    /**
     * Register plugin settings using the Settings API.
     */
    public function register(): void
    {
        register_setting(
            'fp_admin_notices_settings',
            $this->repository->option_name(),
            [
                'type'              => 'array',
                'sanitize_callback' => [ $this, 'sanitize_settings' ],
                'default'           => $this->repository->defaults(),
            ]
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
            [ $this, 'render_roles_field' ],
            'fp_admin_notices_settings',
            'fp_admin_notices_settings_section'
        );

        add_settings_field(
            'fp_admin_notices_include_update_nag',
            __( 'Includi avvisi di aggiornamento', 'fp-admin-notices' ),
            [ $this, 'render_include_update_nag_field' ],
            'fp_admin_notices_settings',
            'fp_admin_notices_settings_section'
        );

        add_settings_field(
            'fp_admin_notices_auto_open_critical',
            __( 'Apri automaticamente per errori critici', 'fp-admin-notices' ),
            [ $this, 'render_auto_open_field' ],
            'fp_admin_notices_settings',
            'fp_admin_notices_settings_section'
        );

        add_settings_field(
            'fp_admin_notices_allowed_screens',
            __( 'Limita il caricamento alle schermate', 'fp-admin-notices' ),
            [ $this, 'render_allowed_screens_field' ],
            'fp_admin_notices_settings',
            'fp_admin_notices_settings_section'
        );
    }

    /**
     * Register the plugin settings page.
     */
    public function register_settings_page(): void
    {
        add_options_page(
            __( 'Pannello notifiche', 'fp-admin-notices' ),
            __( 'Pannello notifiche', 'fp-admin-notices' ),
            'manage_options',
            'fp-admin-notices',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Render the plugin settings page.
     */
    public function render_settings_page(): void
    {
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
     * Render the allowed roles field.
     */
    public function render_roles_field(): void
    {
        $settings    = $this->repository->all();
        $allowed     = isset( $settings['allowed_roles'] ) ? (array) $settings['allowed_roles'] : ['administrator'];
        $editable = get_editable_roles();
        $option_name = $this->repository->option_name();
        ?>
        <fieldset>
            <legend class="screen-reader-text"><?php esc_html_e( 'Ruoli abilitati', 'fp-admin-notices' ); ?></legend>
            <?php foreach ( $editable as $role_key => $role ) :
                $checked = in_array( $role_key, $allowed, true );
                ?>
                <label>
                    <input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[allowed_roles][]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( $checked ); ?> />
                    <?php echo esc_html( translate_user_role( $role['name'] ) ); ?>
                </label><br />
            <?php endforeach; ?>
            <p class="description"><?php esc_html_e( 'Gli utenti con uno dei ruoli selezionati potranno utilizzare il pannello notifiche.', 'fp-admin-notices' ); ?></p>
        </fieldset>
        <?php
    }

    /**
     * Render the include update nag field.
     */
    public function render_include_update_nag_field(): void
    {
        $settings    = $this->repository->all();
        $value       = ! empty( $settings['include_update_nag'] );
        $option_name = $this->repository->option_name();
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[include_update_nag]" value="1" <?php checked( $value ); ?> />
            <?php esc_html_e( 'Mostra anche gli avvisi di aggiornamento (update nag).', 'fp-admin-notices' ); ?>
        </label>
        <?php
    }

    /**
     * Render the auto open field.
     */
    public function render_auto_open_field(): void
    {
        $settings    = $this->repository->all();
        $value       = ! empty( $settings['auto_open_critical'] );
        $option_name = $this->repository->option_name();
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[auto_open_critical]" value="1" <?php checked( $value ); ?> />
            <?php esc_html_e( 'Apri automaticamente il pannello quando arrivano errori critici.', 'fp-admin-notices' ); ?>
        </label>
        <?php
    }

    /**
     * Render the allowed screens field.
     */
    public function render_allowed_screens_field(): void
    {
        $settings    = $this->repository->all();
        $option_name = $this->repository->option_name();
        $screens     = isset( $settings['allowed_screens'] ) && is_array( $settings['allowed_screens'] )
            ? implode( "\n", array_map( 'sanitize_text_field', $settings['allowed_screens'] ) )
            : '';
        ?>
        <textarea
            name="<?php echo esc_attr( $option_name ); ?>[allowed_screens]"
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
     * Sanitize settings input.
     *
     * @param mixed $settings Raw settings value.
     */
    public function sanitize_settings( $settings ): array
    {
        $settings = is_array( $settings ) ? $settings : [];

        $allowed_roles = [];
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

        $allowed_roles = array_values( array_unique( $allowed_roles ) );

        $allowed_screens = [];
        if ( ! empty( $settings['allowed_screens'] ) ) {
            $raw_screens = is_array( $settings['allowed_screens'] )
                ? $settings['allowed_screens']
                : preg_split( '/[\r\n]+/', (string) $settings['allowed_screens'] );

            foreach ( (array) $raw_screens as $screen_id ) {
                $normalized = Screen_ID_Normalizer::normalize( (string) $screen_id );
                if ( '' !== $normalized ) {
                    $allowed_screens[] = $normalized;
                }
            }
        }

        $allowed_screens = array_values( array_unique( $allowed_screens ) );

        $sanitized = [
            'allowed_roles'      => $allowed_roles,
            'include_update_nag' => ! empty( $settings['include_update_nag'] ),
            'auto_open_critical' => ! empty( $settings['auto_open_critical'] ),
            'allowed_screens'    => $allowed_screens,
        ];

        $this->repository->prime( $sanitized );

        return $sanitized;
    }
}
