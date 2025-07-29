<?php
namespace AICA\Admin\Tabs;

use AICA\Plugin;

class CompetitorAuditTab extends BaseTab {

    public function __construct()
    {
        parent::__construct('competitor_audit', 'Competitor Audit');
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function render(): void {
        ?>
        <div class="aica-audit-tool">
            <p>
                <input type="url" class="audit-url" placeholder="https://competitor.com/page" style="width:70%">
                <button type="button" class="button button-primary run-audit">
                    <?php _e( 'Analyze', 'aica' ); ?>
                </button>
            </p>
            <div class="results-container" style="display:none">
                <pre class="audit-output" style="max-height:400px;overflow:auto"></pre>
                <p>
                    <button type="button" class="button apply-keywords"><?php _e( 'Apply Keywords', 'aica' ); ?></button>
                    <button type="button" class="button apply-structure"><?php _e( 'Copy Structure', 'aica' ); ?></button>
                </p>
            </div>
        </div>
        <?php
    }

    public function handleSave(): void {
        // This tab doesn't have form submission, only AJAX interactions
        wp_safe_redirect( wp_get_referer() );
        exit;
    }

    public function enqueue_scripts(): void {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'toplevel_page_aica-settings') {
            wp_enqueue_script(
                'aica-audit',
                plugins_url( 'assets/js/audit.js', Plugin::file() ),
                [],
                '0.3.0',
                true
            );
            wp_localize_script( 'aica-audit', 'aicaAudit', [
                'apiUrl' => rest_url( 'aica/v1/audit' ),
                'nonce'  => wp_create_nonce( 'wp_rest' ),
            ] );
        }
    }
}
