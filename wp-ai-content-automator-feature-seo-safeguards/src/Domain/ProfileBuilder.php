<?php
namespace AICA\Domain;

use AICA\Content\MiniSummariser;
use AICA\Content\DomainInsightsExtractor;

/**
 * Builds and caches the domain profile:
 *  - merged summary of the selected pages
 *  - expertise + audience tags for downstream prompts
 */
class ProfileBuilder
{
    public static function registerHook(): void
    {
        add_action('aica_build_profile_job', [self::class, 'run']);
    }

    public static function queue(): void
    {
        function_exists('as_enqueue_async_action')
            ? as_enqueue_async_action('aica_build_profile_job')
            : wp_schedule_single_event(time() + 60, 'aica_build_profile_job');
    }

    public static function run(): void
    {
        $opts = get_option(\AICA\Plugin::OPTION_KEY, []);
        $urls = array_values(array_filter($opts['profile_pages'] ?? [], 'strlen'));

        /* ---------- strict selection ------------ */
        if (empty($urls)) {
            // Fallback only when nothing selected
            $base  = home_url();
            $urls  = [$base, $base . '/about'];
            foreach (get_pages(['parent' => 0]) as $p) {
                if (preg_match('/(solution|product|service|feature)/i', $p->post_name)) {
                    $urls[] = get_permalink($p->ID);
                }
            }
        }

        if (!$urls) {
            error_log('[AICA] Domain profile: no pages to summarise.');
            return;
        }

        $summaries   = [];
        $summariser  = new MiniSummariser();

        foreach ($urls as $u) {
            $html = wp_remote_retrieve_body(wp_remote_get($u));
            if (!$html) { continue; }
            $text = wp_strip_all_tags($html);
            $sum  = $summariser->summarise($text);
            $summaries[] = "### {$u}\n{$sum}";
        }

        $merged = $summariser->summarise(implode("\n\n", $summaries));

        /* ---------- extract domain insights ---------- */
        $insights = (new DomainInsightsExtractor())->extract($merged);

        update_option('aica_domain_profile', [
            'updated'        => current_time('mysql'),
            'profile'        => $merged,
            'pages'          => $urls,
            'expertise_tags' => $insights['expertise'],
            'audience_tags'  => $insights['audience'],
        ], false);

        error_log('[AICA] Domain profile rebuilt at ' . current_time('mysql'));
    }
}
