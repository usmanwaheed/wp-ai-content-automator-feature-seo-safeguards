<?php
// File: src/Admin/SettingsPage.php
namespace AICA\Admin;

use AICA\Admin\Tabs\BaseTab;
use AICA\Admin\Tabs\DomainTab;
use AICA\Admin\Tabs\CompetitorAuditTab;
use AICA\Admin\Tabs\StrategySingleTab;
use AICA\Plugin;

class SettingsPage
{
    /** @var BaseTab[] */
    private array $tabs;

    public function __construct()
    {
        // Domain tab + Competitor Audit tab + five fixed strategy tabs
        $this->tabs = [ new DomainTab(), new CompetitorAuditTab() ];
        for ($i = 0; $i < 5; $i++) {
            $this->tabs[] = new StrategySingleTab($i);
        }

        add_action('admin_menu',            [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /*──────────  Menu  ──────────*/
    public function add_menu(): void
    {
        add_menu_page(
            'AI Content Automator',
            'AI Content',
            'manage_options',
            'aica-settings',
            [$this, 'render_page'],
            'dashicons-edit',
            80
        );
    }

    /*──────────  Page  ──────────*/
    public function render_page(): void
    {
        $default = $this->tabs[0]->getSlug();
        $current = sanitize_text_field($_GET['tab'] ?? $default);
        $msg     = sanitize_text_field($_GET['aica_msg'] ?? '');

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">AI Content Automator</h1>

            <?php if ($msg): ?>
                <div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
            <?php endif; ?>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($this->tabs as $t): ?>
                    <?php $slug = $t->getSlug(); ?>
                    <a href="<?= esc_url(add_query_arg('tab', $slug)) ?>"
                       class="nav-tab <?= $current === $slug ? 'nav-tab-active' : '' ?>">
                        <?= esc_html($t->getLabel()) ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>">
                <?php wp_nonce_field('aica_save_' . $current); ?>
                <input type="hidden" name="action" value="aica_save_<?= esc_attr($current) ?>">
                <?php do_action('aica_render_tab_' . $current); ?>
                <?php submit_button('Save Changes'); ?>
            </form>
        </div>
        <?php
    }

    /*──────────  Assets  ──────────*/
    public function enqueue_assets(): void
    {
        // Core admin stylesheet
        wp_enqueue_style(
            'aica-admin',
            plugins_url('assets/css/admin.css', Plugin::file())
        );

        // Simple accordion for domain-profile preview
        wp_add_inline_script(
            'jquery-core',
            'jQuery(function($){
                 $(".aica-accordion-toggle").on("click",function(){
                     $(this).next(".aica-accordion-content").toggle();
                 });
             });'
        );
    }
}
