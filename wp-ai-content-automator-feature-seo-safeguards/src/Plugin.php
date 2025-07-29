<?php
namespace AICA;

use AICA\Admin\SettingsPage;
use AICA\Cron\Scheduler;
use AICA\Domain\ProfileBuilder;
use AICA\Content\PlannerMigration;
use AICA\Rest\StrategyRoute;
use AICA\Domain\ContentStrategy;

/* Hooks to reuse SettingsPage::handleSave for start / pause buttons */
add_action('admin_post_aica_start_strategy', [SettingsPage::class, 'handleSave']);
add_action('admin_post_aica_pause_strategy', [SettingsPage::class, 'handleSave']);

final class Plugin
{
    public const OPTION_KEY = 'aica_settings';
    private static string $file;

    public static function boot(string $file): void
    {
        self::$file = $file;
        add_action('plugins_loaded',            [self::class, 'init']);
        register_activation_hook($file,         [self::class, 'activate']);
        register_deactivation_hook($file,       [self::class, 'deactivate']);
    }

    public static function file(): string
    {
        return self::$file;
    }

    public static function init(): void
    {
        PlannerMigration::migrate();
        new SettingsPage();
        new Scheduler();
        ProfileBuilder::registerHook();
        add_action('rest_api_init', [StrategyRoute::class, 'register']);
    }

    public static function activate(): void
    {
        // Initialize strategies with default values if they don't exist
        if (false === get_option('aica_strategies')) {
            $defaults = [];
            for ($i = 0; $i < 5; $i++) {
                $defaults[$i] = [
                    'enabled' => false,
                    'source_url' => '',
                    'kw_primary' => '',
                    'kw_secondary' => '',
                    'tone' => 'conversational',
                    'min_words' => 600,
                    'max_words' => 1200,
                    'recurrence' => 'daily',
                    'weekdays' => [],
                    'publish_time' => '09:00',
                    'category_id' => get_option('default_category'),
                    'interval_days' => 0,
                    'start_date' => '',
                    'month_day' => 1,
                    'stop_condition' => 'none',
                    'max_posts' => 0,
                    'end_date' => '',
                    'enable_images' => false,
                    'image_count' => 3,
                ];
            }
            update_option('aica_strategies', $defaults);
        }

        // Clear old cron jobs
        wp_clear_scheduled_hook('aica_generate_content');

        // Schedule enabled strategies
        $raw = get_option('aica_strategies', []);
        $sched = new Scheduler();

        foreach ($raw as $idx => $row) {
            try {
                $s = new ContentStrategy($row);
            } catch (\InvalidArgumentException $e) {
                error_log('AICA activate(): skipped strategy #' . $idx . ' - ' . $e->getMessage());
                continue;
            }

            if (!$s->enabled) {
                continue;
            }

            $next = $sched->calc_next_run($s);
            wp_schedule_single_event($next, 'aica_strategy_event', [$idx]);
        }
    }

    public static function deactivate(): void
    {
        (new Scheduler())->clearCron();
    }
}