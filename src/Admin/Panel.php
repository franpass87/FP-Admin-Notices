<?php
/**
 * Renders the admin notices panel markup.
 */
declare(strict_types=1);

namespace FP\AdminNotices\Admin;

use function do_action;
use function esc_attr_e;
use function esc_attr_x;
use function esc_html_e;
use function esc_html_x;

/**
 * Outputs the persistent panel markup in the admin footer.
 */
class Panel
{
    /**
     * Render the markup for the modal panel.
     */
    public function render(): void
    {
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
}
