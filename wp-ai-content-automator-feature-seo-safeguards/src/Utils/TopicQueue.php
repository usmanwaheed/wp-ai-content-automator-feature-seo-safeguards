<?php
// File: src/Utils/TopicQueue.php
namespace AICA\Utils;

use wpdb;

class TopicQueue
{
    private static string $table;

    public static function init(): void
    {
        global $wpdb;
        self::$table = $wpdb->prefix . 'aica_topic_queues';
    }

    /**
     * Create the topic queue table (plugin activation hook).
     */
    public static function createTable(): void
    {
        global $wpdb;
        self::init();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE " . self::$table . " (
            strategy_id BIGINT NOT NULL PRIMARY KEY,
            topics LONGTEXT NOT NULL,
            used_topics LONGTEXT NOT NULL
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Seed a new queue for a strategy.
     */
    public static function initQueue(int $strategyId, array $topics): void
    {
        global $wpdb;
        self::init();
        $wpdb->replace(
            self::$table,
            [
                'strategy_id' => $strategyId,
                'topics'      => wp_json_encode(array_values($topics)),
                'used_topics' => wp_json_encode([]),
            ],
            ['%d','%s','%s']
        );
    }

    /**
     * Get next unused topic, rotate it to used list.
     */
    public static function getNextTopic(int $strategyId): ?string
    {
        global $wpdb;
        self::init();
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT topics, used_topics FROM " . self::$table . " WHERE strategy_id = %d", $strategyId),
            ARRAY_A
        );
        if (!$row) return null;

        $all  = json_decode($row['topics'], true);
        $used = json_decode($row['used_topics'], true);

        $remaining = array_diff($all, $used);
        if (empty($remaining)) {
            $used = [];
            $remaining = $all;
        }
        $next = array_shift($remaining);
        $used[] = $next;

        $wpdb->update(
            self::$table,
            ['used_topics' => wp_json_encode(array_values($used))],
            ['strategy_id' => $strategyId],
            ['%s'], ['%d']
        );

        return $next;
    }
}
