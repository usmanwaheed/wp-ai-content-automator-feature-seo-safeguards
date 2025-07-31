<?php
declare(strict_types=1);

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
        $strategies = get_option('aica_strategies', []);
        $changed = false;

        // Legacy settings from old version
        $legacySettings = get_option('aica_settings', []);

        if (empty($strategies) && !empty($legacySettings['source_url'])) {
            $strategies[] = [
                'source_url'      => $legacySettings['source_url'],
                'kw_primary'      => $legacySettings['kw_primary']   ?? '',
                'kw_secondary'    => $legacySettings['kw_secondary'] ?? '',
                'tone'            => $legacySettings['tone']         ?? 'professional',
                'min_words'       => $legacySettings['min_words']    ?? 600,
                'max_words'       => $legacySettings['max_words']    ?? 1200,
                'recurrence'      => $legacySettings['recurrence']   ?? 'daily',
                'weekdays'        => $legacySettings['weekdays']     ?? [],
                'publish_time'    => $legacySettings['publish_time'] ?? '09:00',
                'category_id'     => $legacySettings['category_id']  ?? (int) get_option('default_category'),
                // New default fields
                'interval_days'   => 0,
                'start_date'      => '',
                'month_day'       => 1,
                'stop_condition'  => 'none',
                'max_posts'       => 0,
                'end_date'        => '',
                'enable_images'   => 0,
                'image_count'     => 3,
            ];

            $changed = true;
            error_log('[AICA] Legacy strategy migrated to aica_strategies.');
        }

        // Ensure all strategies have required new default fields
        foreach ($strategies as $i => $strategy) {
            foreach (self::getDefaultFields() as $field => $default) {
                if (!array_key_exists($field, $strategy)) {
                    $strategies[$i][$field] = $default;
                    $changed = true;
                }
            }
        }

        if ($changed) {
            update_option('aica_strategies', $strategies, false);
        }
    }

    /**
     * Returns an array of default strategy fields.
     *
     * @return array<string, mixed>
     */
    private static function getDefaultFields(): array
    {
        return [
            'interval_days'   => 0,
            'start_date'      => '',
            'weekdays'        => [],
            'month_day'       => 1,
            'stop_condition'  => 'none',
            'max_posts'       => 0,
            'end_date'        => '',
            'enable_images'   => 0,
            'image_count'     => 3,
        ];
    }
}
