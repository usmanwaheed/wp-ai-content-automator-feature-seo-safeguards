<?php
// File: src/Content/PlannerMigration.php
namespace AICA\Content;

/**
 * One-time migration: converts the legacy single-strategy settings
 * (stored in aica_settings) into the new multi-strategy array
 * (aica_strategies), and fills in all new default fields.
 * Runs harmlessly on every init().
 */
class PlannerMigration
{
    public static function migrate(): void
    {
        // Load existing strategies or empty array
        $all = get_option('aica_strategies', []);
        $changed = false;

        // Legacy migration if no existing strategies
        $old = get_option('aica_settings', []);
        if (empty($all) && !empty($old['source_url'])) {
            $legacy = [
                'source_url'      => $old['source_url'],
                'kw_primary'      => $old['kw_primary']   ?? '',
                'kw_secondary'    => $old['kw_secondary'] ?? '',
                'tone'            => $old['tone']         ?? 'professional',
                'min_words'       => $old['min_words']    ?? 600,
                'max_words'       => $old['max_words']    ?? 1200,
                'recurrence'      => $old['recurrence']   ?? 'daily',
                'weekdays'        => $old['weekdays']     ?? [],
                'publish_time'    => $old['publish_time'] ?? '09:00',
                'category_id'     => $old['category_id']  ?? get_option('default_category'),
                // new default fields
                'interval_days'   => 0,
                'start_date'      => '',
                'month_day'       => 1,
                'stop_condition'  => 'none',
                'max_posts'       => 0,
                'end_date'        => '',
                'enable_images'   => 0,
                'image_count'     => 3,
            ];

            $all = [$legacy];
            $changed = true;
            update_option('aica_strategies', $all, false);
            error_log('[AICA] Legacy strategy migrated to aica_strategies.');
        }

        // Defaults migration for any existing strategies
        foreach ($all as $i => $s) {
            foreach ([
                'interval_days'  => 0,
                'start_date'     => '',
                'weekdays'       => [],
                'month_day'      => 1,
                'stop_condition' => 'none',
                'max_posts'      => 0,
                'end_date'       => '',
                'enable_images'  => 0,
                'image_count'    => 3,
            ] as $field => $default) {
                if (! isset($s[$field])) {
                    $all[$i][$field] = $default;
                    $changed = true;
                }
            }
        }

        if ($changed) {
            update_option('aica_strategies', $all, false);
        }
    }
}
