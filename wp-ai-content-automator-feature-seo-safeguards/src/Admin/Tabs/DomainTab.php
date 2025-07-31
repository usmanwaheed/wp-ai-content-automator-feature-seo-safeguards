<?php
// File: src/Admin/Tabs/DomainTab.php
namespace AICA\Admin\Tabs;

use AICA\Domain\ProfileBuilder;

class DomainTab extends BaseTab
{
    /** Model selector – curated list (June 2025) */
    private array $models = [
        'anthropic/claude-3-5-sonnet' => 'Claude 3.5 Sonnet (Anthropic)',
        'anthropic/claude-3-7-sonnet' => 'Claude 3.7 Sonnet (Anthropic)',
        'openai/gpt-4o'               => 'GPT-4o (OpenAI)',
        'openai/gpt-4.1-mini' => 'GPT-4.1 (OpenAI)',
        'google/gemini-1.5-pro'       => 'Gemini 1.5 Pro (Google)',
        'anthropic/claude-opus-4'     => 'Claude Opus 4 (Anthropic)',
        'google/gemini-2.5-pro'       => 'Gemini 2.5 Pro (Google)',
    ];

    public function __construct()
    {
        parent::__construct('domain', 'Domain Profile');
    }

    /*──────────  Render  ──────────*/
    public function render(): void
    {
        $o      = get_option('aica_settings', []);
        $domain = get_option('aica_domain_profile', []);

        wp_nonce_field('aica_save_domain', 'aica_domain_nonce');
        ?>
        <table class="form-table">
            <!-- API key -->
            <tr>
                <th scope="row">
                    <label for="aica_openai_key">AI&nbsp;API&nbsp;Key</label>
                    <span class="dashicons dashicons-editor-help"
                          title="Enter your AI provider’s secret key. Required before any content can be generated."
                          style="vertical-align:middle;"></span>
                </th>
                <td>
                    <input type="password" id="aica_openai_key" name="aica_settings[openai_key]"
                           value="<?= esc_attr($o['openai_key'] ?? '') ?>" class="regular-text">
                </td>
            </tr>

            <!-- Model -->
            <tr>
                <th scope="row">
                    <label for="aica_model">AI&nbsp;Model</label>
                    <span class="dashicons dashicons-editor-help"
                          title="Select an AI model for content generation."
                          style="vertical-align:middle;"></span></span>
                </th>
                <td>
                    <select name="aica_settings[model]" id="aica_model">
                        <?php
                        $sel = $o['model'] ?? 'openai/gpt-4.1-mini'; // default selected value
                        foreach ($this->models as $val => $label) {
                            $selected = selected($sel, $val, false);
                            $disabled = $val !== 'openai/gpt-4.1-mini' ? 'disabled' : '';
                            echo "<option value='{$val}' {$selected} {$disabled}>{$label}</option>";
                        }
                        ?>
                    </select>
                </td>
            </tr>

            <!-- Similarity Threshold -->
            <tr>
                <th scope="row">
                    <label for="aica_similarity_threshold">Similarity Threshold</label>
                    <span class="dashicons dashicons-editor-help"
                          title="Controls how different new content must be from existing posts (higher = more unique). Increase to prevent duplicate content, decrease to allow more similar posts."
                          style="vertical-align:middle;"></span>
                </th>
                <td>
                    <input type="range" id="aica_similarity_threshold" name="aica_settings[similarity_threshold]"
                           min="70" max="95" step="5" 
                           value="<?= esc_attr($o['similarity_threshold'] ?? 85) ?>"
                           oninput="document.getElementById('threshold_value').textContent = this.value + '%'">
                    <span id="threshold_value"><?= esc_html(($o['similarity_threshold'] ?? 85) . '%') ?></span>
                </td>
            </tr>

            <!-- Show AI errors -->
            <tr>
                <th scope="row">
                    Show AI errors in WP-Admin
                    <span class="dashicons dashicons-editor-help"
                          title="When enabled, duplicate-content or angle-exhaustion warnings appear as admin notices on the dashboard and inside the AI Content page."
                          style="vertical-align:middle;"></span>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="aica_settings[show_ai_errors]" value="1"
                               <?php checked(1, $o['show_ai_errors'] ?? 0); ?>>
                        Display duplicate / angle-exhaustion warnings.
                    </label>
                </td>
            </tr>

            <!-- Source pages -->
            <tr>
                <th scope="row">
                    Profile Source Pages
                    <span class="dashicons dashicons-editor-help"
                          title="Pages used to build your domain profile. Pick only core, representative content."
                          style="vertical-align:middle;"></span>
                </th>
                <td>
                    <select name="aica_settings[profile_pages][]" multiple style="min-width:320px;">
                        <?php
                        foreach (get_pages() as $p) {
                            $url = get_permalink($p->ID);
                            $sel = in_array($url, $o['profile_pages'] ?? [], true) ? 'selected' : '';
                            echo "<option value='" . esc_url($url) . "' {$sel}>" . esc_html($p->post_title) . '</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>

            <!-- Auto-build info -->
            <?php if (!empty($domain['updated'])): ?>
                <tr><th scope="row">Last built</th><td><em><?= esc_html($domain['updated']) ?></em></td></tr>
                <tr>
                    <th scope="row">Profile (preview)</th>
                    <td>
                        <button type="button" class="button-secondary aica-accordion-toggle">Show / hide</button>
                        <div class="aica-accordion-content" style="display:none;white-space:pre-wrap;background:#fff;border:1px solid #ccd0d4;padding:12px;max-height:240px;overflow:auto;">
                            <?= wp_kses_post($domain['profile'] ?? '') ?>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>

            <!-- Expertise tags -->
            <tr>
                <th scope="row">
                    <label for="aica_exp_tags">Expertise&nbsp;Tags</label>
                    <span class="dashicons dashicons-editor-help"
                          title="Comma-separated expertise areas fed into the prompt to position you as an authority."
                          style="vertical-align:middle;"></span>
                </th>
                <td>
                    <input type="text" id="aica_exp_tags" name="aica_settings[expertise_tags]"
                           value="<?= esc_attr(implode(', ', $o['expertise_tags'] ?? ($domain['expertise_tags'] ?? []))) ?>"
                           class="regular-text">
                </td>
            </tr>

            <!-- Audience tags -->
            <tr>
                <th scope="row">
                    <label for="aica_aud_tags">Audience&nbsp;Tags</label>
                    <span class="dashicons dashicons-editor-help"
                          title="Comma-separated description of your target readers."
                          style="vertical-align:middle;"></span>
                </th>
                <td>
                    <input type="text" id="aica_aud_tags" name="aica_settings[audience_tags]"
                           value="<?= esc_attr(implode(', ', $o['audience_tags'] ?? ($domain['audience_tags'] ?? []))) ?>"
                           class="regular-text">
                </td>
            </tr>

            <!-- Structured Data Settings -->
            <tr>
                <th scope="row">
                    Structured Data Settings
                    <span class="dashicons dashicons-editor-help"
                          title="Enable automatic JSON-LD schema markup for generated posts to improve SEO."
                          style="vertical-align:middle;"></span>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="aica_settings[schema_article]" value="1"
                               <?php checked(1, $o['schema_article'] ?? 1); ?>>
                        Enable Article Schema
                    </label><br>
                    <label>
                        <input type="checkbox" name="aica_settings[schema_faq]" value="1"
                               <?php checked(1, $o['schema_faq'] ?? 1); ?>>
                        Enable FAQ Schema
                    </label>
                </td>
            </tr>
        </table>

        <p>
            <button type="submit" class="button" name="aica_action" value="build_profile">
                <?= empty($domain) ? 'Build' : 'Re-run' ?> Domain Profile
            </button>
        </p>
        <?php
    }

    /*──────────  Save  ──────────*/
    public function handleSave(): void
    {
        $this->requireNonce('aica_save_domain', 'aica_domain_nonce');

        $settings                   = get_option('aica_settings', []);
        $settings['openai_key']     = sanitize_text_field($_POST['aica_settings']['openai_key'] ?? '');
        $settings['model']          = sanitize_text_field($_POST['aica_settings']['model'] ?? 'anthropic/claude-3-5-sonnet');
        $settings['profile_pages']  = array_values(array_filter(array_map('esc_url_raw', (array)($_POST['aica_settings']['profile_pages'] ?? []))));
        $settings['expertise_tags'] = $this->csvToArray($_POST['aica_settings']['expertise_tags'] ?? '');
        $settings['audience_tags']  = $this->csvToArray($_POST['aica_settings']['audience_tags'] ?? '');
        $settings['show_ai_errors'] = isset($_POST['aica_settings']['show_ai_errors']) ? 1 : 0;
        $settings['similarity_threshold'] = min(95, max(70, intval($_POST['aica_settings']['similarity_threshold'] ?? 85)));
        $settings['schema_article'] = isset($_POST['aica_settings']['schema_article']) ? 1 : 0;
        $settings['schema_faq'] = isset($_POST['aica_settings']['schema_faq']) ? 1 : 0;

        update_option('aica_settings', $settings, false);

        if (sanitize_text_field($_POST['aica_action'] ?? '') === 'build_profile') {
            ProfileBuilder::queue();
            wp_safe_redirect(add_query_arg('aica_msg', 'profile_queued', wp_get_referer()));
        } else {
            wp_safe_redirect(add_query_arg('aica_msg', 'domain_saved', wp_get_referer()));
        }
        exit;
    }

    private function csvToArray(string $csv): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $csv))));
    }
}
