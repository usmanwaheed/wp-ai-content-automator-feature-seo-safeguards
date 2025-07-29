<?php
// File: src/Rest/StrategyRoute.php
namespace AICA\Rest;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class StrategyRoute extends WP_REST_Controller
{
    /** Bootstrapped from Plugin::boot() */
    public static function register(): void
    {
        add_action('rest_api_init', fn () => (new self())->register_routes());
    }

    /*──────────  Routes  ──────────*/
    public function register_routes(): void
    {
        register_rest_route('aica/v1', '/strategy(?:/(?P<id>\d+))?', [
            'methods'             => [
                WP_REST_Server::READABLE,
                WP_REST_Server::EDITABLE,
                WP_REST_Server::CREATABLE,
                WP_REST_Server::DELETABLE,
            ],
            'permission_callback' => fn () => current_user_can('manage_options'),
            'callback'            => [self::class, 'dispatch'],
            'args'                => [   // full schema
                'source_url'      => ['type'=>'string','required'=>true],
                'kw_primary'      => ['type'=>'string'],
                'kw_secondary'    => ['type'=>'string'],
                'tone'            => ['type'=>'string','enum'=>['professional','casual','authoritative']],
                'min_words'       => ['type'=>'integer','minimum'=>300],
                'max_words'       => ['type'=>'integer','minimum'=>400],
                'recurrence'      => ['type'=>'string','enum'=>['daily','interval','weekly','monthly']],
                'publish_time'    => ['type'=>'string','pattern'=>'^\\d{2}:\\d{2}$'],
                'interval_days'   => ['type'=>'integer','minimum'=>0],
                'start_date'      => ['type'=>'string','format'=>'date'],
                'weekdays'        => ['type'=>'array','items'=>['type'=>'string']],
                'month_day'       => ['type'=>'integer','minimum'=>1,'maximum'=>31],
                'stop_condition'  => ['type'=>'string','enum'=>['none','max_posts','end_date']],
                'max_posts'       => ['type'=>'integer','minimum'=>0],
                'end_date'        => ['type'=>'string','format'=>'date'],
                'category_id'     => ['type'=>'integer'],
            ],
        ]);
    }

    /*──────────  Dispatcher  ──────────*/
    public static function dispatch(WP_REST_Request $req): WP_REST_Response
    {
        $strategies = get_option('aica_strategies', []);
        $id         = $req->get_param('id');

        switch ($req->get_method()) {
            case 'GET':
                return new WP_REST_Response(
                    $id !== null ? ($strategies[$id] ?? null) : array_values($strategies)
                );

            case 'POST':
                $strategies[] = self::sanitize($req->get_params());
                break;

            case 'PUT':
                if ($id === null || !isset($strategies[$id])) {
                    return new WP_REST_Response(null, 404);
                }
                $strategies[$id] = self::sanitize($req->get_params());
                break;

            case 'DELETE':
                if ($id === null || !isset($strategies[$id])) {
                    return new WP_REST_Response(null, 404);
                }
                array_splice($strategies, (int) $id, 1);
                break;

            default:
                return new WP_REST_Response(null, 400);
        }

        update_option('aica_strategies', $strategies, false);

        return new WP_REST_Response(
            $req->get_method() === 'POST'
                ? $strategies
                : ($req->get_method() === 'DELETE' ? null : $strategies[$id])
        );
    }

    /*──────────  Sanitiser  ──────────*/
    private static function sanitize(array $in): array
    {
        $start = (isset($in['start_date']) && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $in['start_date']))
            ? $in['start_date'] : '';
        $end   = (isset($in['end_date']) && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $in['end_date']))
            ? $in['end_date'] : '';
        if ($start && $end && strtotime($end) < strtotime($start)) {
            $end = $start;
        }

        return [
            'source_url'      => esc_url_raw($in['source_url'] ?? ''),
            'kw_primary'      => sanitize_text_field($in['kw_primary'] ?? ''),
            'kw_secondary'    => sanitize_text_field($in['kw_secondary'] ?? ''),
            'tone'            => in_array($in['tone'] ?? 'professional', ['professional','casual','authoritative'], true)
                                    ? $in['tone'] : 'professional',
            'min_words'       => max(300, intval($in['min_words'] ?? 0)),
            'max_words'       => max(400, intval($in['max_words'] ?? 0)),
            'recurrence'      => in_array($in['recurrence'] ?? 'daily', ['daily','interval','weekly','monthly'], true)
                                    ? $in['recurrence'] : 'daily',
            'publish_time'    => preg_match('/^\\d{2}:\\d{2}$/', $in['publish_time'] ?? '')
                                    ? $in['publish_time'] : '09:00',
            'interval_days'   => max(0, intval($in['interval_days'] ?? 0)),
            'start_date'      => $start,
            'weekdays'        => array_values(array_intersect((array)($in['weekdays'] ?? []), ['mon','tue','wed','thu','fri','sat','sun'])),
            'month_day'       => min(31, max(1, intval($in['month_day'] ?? 1))),
            'stop_condition'  => in_array($in['stop_condition'] ?? 'none', ['none','max_posts','end_date'], true)
                                    ? $in['stop_condition'] : 'none',
            'max_posts'       => max(0, intval($in['max_posts'] ?? 0)),
            'end_date'        => $end,
            'category_id'     => intval($in['category_id'] ?? get_option('default_category')),
        ];
    }
}
