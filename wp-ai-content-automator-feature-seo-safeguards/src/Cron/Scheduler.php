<?php
// File: src/Cron/Scheduler.php
namespace AICA\Cron;

use AICA\Content\Planner;
use AICA\Domain\ContentStrategy;

final class Scheduler
{
    public const HOOK = 'aica_generate_content';

    public function __construct()
    {
        add_action(self::HOOK, [$this, 'run']);
        add_action('aica_strategy_event', [$this, 'run_single_strategy']);
        add_filter('cron_request', [$this, 'extendCronTimeout']);
    }

    /** Register WP-Cron event on activation. */
    public function registerCron(): void
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + 120, 'hourly', self::HOOK);
        }
    }

    public function clearCron(): void
    {
        wp_clear_scheduled_hook(self::HOOK);
    }

    /** Cron callback – kicks off the planner. */
    public function run(): void
    {
        // Prevent concurrent execution
        if (get_transient('aica_task_running')) {
            return;
        }
        
        set_transient('aica_task_running', true, 300); // 5 minute lock
        
        try {
            $strategies = array_map(
                fn (array $raw) => new \AICA\Domain\ContentStrategy($raw),
                get_option('aica_strategies', [])
            );
            
            foreach ($strategies as $s) {
                if (!$this->due($s)) continue;
                
                try {
                    $planner = new \AICA\Content\Planner();
                    $planner->execute_strategy($s);
                } catch (\Throwable $e) {
                    error_log('[AICA] Strategy execution failed: ' . $e->getMessage());
                }
            }
            
            // Run Phase 2-3 robots blocked-slugs cleanup
            $rm = new \AICA\SEO\RobotsManager();
            $rm->purge_expired_blocks();
            
            if (get_option('aica_rewrite_dirty')) {
                $rm->update_rewrite_rules();
                flush_rewrite_rules(false);
                update_option('aica_rewrite_dirty', false);
            }
        } finally {
            delete_transient('aica_task_running');
        }
    }

    private function due(\AICA\Domain\ContentStrategy $s): bool
    {
        $next = $this->calc_next_run($s);
        return time() >= $next && time() <= $next + 300; // 5 minute window
    }

    public function calc_next_run(\AICA\Domain\ContentStrategy $s): int
    {
        $now = new \DateTime('now', wp_timezone());
        $today = clone $now;
        $today->setTime(...explode(':', $s->publish_time));
        
        // If today's time has passed, calculate next occurrence
        if ($today <= $now) {
            switch ($s->recurrence) {
                case 'daily':
                    $today->add(new \DateInterval('P1D'));
                    break;
                case 'weekly':
                    $today->add(new \DateInterval('P7D'));
                    break;
                case 'monthly':
                    $today->add(new \DateInterval('P1M'));
                    break;
                case 'interval':
                    if ($s->interval_days > 0) {
                        $today->add(new \DateInterval('P' . $s->interval_days . 'D'));
                    }
                    break;
            }
        }
        
        return $today->getTimestamp();
    }

    /**
     * Run a single strategy by ID (called by aica_strategy_event)
     */
    public function run_single_strategy(int $strategyId): void
    {
        if (get_transient('aica_task_running')) {
            return;
        }
        
        set_transient('aica_task_running', true, 300);
        
        try {
            $strategies = get_option('aica_strategies', []);
            if (!isset($strategies[$strategyId])) {
                return;
            }
            
            $strategy = new \AICA\Domain\ContentStrategy($strategies[$strategyId]);
            $planner = new \AICA\Content\Planner();
            $planner->execute_strategy($strategy);
            
            // Schedule next run for this strategy
            $nextRun = $this->calc_next_run($strategy);
            wp_schedule_single_event($nextRun, 'aica_strategy_event', [$strategyId]);
            
        } catch (\Throwable $e) {
            error_log('[AICA] Single strategy execution failed: ' . $e->getMessage());
        } finally {
            delete_transient('aica_task_running');
        }
    }

    /** Increase timeout whenever a cron request is fired. */
    public function extendCronTimeout(array $args): array
    {
        $args['args']['timeout'] = max($args['args']['timeout'] ?? 10, 300);
        return $args;
    }

    /**
     * Determine if this strategy should run today.
     */
    public function shouldRun(ContentStrategy $strat): bool
    {
        // use WP timezone
        $tz    = wp_timezone();
        $today = new \DateTime('today', $tz);
        $rec   = $strat->recurrence;

        switch ($rec) {
            case 'daily':
                return true;

            case 'interval':
                if (! $strat->start_date) {
                    return false;
                }
                $start = \DateTime::createFromFormat('Y-m-d', $strat->start_date, $tz);
                if (! $start) {
                    return false;
                }
                $diff = $start->diff($today)->days;
                return $strat->interval_days > 0 && ($diff % $strat->interval_days) === 0;

            case 'weekly':
                $map = [1=>'mon',2=>'tue',3=>'wed',4=>'thu',5=>'fri',6=>'sat',7=>'sun'];
                $day = strtolower($map[(int)$today->format('N')]);
                return in_array($day, $strat->weekdays, true);

            case 'monthly':
                return (int)$today->format('j') === $strat->month_day;

            default:
                return false;
        }
    }
}
